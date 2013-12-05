<?php
use Phalcon\Mvc\Micro\Collection as MicroCollection;

use Phalcon\Logger,
    Phalcon\Events\Manager as EventsManager,
    Phalcon\Logger\Adapter\File as FileLogger,
    Phalcon\Cache\Backend\Memcached;

error_reporting(E_ALL);

try {

    define('MAD_MAXMIND_TYPE', 'PHPSOURCE'); // Change to 'NATIVE' if you installed the GeoIP PHP Module (http://www.php.net/manual/en/book.geoip.php) -> Faster! - Please note that mAdserve will crash if this option is enabled but not installed.
    define('MAD_MAXMIND_DATAFILE_LOCATION', __DIR__ . '/../app/data/geotargeting/GeoLiteCity.dat');
    define('MAD_IGNORE_DAILYLIMIT_NOCRON', false); // Ignore a campaign's daily impression limit when the mAdserve cron was not executed for more than 24 hours.

    define('MAD_ADSERVING_PROTOCOL', 'http://');
    define('MAD_CLICK_HANDLER', 'v1/mdclick');
    define('MAD_SERVER_HOST', 'localhost/advapi');//adkey.joyplus.tv
    define('MAD_TRACK_HANDLER', 'v1/mdtrack');
    define('MAD_CLICK_ALWAYS_EXTERNAL', false);
    define('MAD_TRACK_UNIQUE_CLICKS', false); // Track only unique clicks. Works only if a caching method is enabled.
    define('MAD_CLICK_IMMEDIATE_REDIRECT', false); // Make the click handler redirect the end-user to the destination URL immediately and write the click to the statistic database in the background.
    define('CACHE_PREFIX', 'ADV_ZH');
    define('MAD_MAINTENANCE', false); //设置true停止广告投放


    /**
	 * Read the configuration
	 */
	$config = new Phalcon\Config\Adapter\Ini(__DIR__ . '/../app/config/config.ini');

	$loader = new \Phalcon\Loader();

	/**
	 * We're a registering a set of directories taken from the configuration file
	 */
	$loader->registerDirs(
		array(
			__DIR__ . $config->application->controllersDir,
			__DIR__ . $config->application->pluginsDir,
            __DIR__ . $config->application->responseDir,
            __DIR__ . $config->application->exceptionDir,
			__DIR__ . $config->application->modelsDir,
		)
	)->register();

	/**
	 * The FactoryDefault Dependency Injector automatically register the right services providing a full stack framework
	 */
	$di = new \Phalcon\DI\FactoryDefault();

	/**
	 * We register the events manager
	 */
//	$di->set('dispatcher', function() use ($di) {
//
//		$eventsManager = $di->getShared('eventsManager');
//
//		$security = new Security($di);
//
//		/**
//		 * We listen for events in the dispatcher using the Security plugin
//		 */
//		$eventsManager->attach('dispatch', $security);
//
//		$dispatcher = new Phalcon\Mvc\Dispatcher();
//		$dispatcher->setEventsManager($eventsManager);
//
//		return $dispatcher;
//	});

	/**
	 * The URL component is used to generate all kind of urls in the application
	 */
	$di->set('url', function() use ($config){
		$url = new \Phalcon\Mvc\Url();
		$url->setBaseUri($config->application->baseUri);
		return $url;
	});


//	$di->set('view', function() use ($config) {
//
//		$view = new \Phalcon\Mvc\View();
//
//		$view->setViewsDir(__DIR__ . $config->application->viewsDir);
//
//		$view->registerEngines(array(
//			".volt" => 'volt'
//		));
//
//		return $view;
//	});

	/**
	 * Setting up volt
	 */
//	$di->set('volt', function($view, $di) {
//
//		$volt = new \Phalcon\Mvc\View\Engine\Volt($view, $di);
//
//		$volt->setOptions(array(
//			"compiledPath" => "../cache/volt/"
//		));
//
//		return $volt;
//	}, true);

	/**
	 * Database connection is created based in the parameters defined in the configuration file
	 */
//	$di->set('db', function() use ($config) {
//		return new \Phalcon\Db\Adapter\Pdo\Mysql(array(
//			"host" => $config->database->host,
//			"username" => $config->database->username,
//			"password" => $config->database->password,
//			"dbname" => $config->database->name
//		));
//	});

    $di->set('db', function() use ($config) {

        $eventsManager = new EventsManager();

        $logger = new FileLogger("../app/logs/sql.log");

//Listen all the database events
        $eventsManager->attach('db', function($event, $connection) use ($logger) {
            if ($event->getType() == 'beforeQuery') {
                $logger->log($connection->getSQLStatement(), Logger::INFO);
            }
        });

        $connection = new \Phalcon\Db\Adapter\Pdo\Mysql(array(
            "host" => $config->database->host,
        	"port" => $config->database->port,
            "username" => $config->database->username,
            "password" => $config->database->password,
            "dbname" => $config->database->name,
        	"charset" => $config->database->charset
        ));

        //Assign the eventsManager to the db adapter instance
        $connection->setEventsManager($eventsManager);

        return $connection;
    });

    if ($config->logger->enabled) {
        $di->set('logger', function () use ($config) {

            $logger = new FileLogger("../app/logs/main.log");
            $formatter = new \Phalcon\Logger\Formatter\Line($config->logger->format);
            $logger->setFormatter($formatter);
            return $logger;
        });
    } else {
        $di->set('logger', function () use ($config) {
            $logger = new \Phalcon\Logger\Adapter\Syslog("ADVAPI", array(
                'option' => LOG_NDELAY,
                'facility' => LOG_DAEMON
            ));
            return $logger;
        });
    }

	/**
	 * If the configuration specify the use of metadata adapter use it or use memory otherwise
	 */
	$di->set('modelsMetadata', function() use ($config) {
		if (isset($config->models->metadata)) {
			$metaDataConfig = $config->models->metadata;
			$metadataAdapter = 'Phalcon\Mvc\Model\Metadata\\'.$metaDataConfig->adapter;
			return new $metadataAdapter();
		}
		return new Phalcon\Mvc\Model\Metadata\Memory();
	});

	/**
	 * Start the session the first time some component request the session service
	 */
	$di->set('session', function(){
		$session = new Phalcon\Session\Adapter\Files();
		$session->start();
		return $session;
	});

    $di->set('modelsCache', function() {

        //Cache data for one day by default
        $frontCache = new \Phalcon\Cache\Frontend\Data(array(
            "lifetime" => 3600
        ));

        //Memcached connection settings
        $cache = new \Phalcon\Cache\Backend\Memcache($frontCache, array(
            "host" => "localhost",
            "port" => "11211"
        ));

        return $cache;
    });

    /**
     * Configure cache
     */
    $di->set('cacheData', function() use ($config) {
        //Cache data for one hour
        //$frontCache = new \Phalcon\Cache\Frontend\None();
    	$frontCache = new \Phalcon\Cache\Frontend\Data(array(
    			"lifetime" => 3600
    	));
        // Create the component that will cache "Data" to a "Memcached" backend
        // Memcached connection settings
        $cacheData = new \Phalcon\Cache\Backend\Memcache($frontCache, array(
            "host" => $config->cache->memcachedServer,
            "port" => $config->cache->memcachedPort
        ));

        return $cacheData;
    });
    
	$di->set('cacheAdData', function() use ($config) {
		$frontCache = new \Phalcon\Cache\Frontend\None();
		$cacheData = new \Phalcon\Cache\Backend\Memcache($frontCache, array(
			"host" => $config->cache->memcachedServer,
			"port" => $config->cache->memcachedPort
		));
    	
		return $cacheData;
	});

    $di->set('mdManager', function() use ($config) {
        return new MDManager();
    });
	/**
	 * Register the flash service with custom CSS classes
	 */
//	$di->set('flash', function(){
//		return new Phalcon\Flash\Direct(array(
//			'error' => 'alert alert-error',
//			'success' => 'alert alert-success',
//			'notice' => 'alert alert-info',
//		));
//	});

//	/**
//	 * Register a user component
//	 */
//	$di->set('elements', function(){
//		return new Elements();
//	});


//	$application = new \Phalcon\Mvc\Application();
//	$application->setDI($di);
//	echo $application->handle()->getContent();

    /**
     * Out application is a Micro application, so we mush explicitly define all the routes.
     * For APIs, this is ideal.  This is as opposed to the more robust MVC Application
     * @var $app
     */
    $app = new Phalcon\Mvc\Micro();
    $app->setDI($di);

    //Mount MDRequest collection
    $mdrequest = new MicroCollection();
    //Set the main handler. ie. a controller instance
    $mdrequest->setHandler(new MDRequestController());
    //Set a common prefix for all routes
    $mdrequest->setPrefix('/v1/mdrequest');
    //Use the method 'indexAction' in ProductsController
    $mdrequest->get('/', 'get');
    $app->mount($mdrequest);

    //Mount MDRequest collection
    $mdtrack = new MicroCollection();
    //Set the main handler. ie. a controller instance
    $mdtrack->setHandler(new MDTrackController());
    //Set a common prefix for all routes
    $mdtrack->setPrefix('/v1/mdtrack');
    //Use the method 'indexAction' in ProductsController
    $mdtrack->get('/', 'get');

    $app->mount($mdtrack);
    
    //Mount MDRequest collection
    $mdclick = new MicroCollection();
    //Set the main handler. ie. a controller instance
    $mdclick->setHandler(new MDClickController());
    //Set a common prefix for all routes
    $mdclick->setPrefix('/v1/mdclick');
    //Use the method 'indexAction' in ProductsController
    $mdclick->get('/', 'get');
    
    $app->mount($mdclick);
    /**
     * After a route is run, usually when its Controller returns a final value,
     * the application runs the following function which actually sends the response to the client.
     *
     * The default behavior is to send the Controller's returned value to the client as JSON.
     * However, by parsing the request querystring's 'type' paramter, it is easy to install
     * different response type handlers.  Below is an alternate csv handler.
     */
    $app->after(function() use ($app) {

        $records = $app->getReturnedValue();
		
        $response = new XMLResponse();
        $response->send($records);

        return;
    });


    /**
     * The notFound service is the default handler function that runs when no route was matched.
     * We set a 404 here unless there's a suppress error codes.
     */
    $app->notFound(function () use ($app) {
        throw new HTTPException(
            'Not Found.',
            404,
            array(
                'dev' => 'That route was not found on the server.',
                'internalCode' => 'NF1000',
                'more' => 'Check route for mispellings.'
            )
        );
    });

    /**
     * If the application throws an HTTPException, send it on to the client as json.
     * Elsewise, just log it.
     * TODO:  Improve this.
     */
    set_exception_handler(function($exception) use ($app){
        //HTTPException's send method provides the correct response headers and body
        if(is_a($exception, 'HTTPException')){
            $exception->send();
        }
        error_log($exception);
        error_log($exception->getTraceAsString());
    });

    $app->handle();
} catch (PDOException $e){
    $di->get("logger")->log($e->getMessage(), Logger::ERROR);
    sendError("42000");
} catch (Phalcon\Db\Exception $e) {
	$di->get("logger")->log($e->getMessage(), Logger::ERROR);
	sendError("42000");
} catch (Phalcon\Mvc\Model\Exception $e) {
	$di->get("logger")->log($e->getMessage(), Logger::ERROR);
	sendError("42000");
} catch (Phalcon\Exception $e) {
	$di->get("logger")->log($e->getMessage(), Logger::ERROR);
	sendError("41000");
} catch (Exception $e) {
	$di->get("logger")->log($e->getMessage(), Logger::ERROR);
	sendError("41000");
}

function sendError($code) {
	$records = array("code"=>$code);
	$response = new XMLResponse();
	$response->send($records);
}