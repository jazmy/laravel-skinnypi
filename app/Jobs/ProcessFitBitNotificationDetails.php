<?php

namespace App\Jobs;

use App\User;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use League\OAuth2\Client\Token\AccessToken;
use djchen\OAuth2\Client\Provider\Fitbit;

class ProcessFitBitNotificationDetails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The id of the notification that we received
     *
     * @var integer
     */
    private $notification_id;

    /**
     * Create a new job instance.
     *
     * @param integer $notification_id the id of the notification that was just received
     * @return void
     */
    public function __construct( $notification_id = null )
    {
        $this->notification_id = $notification_id;
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
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            // first get the notification metadata from the fitbit_data table
            $notif = DB::table('fitbit_data')->where('processed', 0)->where('id', $this->notification_id)->first();

            // check if we have it or not
            if ( $notif ) {
                // we have the notification
                // we stored the notification data as json...let's reconstruct it back to
                // php array
                $notifArray = json_decode( $notif->data, true );

                // let's get the accesstoken record for this user so that we can get a token to use for the request
                $accesstokenRecord = DB::table('fitbit_accesstokens')
                                    ->where('resource_owner_id', $notifArray['ownerId'])
                                    ->first();

                // no access token saved for this user. stop execution
                if ( ! $accesstokenRecord ) {
                    throw new Exception('no access token record');
                }

                $access_token = $accesstokenRecord->access_token;

                // build an AccessToken object from the record we got
                $existingAccessToken = new AccessToken([
                    'access_token' => $accesstokenRecord->access_token,
                    'refresh_token' => $accesstokenRecord->refresh_token,
                    'resource_owner_id' => $accesstokenRecord->resource_owner_id,
                    'expires' => $accesstokenRecord->expires
                ]);

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

                    print "Processing complete!".PHP_EOL;
                    return true;
                } else {
                    // we got nothing from the api...we will just move on
                    print "Got nothing from the api".PHP_EOL;
                    return true;
                }

            } else {
                print "Notification not found!".PHP_EOL;
                return true;
            }
        } catch ( Exception $e ) {
            info( $e );
            print $e->getMessage().PHP_EOL;

            return true;
        }
    }

    /**
     * Make the needed call to the MQTT server depending on if the user los weight or not
     *
     * @access private
     * @param integer $user_id the id of the user
     * @return void
     */
    private function __callMQTTServer( $user_id )
    {
        if ( empty( $user_id ) ) throw new Exception( 'No user id provided for MQTT server call' );

        // get the user record
        $user = User::find( $user_id );

        // so let's get the two most recent weight for this user and decide what kind of message we will send
        // once the user loses weight
        $recent_logs = DB::table('weight_logs')
                            ->where('user_id', $user->id)
                            ->orderBy('id', 'DESC')
                            ->take( 2 )
                            ->get();

        // we can only know if the user lost weight when we have more than one result
        if ( count( $recent_logs ) > 1 ) {
            // we have more than one result
            try {
                // initialize the connection
                $host = config('skinnypi.MQTT_SERVER', null);
                $port =  config('skinnypi.MQTT_PORT', null);
                $username = config('skinnypi.MQTT_USER', null);
                $password = config('skinnypi.MQTT_PASSWORD', null);
                $clientID = 'fitbit';

                $mqtt = new \phpMQTT( $host, $port, $clientID );

                // try to connect to the MQTT server
                if ( $mqtt->connect( true, null, $username, $password ) ) {
                    // we have a connection

                    // separate the most recent record ( it will be the first record in the $recent_logs since we are ordering
                    // by DESCending order)
                    $most_recent = $recent_logs[0];
                    // and the one before the most recent
                    $previous = $recent_logs[1];

                    // let's see if the weight contained in the $most_recent is less than the one
                    // in the $previous record
                    if ( $most_recent->weight < $previous->weight ) {
                        // the user lost weight
                        // so let's send a message to the MQTT server
                        $message = json_encode([ 'color' => "1", 'style' => "1", 'seconds' => "10", 'audio' => "1" ]);
                        $mqtt->publish( "blinkt", $message, 0 );


                        //$client = new GuzzleHttp\Client();
                        //$res = $client->request('GET', 'https://maker.ifttt.com/trigger/FitbitSuccess/with/key/bN-odmNsdoSsFqABIxPMBn');


                        // write a message to the log
                        self::__mqttLog( $user, 'Sending message to server (User lost weight!)' );
                    } else {
                        // the user did not lose weight
                        // sent the proper message to the MQTT server
                        $message = json_encode([ "color" => "1","style" => "2","seconds" => "10","audio" => "2" ]);
                        $mqtt->publish( "blinkt", $message, 0 );

                        // write a message to the log
                        self::__mqttLog( $user, 'Sending message to server (User did not lose weight)' );
                    }

                    // close the connection to the MQTT server
                    $mqtt->close();
                }
            } catch ( Exception $e ) {
                self::__mqttLog( null, 'There was an error: '.$e->getMessage() );
            }

        } else {
                // we could not connect to the server
                // write to the log
                self::__mqttLog( null, 'Could not connect to server' );
        }
    }

    /**
     * Write a message to a log file created specifically for the MQTT debug output
     *
     * @access private
     * @param User $user
     * @param boolean $status
     */
    private function __mqttLog( $user, $message )
    {
        // prepare the log message
        $now = Carbon::now()->toDateTimeString();
        $user_name = $user ? $user->name : '';
        $logMsg = "{$now}: $user_name {$message}";

        Storage::append( 'mqtt_log.txt', $logMsg );
    }

}
