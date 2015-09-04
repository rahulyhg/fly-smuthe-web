<?php 

require('../vendor/autoload.php'); 
require('../bootstrapper.php');

Bootstrapper::run();

$app = new \Slim\Slim(array(
	'cookies.encrypt' 			=> true,
	'cookies.lifetime' 			=> '20 minutes',
	'cookies.path' 				=> '/',
	'cookies.domain' 			=> 'flysmuthe.com',
	'cookies.secure'		 	=> false,
	//'cookies.secure' 			=> true,
	'cookies.httponly'		 	=> true,
	'cookies.secret_key' 			=> \config\SecureConfig::$cookieEncryptionKey,
	'cookies.cipher' 			=> MCRYPT_RIJNDAEL_256,
	'cookies.cipher_mode' 			=> MCRYPT_MODE_CBC,
	'http.version' 				=> '1.1',
	'mode' 					=> 'development'
));

class DatabaseConnector extends \Slim\Middleware {
        public function call() {
                $container = new Illuminate\Container\Container;
                $connFactory = new \Illuminate\Database\Connectors\ConnectionFactory($container);
                $conn = $connFactory->make(array(
                        'driver'                => 'mysql',
                        'host'                  => '127.0.0.1',
                        'database'              => 'flysmuthe',
                        'username'              => \config\SecureConfig::$dbUsername,
                        'password'              => \config\SecureConfig::$dbPassword,
                        'charset'               => 'utf8',
                        'collation'             => 'utf8_general_ci',
                        'prefix'                => ''
                ));
                $resolver = new \Illuminate\Database\ConnectionResolver();
                $resolver->addConnection('default', $conn);
                $resolver->setDefaultConnection('default');
                \Illuminate\Database\Eloquent\Model::setConnectionResolver($resolver);
        	
		//Call next middleware
        	$this->next->call();
	}
}

$app->add(new DatabaseConnector());

use \Slim\Middleware\HttpBasicAuthentication\AuthenticatorInterface;

class APIAuthenticator implements AuthenticatorInterface {
	public function __invoke(array $arguments) {
		return true;
	}
}

$app->add(new \Slim\Middleware\HttpBasicAuthentication([
	"path" => "/api",
	"realm" => "Protected",
	"authenticator" => new APIAuthenticator()
]));

class GatewayAuthenticator implements AuthenticatorInterface {
	public function __invoke(array $arguments) {
		return $arguments['user'] == \config\SecureConfig::$apiId && $arguments['password'] == \config\SecureConfig::$apiSecret;
	}
}

$app->add(new \Slim\Middleware\HttpBasicAuthentication([
	"path" => "/gateway",
	"realm" => "Protected",
	"authenticator" => new GatewayAuthenticator()
]));

// Only invoked if mode is "production"
$app->configureMode('production', function () use ($app) {
	$app->config(array(
        	'log.enable' => true,
		'log.level' => \Slim\Log::ERROR,
        	'debug' => false
	));
});

// Only invoked if mode is "development"
$app->configureMode('development', function () use ($app) {
	$app->config(array(
        	'log.enable' => true,
		'log.level' => \Slim\Log::DEBUG,
        	'debug' => true
	));
});

$app->group('/gateway', function() use ($app) {
	$app->post('/register', function() use ($app) {
                $json = $app->request->getBody();
                $data = json_decode($json, false);
        });
});

// API group
$app->group('/api', function () use ($app) {        	

	$app->get('/turbulencedata/:latitude/:longitude(/:radius)', function ($latitude, $longitude, $radius = 1) {
		$results = \app\models\TurbulenceStatistic::notStale()
				->regionalDataFromCoordinatesForRadius($latitude, $longitude, $radius)
				->orderBy('altitude', 'asc')
				->orderBy('group_id', 'asc')
				->orderBy('created', 'asc')
				->get();
		
		$currentAltitude = 0;
		$currentAccelTotal = 0.0;
		$currentSeconds = 0;
		$currentGroupId = "";
		$firstTime = null;
		$lastTime = null;
		$previousCumulativeAccel = 0.0;

		$finalResults = array();
		
		// $avgCounter will be incremented per altitude
		$avgCounter = 0;

		// $groupCounter will be reset each group
		$groupCounter = 0;		

		// $x will count the whole loop
		$x = 0;
		foreach($results as $result){
			// If this is the first iteration, initialize vars
			if($x == 0) {
				$currentAltitude = $result->altitude;
				$currentGroupId = $result->group_id;
				$firstTime = strtotime($result->created);
			}

			if($currentGroupId != $result->group_id) {
				$lastTime = strtotime($result->created);
				
				$groupCounter = 0;
				$currentGroupId = $result->group_id;
			}

			if($groupCounter == 1 && $x > 1) {
                                $diff = abs(($lastTime-$firstTime) / 60);
                                $accelPerMin = $currentAccelTotal / $diff;

				$firstTime = strtotime($result->created);
			}

			// If we are changing altitudes OR this is the last result (in the case of the last altitude)
			if($currentAltitude != $result->altitude || count($results) == ($x + 1)){
				// Calculate average intensity and density for this altitude              
				$avg = $currentAccelTotal / $avgCounter;
				$finalResults[] = array(
					'Altitude' => $currentAltitude, 
					'AverageIntensity' => $avg, 
					'Description' => \app\models\TurbulenceStatistic::getTurbulenceDescription($avg));  

				// Set next altitude to current	
				$currentAltitude = $result->altitude;
				
				// Reset state vars
				$currentSeconds = 0.0;
				$currentAccelTotal = 0.0;
				$previousCumulativeAccel = 0.0;
				$avgCounter = 0;
			}
		
			// Combine all axis, remove gravity
			$cumulativeAccel = abs($result->x_accel + $result->y_accel + $result->z_accel) - 1;

			// If first delta calculation, set this to the current cumulative accel value
			if($previousCumulativeAccel == 0.0) $previousCumulativeAccel = $cumulativeAccel;

			// Add the abs delta to the currentAccelTotal
			$currentAccelTotal += abs($previousCumulativeAccel - $cumulativeAccel);

			// Set the previousCumulativeAccel for the next delta calculation
			$previousCumulativeAccel = $cumulativeAccel;			

			// Increment the counters
			$avgCounter++;
			$groupCounter++;
			$x++;
		}
		print_r($finalResults);
	});

	$app->post('/turbulencestatistic', function() use ($app) {
		$json = $app->request->getBody();
		$data = json_decode($json, false);

		$turbulenceStatistic = new \app\models\TurbulenceStatistic(array(
			'x_accel' 	=> $data->XAccel,
                        'y_accel' 	=> $data->YAccel,
                        'z_accel' 	=> $data->ZAccel,
                        'altitude' 	=> $data->Altitude,
                        'latitude' 	=> $data->Latitude,
                        'longitude' 	=> $data->Longitude,
			'created' 	=> $data->Created,
			'group_id' 	=> $data->GroupId
		));

		$turbulenceStatistic->save();
		
		echo json_encode(array('ResponseCode' => \config\Constants::$success));
	});
});

$app->run();

?>
