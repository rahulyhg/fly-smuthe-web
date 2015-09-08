<?php

namespace app\controllers;

class TurbulenceStatisticController {

	public function getTurbulenceData($latitude, $longitude, $radius, $hoursUntilStale){
		$results = \app\models\TurbulenceStatistic::notStale($hoursUntilStale)
                                ->regionalDataFromCoordinatesForRadius($latitude, $longitude, $radius)
                                ->orderBy('altitude', 'asc')
                                ->orderBy('group_id', 'asc')
                                ->orderBy('created', 'asc')
                                ->get();

		$accelProcessor = new \app\processors\AccelerometerProcessor();

                $currentAltitude = 0;
                $currentAccelTotal = 0.0;
                $currentSeconds = 0;
                $currentGroupId = "";
                $firstTime = null;
                $lastTime = null;
                $totalTime = 0.0;
                $previousCumulativeAccel = 0.0;

                $finalResults = array();

                // $avgCounter will be incremented per altitude
                $avgCounter = 0;

                // $groupCounter will be reset each group
                $groupCounter = 0;

                // $bumpCounter counts number of bumps
                $bumpCounter = 0;

                // $x will count the whole loop
                $x = 0;
                foreach($results as $result){
                        // If this is the first iteration, initialize vars
                        if($x == 0) {
                                $currentAltitude = $result->altitude;
                                $currentGroupId = $result->group_id;
                                $firstTime = strtotime($result->created);
                        }

			// If this is the last $result from this group, or we are changing altitude
                        if($currentGroupId != $result->group_id || $currentAltitude != $result->altitude || count($results) == ($x + 1)) {
                                // Set the last time
                                $lastTime = strtotime($result->created);

				// Time difference in minutes
                                $diff = abs(($lastTime-$firstTime) / 60);

                                $totalTime += $diff;

                                // Reset the group counter so we can catch the first $result of the next group
                                // when $groupCounter == 1
                                $groupCounter = 0;
                                $currentGroupId = $result->group_id;

                        	// Group has changed, reset the high pass filter vars
                                $accelProcessor->reset();
			}

                        // If this is the first $result of the group and we are past the very first overall iteration
                        if(($groupCounter == 1 && $x > 1) || count($results) == ($x + 1)) {
                                // Set the $firstTime from this new group
                                $firstTime = strtotime($result->created);
                        }

                        // If we are changing altitudes OR this is the last result (in the case of the last altitude)
                        if($currentAltitude != $result->altitude || count($results) == ($x + 1)){
                                // Calculate average intensity and density for this altitude              
                                $avg = $avgCounter > 0 ? $currentAccelTotal / $avgCounter : 0;
                                $finalResults[] = array(
                                        'Altitude' => $currentAltitude,
                                        'AverageIntensity' => $avg,
                                        'Bumps' => $bumpCounter,
                                        'Minutes' => $totalTime,
                                        'BumpsPerMinute' => $totalTime > 0 ? $bumpCounter / $totalTime : 0,
                                        'Description' => \app\models\TurbulenceStatistic::getTurbulenceDescription($avg),
					'IntensityRating' => \app\models\TurbulenceStatistic::getIntensityRating($avg),
					'Radius' => $radius,
					'Accuracy' => $avgCounter);

                                // Set next altitude to current 
                                $currentAltitude = $result->altitude;

                                // Reset state vars
                                $currentSeconds = 0.0;
                                $currentAccelTotal = 0.0;
                                $previousCumulativeAccel = 0.0;
                                $avgCounter = 0;
                                $totalTime = 0;
                                $bumpCounter = 0;
                        }

                        // High pass filtered values
			$accelProcessor->highPass($result->x_accel, $result->y_accel, $result->z_accel);

			// Combine all axis, remove gravity
                        $cumulativeAccel = abs($accelProcessor->rollingX + $accelProcessor->rollingY + $accelProcessor->rollingZ);

			if($avgCounter > 0){
				// If first delta calculation, set this to the current cumulative accel value
                        	if($previousCumulativeAccel == 0.0) $previousCumulativeAccel = $cumulativeAccel;

                        	// Add the abs delta to the currentAccelTotal
                        	$accelDelta = abs($previousCumulativeAccel - $cumulativeAccel);

                        	if(\app\models\TurbulenceStatistic::wasBump($accelDelta)){
                                	$bumpCounter++;
                        	}

                        	$currentAccelTotal += $accelDelta;
			}

                        // Set the previousCumulativeAccel for the next delta calculation
                        $previousCumulativeAccel = $cumulativeAccel;

                        // Increment the counters
                        $avgCounter++;
                        $groupCounter++;
                        $x++;
		}
		return $finalResults;
	}
}

?>
