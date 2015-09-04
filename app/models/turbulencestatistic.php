<?php

namespace app\models;

class TurbulenceStatistic extends \Illuminate\Database\Eloquent\Model {

	const EarthRadiusInNauticalMiles = 3440.28;

	const HoursUntilStale = 3;

	const SmoothLimit = 0.020;

	const LightTurbulenceLimit = 0.040;

	const ModerateTurbulenceLimit = 0.080;

	const SevereTurbulenceLimit = 0.15;

	const ExtremeTurbulenceLimit = 0.50;

	public $timestamps = false;

	protected $fillable = array(
		'x_accel',
                'y_accel',
                'z_accel',
                'altitude',
		'latitude',
                'longitude',
                'created',
		'group_id'
	);

	public function scopeNotStale($query){
		date_default_timezone_set("UTC");
		$staleDate = date("Y-m-d H:i:s", time() - (60 * 60 * TurbulenceStatistic::HoursUntilStale));
		return $query->where('created', '>=', $staleDate);
	}

	public function scopeRegionalDataFromCoordinatesForRadius($query, $latitude, $longitude, $radiusInNauticalMiles){
		return $query->whereRaw("(".TurbulenceStatistic::EarthRadiusInNauticalMiles." * acos(cos( radians( ? ) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians( ? ) ) + sin(radians(?)) * sin(radians(latitude)))) < ?", array([$latitude], [$longitude], [$latitude], [$radiusInNauticalMiles]));
	}

	public static function getTurbulenceDescription($avgTurbulenceG){
		if($avgTurbulenceG <= TurbulenceStatistic::SmoothLimit) return "Smooth";

		if($avgTurbulenceG <= TurbulenceStatistic::LightTurbulenceLimit) return "Light";

		if($avgTurbulenceG <= TurbulenceStatistic::ModerateTurbulenceLimit) return "Moderate";

		if($avgTurbulenceG <= TurbulenceStatistic::SevereTurbulenceLimit) return "Severe";

		if($avgTurbulenceG <= TurbulenceStatistic::ExtremeTurbulenceLimit) return "Extreme";
	}

	public static function wasBump($accel) {
		return $accel >= TurbulenceStatistic::SmoothLimit;
	}
}

?>
