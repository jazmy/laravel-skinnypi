<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;

class HomeController extends Controller
{
    /**
    * Create a new controller instance.
    *
    * @return void
    */
    public function __construct()
    {
        $this->middleware( 'auth', [ 'only' => [ 'getDashboard', 'getMyWeightLogs' ] ] );
    }

    /**
    * Show the application homepage.
    *
    * @return \Illuminate\Http\Response
    */
    public function getIndex()
    {
        // send the user to the dashboard
        return redirect('/dashboard');

        return view( 'home' );
    }

    public function triggerLights()
    {
    //Trigger Hue Lights
    $client = new \GuzzleHttp\Client();
    $response = $client->request('GET', 'https://maker.ifttt.com/trigger/FitbitSuccess/with/key/bN-odmNsdoSsFqABIxPMBn');
    echo "Made it here";
    echo $response->getStatusCode();
}

    /**
    * Display the dashboard to the logged in user
    *
    * @return Illuminate\Http\Response
    */
    public function getDashboard()
    {
        // get the current user data
        $user = Auth::user();

        // we will need some records to properly display the dashboard
        // first, we need to get the access token record of this user that we have on file
        // from the fitbit_accesstokens table so that we can know whether the user has authorized us
        // or not
        $accessToken = DB::table('fitbit_accesstokens')->where('user_id', $user->id)->first();

        // next, we will get the two last weight log entries for the user so that we can compare and check if
        // the user lost weight or not
        $recent_logs = DB::table('weight_logs')
        ->where('user_id', $user->id)
        // ->orderBy('created_at', 'DESC')
        ->orderBy('id', 'DESC')
        ->take( 2 )
        ->get();
        // dump
        // dd($accessToken, $recent_logs);

        // create an array that will hold our result for the
        // weight difference calculation
        $data = array();

        // check to see if we have the weight logs records that we need
        if ( $recent_logs ) {
            // we have them
            // check if we have at least two since we cannot compare unless we have more than one record
            if ( count( $recent_logs ) > 1 ) {
                // we have more than one record
                // separate the most recent record ( it will be the first record in the $recent_logs since we are ordering
                // by DESCending order)
                $most_recent = $recent_logs[0];
                // and the one before the most recent
                $previous = $recent_logs[1];

                // let's see if the weight contained in the $most_recent is less than the one
                // in the $previous record
                if ( $most_recent->weight < $previous->weight ) {
                    // Hurray, the user has lost weight!!!!!
                    $data = array( 'status' => true, 'most_recent' => $most_recent, 'previous' => $previous );
                } else {
                    // the user did not lose weight
                    $data = array( 'status' => false, 'details' => 'You have not lost weight!' );
                }

            } else {
                // we have less than two records
                // that's not enough to use for comparison
                $data = array( 'status' => false, 'details' => 'Not enough data for comparison!' );
            }
        } else {
            // we do not have them
            $data = array( 'status' => false, 'details' => 'No weight log found!' );
        }

        // display the dashboard and pass along the data we will need to make the dashboard happen
        return view( 'dashboard', array( 'accessToken' => $accessToken, 'data' => $data ) );
    }

    /**
    * Get the weight log entries that this user has in the system and display them in a paginated
    * interface
    *
    * @access public
    * @return Illuminate\Http\Response
    */
    public function getMyWeightLogs()
    {
        // get the currently logged in user
        $user = Auth::user();

        // get the weight logs for this user
        $weight_logs = DB::table('weight_logs')->where('user_id', $user->id)->orderBy('id', 'DESC')->paginate( 50 );

        foreach ($weight_logs as $value) {
            $value->pounds = round(($value->weight) * 2.2046226218, 1);
        }

        // now show the page with the weight logs that we have for the user
        return view( 'weight_logs', [ 'weight_logs' => $weight_logs  ] );
    }

    public function myWeightLogsRss($userid)
    {
        // next, we will get the two last weight log entries for the user so that we can compare and check if
        // the user lost weight or not
        $recent_logs = DB::table('weight_logs')
        ->where('user_id', $userid)
        ->orderBy('id', 'DESC')
        ->take( 2 )
        ->get();

        // check to see if we have the weight logs records that we need
        if ( $recent_logs ) {
            // we have them
            // check if we have at least two since we cannot compare unless we have more than one record
            if ( count( $recent_logs ) > 1 ) {
                // we have more than one record
                // separate the most recent record ( it will be the first record in the $recent_logs since we are ordering
                // by DESCending order)
                $most_recent = $recent_logs[0];
                // and the one before the most recent
                $previous = $recent_logs[1];

                // let's see if the weight contained in the $most_recent is less than the one
                // in the $previous record
                if ( $most_recent->weight < $previous->weight ) {
                    // Hurray, the user has lost weight!!!!!
                    $status = "success";
                } else {
                    // the user did not lose weight
                    $status = "fail";
                }

            } else {
                // we have less than two records
                // that's not enough to use for comparison
                $status = 'Not enough data for comparison!';
            }
        } else {
            // we do not have them
            $status = 'No weight log found!';
        }


        // get the weight logs for this user
        $weight_logs = DB::table('weight_logs')->where('user_id', $userid)->orderBy('id', 'DESC')->get();

        foreach ($weight_logs as $value) {
            $value->pounds = round(($value->weight) * 2.2046226218, 1);
            $value->status = $status;
        }

        // now show the page with the weight logs that we have for the user
        return view( 'weight_logs_rss', [ 'weight_logs' => $weight_logs  ] );
    }



}
