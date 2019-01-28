<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

/**
 * Add authentication routes
 */
Auth::routes();

// social auth routes
Route::get('/social-auth/redirect/{provider}', 'Auth\SocialLoginController@getRedirect')->name('social.redirect');
Route::get('/social-auth/callback/{provider}', 'Auth\SocialLoginController@getCallback')->name('social.callback');

Route::get('/return', function() {
	// dd(json_encode(['ownerID' => 1]));
	dispatch( new App\Jobs\ProcessFitBitNotificationDetails( 1 ) );
});

/**
 * Display the dashboard that a logged in user sees
 */
Route::get('/dashboard', 'HomeController@getDashboard')->name('dashboard');

/**
 * Display the application Landing page
 */
Route::get('/', 'HomeController@getIndex');
Route::get('/lights', 'HomeController@triggerLights');
/**
 * Display the weight logs page for the user
 */
Route::get( '/my-weight-logs', 'HomeController@getMyWeightLogs' );

/**
 * Display the weight logs RSS page for the user
 */
Route::get( '/my-weight-logs/{userid}/rss', 'HomeController@myWeightLogsRss' );
Route::get( '/my-weight-logs/{userid}/my.xml', 'HomeController@myWeightLogsRss');

/**
 * Display the home page where we ask for authorization
 */
Route::get('/authorize', 'FitBitController@getAuthorize');

/**
 * Deauthorize the application
 */
Route::get('/de-authorize', 'FitBitController@getDeauthorize' );

/**
 * This is the route that FitBit will post to when there is a new notification
 */
Route::post('/callback', 'FitBitNotificationController@postCallback');

/**
 * This is the route that will fetch the details of the notifications that we received from fitbit
 * This is because fitbit does not send data as part of the notification. It only sends metadata
 * relating to the event in the notification and we will then use the metadata to fetch the full
 * event details
 */
Route::get('/notification-details', 'FitBitNotificationController@getNotificationDetails');

/**
 * This is a test route to simulate changing data in a user's fitbit account which
 * will trigger a notification and let us test the notification event
 */
Route::get('/post-weight', 'FitBitController@getPostWeight');

/**
 * This is the route for verifying a subscriber as part of the fitbit api setup process
 */
Route::get('/callback', 'FitBitController@getCallback');
