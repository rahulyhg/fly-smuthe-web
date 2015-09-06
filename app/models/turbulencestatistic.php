<?php

namespace app\models;

class TurbulenceStatistic extends \Illuminate\Database\Eloquent\Model {

	const EarthRadiusInNauticalMiles = 3440.28;

	const HoursUntilStale = 3;

	const SmoothLimit = 0.025;

	const SmoothIntensityRating = 1;

	const LightTurbulenceLimit = 0.045;

	const LightIntensityRating = 2;

	const ModerateTurbulenceLimit = 0.080;

	const ModerateIntensityRating = 3;

	const SevereTurbulenceLimit = 0.15;

	const SevereIntensityRating = 4;

	const ExtremeTurbulenceLimit = 0.50;

	const ExtremeIntensityRating = 5;

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

	public static function getIntensityRating($avgTurbulenceG){
                if($avgTurbulenceG <= TurbulenceStatistic::SmoothLimit) return TurbulenceStatistic::SmoothIntensityRating;

                if($avgTurbulenceG <= TurbulenceStatistic::LightTurbulenceLimit) return TurbulenceStatistic::LightIntensityRating; 

                if($avgTurbulenceG <= TurbulenceStatistic::ModerateTurbulenceLimit) return TurbulenceStatistic::ModerateIntensityRating;

                if($avgTurbulenceG <= TurbulenceStatistic::SevereTurbulenceLimit) return TurbulenceStatistic::SevereIntensityRating;

                if($avgTurbulenceG <= TurbulenceStatistic::ExtremeTurbulenceLimit) return TurbulenceStatistic::ExtremeIntensityRating; 
        }

	public static function wasBump($accel) {
		return $accel > TurbulenceStatistic::SmoothLimit;
	}
}

?>
