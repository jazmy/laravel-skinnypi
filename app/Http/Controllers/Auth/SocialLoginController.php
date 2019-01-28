<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use  Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

use App\Services\SocialAccountService;

class SocialLoginController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest', ['except' => 'logout']);
    }

    /**
     * Send the user to the provider's OAuth site
     *
     * @access public
     * @param string $provider the provider to redirect to
     * @return Response 
     */
    public function getRedirect($provider = null)
    {
    	if (empty($provider)) return redirect()->route('login')->with('error', 'Incomplete Request!');

    	return Socialite::driver($provider)->redirect();
    }

    /**
     * When the provider returns the user with a valid token
     *
     * @access public
     * @param SocialAccountService $service 
     * @param string $provider the provider we are working with
     * @return Response 
     */
    public function getCallback(SocialAccountService $service, $provider = null)
    {
    	// get the user from the provider and create relationships in the system
    	try {
         $user = $service->createOrGetUser(Socialite::driver($provider)->user(), $provider);

         if ($user) {
            // set the user as the currently logged in user
            auth()->login($user, true);

            return redirect()->to('/')->with('success', "Welcome back {$user->name} via [".ucfirst($provider)."]");
         } else return redirect()->route('login')->with('error', "Failed to login via [".ucfirst($provider)."]");   
        } catch (InvalidStateException $e) {
            return redirect()->route('login')->with('error', "Unable to log in with ".ucfirst($provider));
        }
    }

}
