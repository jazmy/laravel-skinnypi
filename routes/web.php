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
 * Display the home page where we ask for authorization
 */
Route::get('/', 'FitBitController@getIndex');


/**
 * This is the route that FitBit will post to when there is a new notification
 */
Route::post('/callback', 'FitBitController@postCallback');

/**
 * This is the route that will fetch the details of the notifications that we received from fitbit 
 * This is because fitbit does not send data as part of the notification. It only sends metadata 
 * relating to the event in the notification and we will then use the metadata to fetch the full 
 * event details
 */
Route::get('/notification-details', 'FitBitController@getNotificationDetails');

/**
 * This is a test route to simulate changing data in a user's fitbit account which 
 * will trigger a notification and let us test the notification event
 */
Route::get('/post-weight', 'FitBitController@getPostWeight');

/**
 * This is the route for verifying a subscriber as part of the fitbit api setup process
 */
Route::get('/callback', 'FitBitController@getCallback');

Route::get('/fitbitfileupdate', 'FitBitController@fitbitfileupdate');

Route::get('/mqttsubscribe', 'FitBitController@mqttSubscribe');

Route::get('/mqttpublish', 'FitBitController@mqttPublish');

Auth::routes();

Route::get('/home', 'HomeController@index')->name('home');
