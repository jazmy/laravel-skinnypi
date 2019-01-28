<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use League\OAuth2\Client\Token\AccessToken;
use djchen\OAuth2\Client\Provider\Fitbit;
use Config;

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
        // the application authorize page should be password protected i.e. it should be available
        // only to logged in users since we will need to get the user id when Fitbit returns the user to us
        $this->middleware( 'auth', [ 'only' => [ 'getAuthorize', 'getDeauthorize' ] ]  );

		// create an instance of the Fitbit Provider
		$provider = new Fitbit([
			'clientId' => config('skinnypi.FITBIT_API_CLIENT_ID', null),
			'clientSecret' => config('skinnypi.FITBIT_API_CLIENT_SECRET', null),
			'redirectUri' => url("/authorize")
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
		$tokenRecord = DB::table('fitbit_accesstokens')->orderBy('id', 'DESC')->first();

		if ( ! $tokenRecord ) return redirect('/')->with('error', 'You are yet to authorize the application!');

		$access_token = $tokenRecord->access_token;

        try {
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
        } catch (Exception $e) {
            dd( $e->getMessage() );
        }

        $rJson = json_decode( $response->getBody()->getContents(), true );

        return dd( [ $rJson, $tokenRecord ] );
	}

    /**
     * Deauthorize a user access given to us
     *
     * @access public
     * @return Response
     */
    public function getDeauthorize()
    {
        // the user wants to revoke the access he or she gave us that allows us to be able to get notifications
        // when his or her weight changes on Fitbit
        // so we will revoke the access token from Fitbit
        // we will delete the access token that we stored for the user

        // get the currently logged in user
        $user = Auth::user();

        // get the current access_token for the user
        $accesstokenRecord = DB::table('fitbit_accesstokens')->where('user_id', $user->id)->first();

        // make sure we have the accessToken
        if ( $accesstokenRecord ) {
            // we have the access token
            // now build an League\OAuth2\Client\Token\AccessToken object with the $accesstokenRecord->access_token record
            // that we have for the user
            $existingAccessToken = new AccessToken(
                [
                    'access_token' => $accesstokenRecord->access_token,
                    'refresh_token' => $accesstokenRecord->refresh_token,
                    'resource_owner_id' => $accesstokenRecord->resource_owner_id,
                    'expires' => $accesstokenRecord->expires
                ]
            );

            $access_token = $accesstokenRecord->access_token;

            // for this user
            // check if the access token has expired
            if ( $existingAccessToken->hasExpired() ) {
                // the access token has expired
                // we will request for a new one using the refresh token that we have
                $newAccessToken = $this->provider->getAccessToken( 'refresh_token', [
                    'refresh_token' => $existingAccessToken->getRefreshToken()
                ]);

                // Save the new access token for the user
                DB::table('fitbit_accesstokens')->where('resource_owner_id', $accesstokenRecord->resource_owner_id)
                    ->update( [ 'access_token' => $newAccessToken->getToken() ] );

                $access_token = $newAccessToken->getToken();

                // overwrite the existing accessToken object with the new one
                $existingAccessToken = $newAccessToken;

            }

            // we will delete the subscription from Fitbit so that we no longer receive notifications
            // let's get the details from the api
            $response = ( new Client )->delete(
                Fitbit::BASE_FITBIT_API_URL . "/1/user/-/body/apiSubscriptions/{$accesstokenRecord->id}.json",
                array(
                    'headers' => [ 'Authorization' => "Bearer {$access_token}", ],
                )
            );

            $response_code = $response->getStatusCode();
            $deleteResponseJson = json_decode( $response->getBody()->getContents(), true );

            // if everything went well, we should receive a 204 status code with no response body
            if ( $response_code AND $response_code == 204 ) {
                // the subscription has been deleted
                // we should do well to revoke the access token as well
                // let's tell Fitbit to revoke the access token since we will not be needing it again
                $revokeResponse = $this->provider->revoke( $existingAccessToken );

                $response_code = $revokeResponse->getStatusCode();
                $body = json_decode( $revokeResponse->getBody()->getContents() );

                // there's no documentation for the response to expect, so as long as there was no error,
                // we will assume the request went well
                // so what is left is to delete the access token that we stored for the user
                $deleted = DB::table('fitbit_accesstokens')->where('user_id', $user->id)->delete();

                // send the user back to the dashboard
                return redirect( '/dashboard' )->with('success', 'Application Deauthorized!');

            } else {
                // the subscription was not deleted
                return redirect( '/dashboard' )->with( 'error', 'Unable to deauthorize application!' );
            }

        } else {
            // we do not have the access token
            // send the user back to the dashboard
            return redirect( '/dashboard' )
                ->with('error', 'No access token found! Perhaps you have not authorize this app before');
        }
    }

    /**
     * Send the user to fitbit to oAuth2 page to authorize our application
     *
     * @access public
     * @return Response
     */
    public function getAuthorize()
    {
        // before we do anything, let us verify that this user has not authorized us already
        // since we do not want him or her to try to authorize us again since Fitbit will just end up
        // sending the user back to us with the error that he or she can only authorize once
        // get the logged in user
        $user = Auth::user();

        // so let's get the current access_token we have for the user
        $existingAccessToken = DB::table('fitbit_accesstokens')->where('user_id', $user->id)->first();

        // check to see if we have the existingAccessToken
        if ( $existingAccessToken ) {
            // yes we have an existing access token record for this user.
            // this means we should not allow him or her to continue to FitBit since it will be
            // counterproductive and not really needed sort of
            // so send the user back to the dashboard
            return redirect( '/dashboard' )->with( 'warning', 'You have authorized this application already!' );
        }

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

    		return redirect( '/dashboard' )->with( 'error', "Invalid state provided! Please try again!" );
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

                // return dd( [ $response, $response_code, $body ] );

                if ( $response_code == 201 ) {
                	// the user has been subscribed and we will be able to get notifications when data changes for
                	// him or her on fitbit
                	return redirect( '/dashboard' )
                            ->with( 'success', "Subscription successfully created. Subscription ID: {$body->subscriptionId}, Subscriber ID: {$body->subscriberId} ");
                } else if ( $response_code == 200 ) {
                	// the user is already subscribed
                	return redirect( '/dashboard' )
                            ->with( 'info', "Subscription already exists!" );
                } else if ( $response_code == 405 ) {
                	// that was a wrong request
                	return redirect( '/dashboard' )->with( 'error', "Subscription already exists!" );
                } else if ( $response_code == 409 ) {
                	// there's a conflict
                	return redirect( '/dashboard' )->with( 'error', "You can only subscribe to a stream once." );
                }

    		} catch ( \League\OAuth2\Client\Provider\Exception\IdentityProviderException $e ) {
    			// failed to get the access token
    			return redirect( '/dashboard' )->with( 'error', $e->getMessage() );
    		} catch ( Exception $e ) {
    			// there was a general Exception
    			return redirect( '/dashboard' )->with( 'error', $e->getMessage() );
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
            // let's get the details of the current user so that we can associate
            // his or her access token with the user id of their users record we have
            // on file
            $user = Auth::user();

    		$accessArray = [
                'user_id' => $user->id,
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
     * This route is for the subscriber verification
     *
     * @access public
     * @return Response
     */
    public function getCallback()
    {
    	$verificationCode = '36131ae3c0433177287d702aab82527f64cdadebfe71d3bcf28d2792dc534100';

    	$code = $_GET['verify'];

    	if ( $code == $verificationCode ) {
    		return response( 'Ok', 204 );
    	} else {
    		return response( '404', 404 );
    	}
    }

}
