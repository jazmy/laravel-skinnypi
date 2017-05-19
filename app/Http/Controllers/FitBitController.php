<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use League\OAuth2\Client\Token\AccessToken;
use djchen\OAuth2\Client\Provider\Fitbit;

class FitBitController extends Controller
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
			'clientId' => env('FITBIT_API_CLIENT_ID', null),
			'clientSecret' => env('FITBIT_API_CLIENT_SECRET', null),
			'redirectUri' => url('/')
		]);

		// set the provider on the class to make it available throughout this class
		$this->provider = $provider;
	}

	/**
	 * Post weight data to fitbit so that we can test the notification behaviour
	 *
	 * @access public
	 * @return Response 
	 */
	public function getPostWeight()
	{
		$tokenRecord = DB::table('fitbit_accesstokens')->orderBy('id', 'ASC')->first();

		if ( ! $tokenRecord ) return redirect('/');

		$access_token = $tokenRecord->access_token;

        $response = ( new Client )->post(
        	Fitbit::BASE_FITBIT_API_URL . '/1/user/-/body/log/weight.json',
        	array(
        		'headers' => [ 'Authorization' => "Bearer {$access_token}", 'Content-Type' => 'application/x-www-form-urlencoded' ],
        		'form_params' => [ 
        			'weight' => empty( $_GET['w'] ) ? 65 : $_GET['w'], 
        			'date' => Carbon::now()->toDateString(), 
        			'time' => Carbon::now()->toTimeString() 
        		]
        	)
        );

        $rJson = json_decode( $response->getBody()->getContents(), true );

        return dd( [ $rJson, $tokenRecord ] );
	}

    /**
     * Display the page for fitbit authorization
     *
     * @access public
     * @return Response 
     */
    public function getIndex()
    {

    	// so we will check if we do not have a code at all 
    	// because if that is the case, then it means the user is just coming to the 
    	// application so we will redirect him or her to the Fitbit oAuth authorisation page
    	if ( empty( $_GET['code'] ) ) {
    		// let's get the authorization url from the oAuth provider 
    		// so that we can have a valid url that we will redirect the user to to authorize us
    		$authorizationUrl = $this->provider->getAuthorizationUrl();

    		// store up the oauth2state generated for us in the session so that we can check it 
    		// when the user returns to this page
    		session()->put( 'oauth2state', $this->provider->getState() );

    		// now send the user to the authorization url so that they can authorize our application
    		return redirect( $authorizationUrl );

    	} else if ( empty( $_GET['state'] ) || ( $_GET['state'] !== session('oauth2state') ) ) {
    		// the state that came back from the authorization page is not the same as the one that we got 
    		// originally. This is most likely a CSRF (Cross Site Request Forgery) Request 
    		// so we will terminate the request abruptly
    		session()->forget( 'oauth2state' );

    		return "Invalid state provided! Please try again!";
    	} else {
    		// the user has been redirected back to us from the authorization page and there is no discrepancy in the 
    		// state generated

    		// let's wrap everything in a try - catch block
    		try {
	    		// so we will try to get an access token using the authorization code grant
	    		$accessToken = $this->provider->getAccessToken('authorization_code', [
		            'code' => $_GET['code']
		        ]);

		        // let's save the access token
		        $user_record_id = self::__saveAccessToken( $accessToken );

		        // so we now have an accesstoken and we can then make requests on behalf of this user
		        // now we will make a request to create a subscription for this user
		        $request = $this->provider->getAuthenticatedRequest(
                    Fitbit::METHOD_POST,
                    Fitbit::BASE_FITBIT_API_URL . "/1/user/-/body/apiSubscriptions/{$user_record_id}.json",
                    $accessToken,
                    [ 
                    	'headers' => [ Fitbit::HEADER_ACCEPT_LANG => 'en_US'], [Fitbit::HEADER_ACCEPT_LOCALE => 'en_US'] 
                    ]
                );

                // get the response from the request made
                // $response = $this->provider->getParsedResponse( $request );
                $response = $this->provider->getResponse( $request );
                $response_code = $response->getStatusCode();
                $body = json_decode( $response->getBody()->getContents() );

                return dd( [ $response, $response_code, $body ] );

                if ( $response_code == 201 ) {
                	// the user has been subscribed and we will be able to get notifications when data changes for 
                	// him or her on fitbit
                	return "Subscription successfully created. Subscription ID: {$body->subscriptionId}, Subscriber ID: {$body->subscriberId} ";
                } else if ( $response_code == 200 ) {
                	// the user is already subscribed
                	return "Subscription already exists!";
                } else if ( $response_code == 405 ) {
                	// that was a wrong request
                	return "Wrong request made!";
                } else if ( $response_code == 409 ) {
                	// there's a conflict
                	return "You can only subscribe to a stream once.";
                }

    		} catch ( \League\OAuth2\Client\Provider\Exception\IdentityProviderException $e ) {
    			// failed to get the access token
    			return $e->getMessage();
    		} catch ( Exception $e ) {
    			// there was a general Exception
    			return $e->getMessage();
    		}
    	}
 
    }

    /**
     * Save the accessToken so that we can use it to make authenticated requests for the user in the future
     *
     * @access private
     * @return boolean 
     */
    private function __saveAccessToken( $accessToken )
    {
    	if ( $accessToken ) {
    		$accessArray = [
    			'access_token' => $accessToken->getToken(),
    			'refresh_token' => $accessToken->getRefreshToken(),
    			'resource_owner_id' => $accessToken->getResourceOwnerId(),
    			'expires' => $accessToken->getExpires()
    		];

    		// now that we have accessToken, we will store it in the database so that we can use it to 
    		// get a token anytime we want for this user
    		// first, we will check if the record exists
    		$exists = DB::table('fitbit_accesstokens')
    					->where('resource_owner_id', '=', $accessToken->getResourceOwnerId())->first();
    		if ( $exists ) {
    			// the record already exists...we can update it
    			DB::table('fitbit_accesstokens')->where('resource_owner_id', '=', $accessToken->getResourceOwnerId())
    				->update( $accessArray );
                return $exists->id;
    		} else {
    			// the record does not exist...we will create it
    			return DB::table('fitbit_accesstokens')->insertGetId( $accessArray );
    		}
    	}
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
    		Storage::append( "notification_log/fitbit_log_{$todayDate}.txt", $now." : ".json_encode( $notificationData ) );

    		// store the notification data in the database
    		DB::table('fitbit_data')->insert(
    			[
    				'data' => json_encode( $notificationData )
    			]
    		);
    	}

    	// so we have stored the notification data in the fitbit_log file as well as the fitbit_data table 
    	// in the sqlite database so we will return the response fitbit is expecting from us and then we will 
    	// get the full details of the notification later
    	
    	// return the 204 response so that the fitbit api can know that we have received 
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
        // DB::table('fitbit_data')->truncate();
    	// DB::table('fitbit_accesstokens')->truncate();
    	// return DB::table('fitbit_accesstokens')->get();
    	// so we will get the notification details in the database and then get the details for all of them
    	$notifications = DB::table('fitbit_data')->get();

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

    				// now that we have loaded the full notification details, we will delete this notification from the 
    				// database so that we will not process it again by mistake
    				DB::table('fitbit_data')->where('id', $notif->id)->delete();
    			} else {
    				// we got nothing from the api...we will just move on
    			}

    			// move on to the next record
    		}
    	}
    }

    /**
     * This route is for the subscriber verification
     *
     * @access public
     * @return Response 
     */
    public function getCallback()
    {
    	$verificationCode = '7253b5f37f94573004d95919af0a23f2a8dc050d7010043019ab483da75055ef';

    	$code = $_GET['verify'];

    	if ( $code == $verificationCode ) {
    		return response( 'Ok', 204 );
    	} else {
    		return response( '404', 404 );
    	}
    }

}
