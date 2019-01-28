<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessFitBitNotificationDetails;
use App\User;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use League\OAuth2\Client\Token\AccessToken;
use djchen\OAuth2\Client\Provider\Fitbit;
use phpMQTT;

class FitBitNotificationController extends Controller
{
	/**
	 * Setup the class
	 *
	 * @access public
	 * @return void
	 */
	public function __construct()
	{
		// create an instance of the Fitbit Provider
		$provider = new Fitbit([
			'clientId' => config('skinnypi.FITBIT_API_CLIENT_ID', null),
			'clientSecret' => config('skinnypi.FITBIT_API_CLIENT_SECRET', null),
			'redirectUri' => url('authorize')
		]);

		// set the provider on the class to make it available throughout this class
		$this->provider = $provider;
	}

    /**
     * Handle Notification coming from fitbit in response to a subscription we have
     *
     * @access public
     * @return Response
     */
    public function postCallback()
    {
    	// the request from fitbit will come as a JSON POST request
    	// so let's get it
    	$data = file_get_contents("php://input");

    	// decode the posted json data to php array
    	$postData = json_decode( $data, true );

    	// we now have the array of data posted to us
    	// we will loop through the array and store them in the database so that
    	// we can quickly return the 204 response before fitbit times out after 3 seconds
    	$carbon = Carbon::now();
    	$now = $carbon->toDayDateTimeString();
    	$todayDate = $carbon->toDateString();

    	foreach ( $postData as $notificationData ) {
    		// store the notification data in the database
    		$notification_id = DB::table('fitbit_data')->insertGetId([
                'data' => json_encode( $notificationData )
            ]);

            // we have stored the notification into the db
            // let's dispatch the job that will process it so
            // that the queue listener can process the notification details
            // ASAP
            if ( $notification_id ) {
                dispatch( new ProcessFitBitNotificationDetails( $notification_id ) );
            }
    	}

        // we have stored the notification metadata and dispatch the jobs that will process
        // each of the notifications immediately on the queue listener

    	// we can return the 204 response so that the fitbit api can know that we have received
    	// the notification.
    	return response( 'Status: 204', 204 );
    }

    /**
     * Now get the details of all the notification we have received using the data we got
     * when Fitbit notified us of the event update
     *
     * @access public
     * @return Response
     */
    public function getNotificationDetails()
    {
        return "Removed!";
        // DB::table('fitbit_data')->truncate();
    	// DB::table('fitbit_accesstokens')->truncate();
    	// return DB::table('fitbit_accesstokens')->get();
    	// so we will get the notification details in the database and then get the details for all of them
    	$notifications = DB::table('fitbit_data')->where('processed', 0)->get();

    	// loop through the array if we have any and get the notification details for each of them from the
    	// fitbit api
    	if ( $notifications ) {
    		foreach ( $notifications as $notif ) {
    			// we stored the notification data as json...let's reconstruct it back to
    			// php array
    			$notifArray = json_decode( $notif->data, true );

    			// let's get the accesstoken record for this user so that we can get a token to use for the request
				$accesstokenRecord = DB::table('fitbit_accesstokens')
									->where('resource_owner_id', $notifArray['ownerId'])
									->first();

				// no access token saved for this user. let's skip to the next record
				if ( ! $accesstokenRecord ) {
					echo "no access token record";
					continue;
				}

				$access_token = $accesstokenRecord->access_token;

				// build an AccessToken object from the record we got
				$existingAccessToken = new AccessToken(
					[
						'access_token' => $accesstokenRecord->access_token,
						'refresh_token' => $accesstokenRecord->refresh_token,
						'resource_owner_id' => $accesstokenRecord->resource_owner_id,
						'expires' => $accesstokenRecord->expires
					]
				);

				// check if the access token has expired
				if ($existingAccessToken->hasExpired()) {
					// the access token has expired
					// we will request for a new one using the refresh token that we have
				    $newAccessToken = $this->provider->getAccessToken('refresh_token', [
				        'refresh_token' => $existingAccessToken->getRefreshToken()
				    ]);

				    // Save the new access token for the user
				    DB::table('fitbit_accesstokens')->where('resource_owner_id', $notifArray['ownerId'])
				    	->update( [ 'access_token' => $newAccessToken ] );

				    $access_token = $newAccessToken;
				}

    			// let's get the details from the api
    			$response = ( new Client )->get(
    				Fitbit::BASE_FITBIT_API_URL . "/1/user/{$notifArray['ownerId']}/body/date/{$notifArray['date']}.json",
    				array(
    					'headers' => [ 'Authorization' => "Bearer {$access_token}", ],
    				)
    			);

    			$rJson = json_decode( $response->getBody()->getContents(), true );
    			// return dd( $rJson );

    			$carbon = Carbon::now();
    			$now = $carbon->toDayDateTimeString();

    			// check if we have the details
    			if ( ! empty( $rJson['body'] ) ) {
    				// we have it...let's log it to a file meant for that user
    				$current_weight = $rJson['body']['weight'];

    				Storage::append(
    					"weight_log/{$notifArray['ownerId']}.txt", "{$now} : Current Weight => {$current_weight}"
    				);

                    // now let's save it into the weight_logs table
                    $user_id = $accesstokenRecord->user_id;

                    DB::table('weight_logs')->insert(
                        array(
                            'user_id' => $user_id, 'weight' => $current_weight,
                            'created_at' => DB::raw('now()'), 'updated_at' => DB::raw('now()')
                        )
                    );

                    // call the MQTT server with the proper message depending on whether our friend lost weight or not
                    self::__callMQTTServer( $user_id );

    				// now that we have loaded the full notification details, we will set the notification data
                    // as processed so that we will not process it again by accident
    				DB::table('fitbit_data')->where('id', $notif->id)->update( array( 'processed' => 1  ) );
    			} else {
    				// we got nothing from the api...we will just move on
    			}

    			// move on to the next record
    		}
    	}
    }



}
