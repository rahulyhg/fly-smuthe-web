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
				->orderBy('created', 'asc')
				->get();
		
		$currentAltitude = 0;
		$currentAccelTotal = 0.0;
		$currentSeconds = 0;
		$previousCumulativeAccel = 0.0;

		$finalResults = array();
		
		$i = 0;
		foreach($results as $result){
			if($currentAltitude != $result->altitude){
				if($i > 0){
					// Calculate average intensity and density for this altitude              
                                	$finalResults[] = 
						array('Altitude' => $currentAltitude, 'AverageIntensity' => $currentAccelTotal / $i);  
				}

				// Set next altitude to current	
				$currentAltitude = $result->altitude;
				
				// Reset state vars
				$currentSeconds = 0.0;
				$currentAccelTotal = 0.0;
				$previousCumulativeAccel = 0.0;
				$i = 0;
			}
		
			// Combine all axis
			$cumulativeAccel = $result->x_accel + $result->y_accel + $result->z_accel;

			// If first delta calculation, set this to the current cumulative accel value
			if($previousCumulativeAccel == 0.0) $previousCumulativeAccel = $cumulativeAccel;

			// Add the abs delta to the currentAccelTotal
			$currentAccelTotal += abs($previousCumulativeAccel - $cumulativeAccel);

			// Set the previousCumulativeAccel for the next delta calculation
			$previousCumulativeAccel = $cumulativeAccel;			

			// Increment the counter
			$i++;
		}
		print_r($finalResults);
	});

	$app->post('/turbulencestatistic', function() use ($app) {
		$json = $app->request->getBody();
		$data = json_decode($json, false);

		$turbulenceStatistic = new \app\models\TurbulenceStatistic(array(
			'x_accel' => $data->XAccel,
                        'y_accel' => $data->YAccel,
                        'z_accel' => $data->ZAccel,
                        'altitude' => $data->Altitude,
                        'latitude' => $data->Latitude,
                        'longitude' => $data->Longitude,
			'created' => $data->Created
		));

		$turbulenceStatistic->save();
		
		echo json_encode(array('ResponseCode' => \config\Constants::$success));
	});
});

$app->run();

?>
