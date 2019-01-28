<?php

/**
 * @author: Peter Harris
 * @email: peteharris401@gmail.com
 * @Date:   2017-01-06 14:12:19
 * @Last Modified time: 2017-05-29 19:44:00
 * @path: /var/www/html/fiverr/jazzerup/app/Services/SocialAccountService.php
 */

namespace App\Services;

use Laravel\Socialite\Contracts\User as ProviderUser;

use App\User;
use App\Model\SocialAccount;

class SocialAccountService {

	/**
	 * Get a user authenticated via a social provider or 
	 * create the user if record does not exist
	 *
	 * @access public
	 * @param ProviderUser $providerUser
	 * @param string $provider the provider we want to check against
	 * @return User | boolean
	 */
	public function createOrGetUser(ProviderUser $providerUser, $provider = null)
	{
		if (empty($provider)) return false;

		$account = SocialAccount::where('provider', $provider)->where('provider_user_id', $providerUser->getId())->first();

		if ($account) {
			// the account exists, return it
			return $account->user;
		} else {
			// the account does not exist, let's create it
			// create an instance of SocialAccount
			$account = new SocialAccount;
			$account->provider_user_id = $providerUser->getId();
			$account->provider = $provider;

			// now try to get the user record for the user
			$user = User::where('email', $providerUser->getEmail())->first();

			app('db')->beginTransaction();

			// check if the user exists or not
			if (! $user) {
				// this user does not exist
				// let's create it
				// $nameArr = explode( ' ', $providerUser->getName() );

				// $user = User::updateOrCreate(
				// 	[ 'email' => $providerUser->getEmail() ], 
				// 	[ 
				// 		'password' => bcrypt($data['password'], 'name' => $providerUser->getName(), 
				// 		'pic_url' => $providerUser->getAvatar(), 'is_social' => 1
				// 	]
				// );

				$user = User::create([
					'email' => $providerUser->getEmail(),
					'name' => $providerUser->getName(),
					'pic_url' => $providerUser->getAvatar(),
					'is_social' => 1,
				]);
			}

			// set the relationship between the user and the social account
			$account->user()->associate($user);
			$account->save();

			// now return the user
			app('db')->commit();

			return $user;
		}
	}

}