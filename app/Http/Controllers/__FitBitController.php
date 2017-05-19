<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use File;
use djchen\OAuth2\Client\Provider\Fitbit;
use Carbon\Carbon;

class __FitBitController extends Controller
{
    //
public function index() {

    $provider = new Fitbit([
        'clientId'          => 'XXX',
        'clientSecret'      => 'XXX',
        'redirectUri'       => 'XXX'
    ]);

    // start the session
    session_start();

    // If we don't have an authorization code then get one
    if (!isset($_GET['code'])) {

        // Fetch the authorization URL from the provider; this returns the
        // urlAuthorize option and generates and applies any necessary parameters
        // (e.g. state).
        $authorizationUrl = $provider->getAuthorizationUrl();

        // Get the state generated for you and store it to the session.
        $_SESSION['oauth2state'] = $provider->getState();

        // Redirect the user to the authorization URL.
        header('Location: ' . $authorizationUrl);
        exit;

    // Check given state against previously stored one to mitigate CSRF attack
    } elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
        unset($_SESSION['oauth2state']);
        exit('Invalid state');

    } else {

        try {

            // Try to get an access token using the authorization code grant.
            $accessToken = $provider->getAccessToken('authorization_code', [
                'code' => $_GET['code']
            ]);

            // We have an access token, which we may use in authenticated
            // requests against the service provider's API.
            //echo $accessToken->getToken() . "\n";
          //  echo $accessToken->getRefreshToken() . "\n";
          //  echo $accessToken->getExpires() . "\n";
          //  echo ($accessToken->hasExpired() ? 'expired' : 'not expired') . "\n";

            // Using the access token, we may look up details about the
            // resource owner.
            $resourceOwner = $provider->getResourceOwner($accessToken);

           //var_export($resourceOwner->toArray());


            // The provider provides a way to get an authenticated API request for
            // the service, using the access token; it returns an object conforming
            // to Psr\Http\Message\RequestInterface.
            $request = $provider->getAuthenticatedRequest(
                Fitbit::METHOD_GET,
                Fitbit::BASE_FITBIT_API_URL . '/1/user/-/body/log/weight/date/2017-04-27/1m.json',

                $accessToken,
                ['headers' => [Fitbit::HEADER_ACCEPT_LANG => 'en_US'], [Fitbit::HEADER_ACCEPT_LOCALE => 'en_US']]
                // Fitbit uses the Accept-Language for setting the unit system used
                // and setting Accept-Locale will return a translated response if available.
                // https://dev.fitbit.com/docs/basics/#localization
            );
            // Make the authenticated API request and get the parsed response.
            $response = $provider->getParsedResponse($request);
            var_dump($response);

//Previous weight
$previousitem =array_values(array_slice($response['weight'], -2))[0];
$previousweight = $previousitem['weight'];
echo $previousweight;

echo "<br>";

//Most Recent Weight
$currentitem =array_values(array_slice($response['weight'], -1))[0];
$currentweight = $currentitem['weight'];
echo $currentweight;
echo "<br>";

if ($currentweight < $previousweight) { echo "YAYYYYYY!";}

//  var_dump($secondlastitem);

 //var_dump($lastitem);
//echo $weight ;
            // If you would like to get the response headers in addition to the response body, use:
            //$response = $provider->getResponse($request);
            //$headers = $response->getHeaders();
            //$parsedResponse = $provider->parseResponse($response);

        } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {

            // Failed to get the access token or user details.
            exit($e->getMessage());

        }

    }
  }

public function fitbitfileupdate () {
$current_time = Carbon::now()->toDayDateTimeString();
  $filename = 'tmp/log.txt';
  $content = "\n" . "CallBack Successful " . $current_time;
  $bytesWritten = File::append($filename, $content);
  if ($bytesWritten === false)
   {
       die("Couldn't write to the file.");
   }


/*
   $headers = $this->getHeaders();
          $userHeaders = array();
          if ($subscriberId)
              $userHeaders['X-Fitbit-Subscriber-Id'] = $subscriberId;
          $headers = array_merge($headers, $userHeaders);
          if (isset($path))
              $path = '/' . $path;
          else
              $path = '';
          try {
              $this->oauth->fetch($this->baseApiUrl . "user/-" . $path . "/apiSubscriptions/" . $id . "." . $this->responseFormat, null, OAUTH_HTTP_METHOD_POST, $headers);
          } catch (Exception $E) {
          }
          $response = $this->oauth->getLastResponse();
          $responseInfo = $this->oauth->getLastResponseInfo();
          if (!strcmp($responseInfo['http_code'], '200') || !strcmp($responseInfo['http_code'], '201')) {
              $response = $this->parseResponse($response);
              if ($response)
                  return $response;
              else
                  throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
          } else {
              throw new FitBitException($responseInfo['http_code'], 'Fitbit request failed. Code: ' . $responseInfo['http_code']);
          }
*/
  }

/*
  private function getHeaders(Request $request)
     {
         $headers = array();
         $headers['User-Agent'] = $request->header('User-Agent');
         if ($this->metric == 1) {
             $headers['Accept-Language'] = 'en_US';
         } else if ($this->metric == 2) {
             $headers['Accept-Language'] = 'en_GB';
         }
         return $headers;
     }
*/


}
