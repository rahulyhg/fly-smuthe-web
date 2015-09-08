<?php

namespace app\models;

class User extends \Illuminate\Database\Eloquent\Model {

	protected $fillable = array(
                'username',
                'password',
                'email',
                'email_verified',
                'confirmation',
                'two_factor_secret',
                'password_reset_id',
        );

	public function authorizations(){
		return $this->hasMany('\app\models\UserAuthorization');
	}
}

?>
