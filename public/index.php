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

use \Slim\Middleware\HttpBasicAuthentication\AuthenticatorInterface;

$app->add(new \Slim\Middleware\HttpBasicAuthentication([
	"path" => "/api",
	"realm" => "Protected",
	"authenticator" => function ($arguments) use ($app) {
		$results = \app\models\UserAuthorization::apiId($arguments['user'])->get();
                if($results == null || count($results) == 0) return false;

                $userAuthorization = $results[0];
                if(!password_verify($arguments['password'], $userAuthorization->api_key)) return false;

                $app->user_id = $userAuthorization->user_id;

                return true;
	}
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

$app->add(new DatabaseConnector());

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

		// TODO: Send email for confirmation
		$pass = \app\utilities\PasswordUtility::GenerateSalt();
		$now = date("Y-m-d H:i:s");
		$hash = password_hash($data->Email.$now, PASSWORD_DEFAULT);

		$user = new \app\models\User(array(
                        'username'      => $data->Email,
                        'password'      => password_hash($pass, PASSWORD_DEFAULT),
                        'email'         => $data->Email,
			'confirmation'	=> $now
                ));

                $user->save();

		$apiId = \app\utilities\PasswordUtility::GenerateSalt();
		$apiKey = \app\utilities\PasswordUtility::GenerateSalt();

		$userAuthorization = new \app\models\UserAuthorization(array(
			'user_id' 	=> $user->id,
			'api_id'	=> $apiId,
			'api_key'	=> password_hash($apiKey, PASSWORD_DEFAULT)
		));

		$userAuthorization->save();

                echo json_encode(array('ResponseCode' => \config\Constants::$success, 'APIId' => $apiId, 'APIKey' => $apiKey));
        });
});

// API group
$app->group('/api', function () use ($app) {        	

	$app->get('/turbulencedata/:latitude/:longitude(/:radius(/:hoursUntilStale))', function ($latitude, $longitude, $radius = 1, $hoursUntilStale = 2) {
		$controller = new \app\controllers\TurbulenceStatisticController();
		$finalResults = $controller->getTurbulenceData($latitude, $longitude, $radius, $hoursUntilStale);
		echo json_encode(array('ResponseCode' => \config\Constants::$success, 'Results' => $finalResults));
	});

	$app->post('/turbulencestatistic', function() use ($app) {
		$json = $app->request->getBody();
		$data = json_decode($json, false);
		
		if(is_object($data)){

			$turbulenceStatistic = new \app\models\TurbulenceStatistic(array(
				'x_accel' 	=> $data->XAccel,
                        	'y_accel' 	=> $data->YAccel,
                        	'z_accel' 	=> $data->ZAccel,
                        	'altitude' 	=> $data->Altitude,
                        	'latitude' 	=> $data->Latitude,
                        	'longitude' 	=> $data->Longitude,
				'created' 	=> $data->Created,
				'group_id' 	=> $data->GroupId,
				'user_id'	=> $app->user_id
			));

			$turbulenceStatistic->save();
		
			echo json_encode(array('ResponseCode' => \config\Constants::$success));
			return;
		}

		echo json_encode(array('ResponseCode' => \config\Constants::$failure));
	});
});

$app->get('/', function () use ($app) {
	echo "Coming soon...";
});

$app->get('/support', function () use ($app) {
	echo "Please email support@flysmuthe.com";
});

$app->get('/privacy', function () use ($app) {
        echo "Defer to https://sovereignshare.com/privacy for now please.";
});

$app->run();

?>
