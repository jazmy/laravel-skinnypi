<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

use App\User;

class SocialAccount extends Model
{
    /**
    * The table used by this model
    *
    * @var string
    */
    protected $table = 'social_accounts';

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [
        'id', 'created_at', 'updated_at'
    ];

    /**
     * Social account belongs to a user
     *
     * @access public
     * @return Eloquent 
     */
    public function user()
    {
    	return $this->belongsTo( User::class );
    }

}
