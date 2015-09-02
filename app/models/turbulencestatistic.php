<?php

namespace app\models;

class TurbulenceStatistic extends \Illuminate\Database\Eloquent\Model {

	const EarthRadiusInNauticalMiles = 3440.28;

	const HoursUntilStale = 2;

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

	public function scopeNotStale($query){
		date_default_timezone_set("UTC");
		$staleDate = date("Y-m-d H:i:s", time() - (60 * 60 * TurbulenceStatistic::HoursUntilStale));
		error_log($staleDate);
		return $query->where('created', '>=', $staleDate);
	}

	public function scopeRegionalDataFromCoordinatesForRadius($query, $latitude, $longitude, $radiusInNauticalMiles){
		return $query->whereRaw("(".TurbulenceStatistic::EarthRadiusInNauticalMiles." * acos(cos( radians( ? ) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians( ? ) ) + sin(radians(?)) * sin(radians(latitude)))) < ?", array([$latitude], [$longitude], [$latitude], [$radiusInNauticalMiles]));
	}
}

?>
