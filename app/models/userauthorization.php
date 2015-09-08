<?php

namespace app\models;

class UserAuthorization extends \Illuminate\Database\Eloquent\Model {

	protected $fillable = array(
                'user_id',
                'api_id',
                'api_key',
        );

	public function user(){
		return $this->belongsTo('app\models\User');
	}

	public function scopeApiId($query, $apiId){
		return $query->where('api_id', $apiId);
	}

}

?>
