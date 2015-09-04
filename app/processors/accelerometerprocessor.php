<?php

namespace app\processors;

class AccelerometerProcessor {
	
	// High pass filter vars
        public $rollingX;
	public $rollingY;
	public $rollingZ;
        
	const kFilteringFactor = 0.1;

	public function __construct(){
		$this->reset();
	}

	public function highPass($xAccel, $yAccel, $zAccel) {
		// High pass filtered values
		$this->rollingX = ($xAccel * AccelerometerProcessor::kFilteringFactor) + ($this->rollingX * (1.0 - AccelerometerProcessor::kFilteringFactor));

                $this->rollingY = ($yAccel * AccelerometerProcessor::kFilteringFactor) + ($this->rollingY * (1.0 - AccelerometerProcessor::kFilteringFactor));

                $this->rollingZ = ($zAccel * AccelerometerProcessor::kFilteringFactor) + ($this->rollingZ * (1.0 - AccelerometerProcessor::kFilteringFactor));
	}

	public function reset() {
		$this->rollingX = $this->rollingY = $this->rollingZ = 0;
	}
}

?>
