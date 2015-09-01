<?php

namespace app\models;

class TurbulenceStatistic extends \Illuminate\Database\Eloquent\Model {

	const EarthRadiusInNauticalMiles = 3440.28;

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

	function scopeRegionalDataFromCoordinatesForRadius($query, $latitude, $longitude, $radiusInNauticalMiles){
		$query->whereRaw("(".TurbulenceStatistic::EarthRadiusInNauticalMiles." * acos(cos( radians( ? ) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians( ? ) ) + sin(radians(?)) * sin(radians(latitude)))) < ?", array([$latitude], [$longitude], [$latitude], [$radiusInNauticalMiles]));
	}
}

?>
