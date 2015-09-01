<?php

namespace app\models;

class TurbulenceStatistic extends \Illuminate\Database\Eloquent\Model {

	public $timestamps = false;

	protected $fillable = array(
		'x_accel',
                'y_accel',
                'z_accel',
                'altitude',
		'latitude',
                'longitude',
                'created'
	);
}

?>
