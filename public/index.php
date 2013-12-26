<?php
use Phalcon\Mvc\Micro\Collection as MicroCollection;

use Phalcon\Logger,
    Phalcon\Events\Manager as EventsManager,
    Phalcon\Logger\Adapter\File as FileLogger,
    Phalcon\Cache\Backend\Memcached;

error_reporting(E_ALL & ~E_NOTICE);

try {

    define('MAD_MAXMIND_TYPE', 'PHPSOURCE'); // Change to 'NATIVE' if you installed the GeoIP PHP Module (http://www.php.net/manual/en/book.geoip.php) -> Faster! - Please note that mAdserve will crash if this option is enabled but not installed.
    define('MAD_MAXMIND_DATAFILE_LOCATION', __DIR__ . '/../app/data/geotargeting/GeoLiteCity.dat');
    define('MAD_IGNORE_DAILYLIMIT_NOCRON', false); // Ignore a campaign's daily impression limit when the mAdserve cron was not executed for more than 24 hours.

    define('MAD_CLICK_ALWAYS_EXTERNAL', false);
    define('MAD_TRACK_UNIQUE_CLICKS', false); // Track only unique clicks. Works only if a caching method is enabled.
    define('MAD_CLICK_IMMEDIATE_REDIRECT', false); // Make the click handler redirect the end-user to the destination URL immediately and write the click to the statistic database in the background.
    
    define('MAD_MAINTENANCE', false); //设置true停止广告投放


    /**
	 * Read the configuration
	 */
	$config = new Phalcon\Config\Adapter\Ini(__DIR__ . '/../app/config/config.ini');

	define('MD_CACHE_ENABLE',$config->cache->cacheEnable);
	define('MAD_ADSERVING_PROTOCOL', $config->application->serverprefix);
	define('MAD_SERVER_HOST', $config->application->serverhost);//adkey.joyplus.tv
	define('MAD_CLICK_HANDLER', $config->application->mdclick);
	define('MAD_TRACK_HANDLER', $config->application->mdtrack);
	define('MAD_REQUEST_HANDLER', $config->application->mdrequest);
	define('MAD_NETWORK_BATCH_HANDLER', $config->application->mdnetworkbatch);
    define('MAD_MONITOR_HANDLER', $config->application->mdmonitor);
    
    //缓存前缀
    define('CACHE_PREFIX', $config->application->cache_prefix);

    //monitor接口是否检查ip来源
    define('MAD_MONITOR_IP_CHECK', $config->application->md_monitor_ip_check);
    
	define('MD_SLAVE_NUM', $config->slave->slaveNum);
	define('MD_CACHE_TIME', $config->cache->modelsLifetime);
	define('DEBUG_LOG_ENABLE', $config->logger->enabled);
	
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
	 * The URL component is used to generate all kind of urls in the application
	 */
	$di->set('url', function() use ($config){
		$url = new \Phalcon\Mvc\Url();
		$url->setBaseUri($config->application->baseUri);
		return $url;
	});


	$logger = new FileLogger("../app/logs/sql.log");
	/**
	 * Database connection is created based in the parameters defined in the configuration file
	 */
	$di->set('dbMaster', function() use ($config, $logger) {
		$eventsManager = new EventsManager();
		$eventsManager->attach('db', function($event, $connection) use ($config, $logger) {
			if ($event->getType() == 'beforeQuery' && $config->logger->enabled) {
				$logger->log($connection->getSQLStatement()."\n".implode(",",$connection->getSQLVariables()), Logger::INFO);
			}
		});
		$master =  new \Phalcon\Db\Adapter\Pdo\Mysql(array(
			"host" => $config->master->host,
			"port" => $config->master->port,
			"username" => $config->master->username,
			"password" => $config->master->password,
			"dbname" => $config->master->name,
			"options" => array(
					PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'
			)
		));
		$master->setEventsManager($eventsManager);
		return $master;
	});
	$di->set('dbSlave', function() use ($config, $logger) {
		$eventsManager = new EventsManager();
		$eventsManager->attach('db', function($event, $connection) use ($config, $logger) {
			if ($event->getType() == 'beforeQuery' && $config->logger->enabled) {
				$logger->log($connection->getSQLStatement()."\n".implode(",",$connection->getSQLVariables()), Logger::INFO);
			}
		});
		$slave =  new \Phalcon\Db\Adapter\Pdo\Mysql(array(
				"host" => $config->slave->host,
				"port" => $config->slave->port,
				"username" => $config->slave->username,
				"password" => $config->slave->password,
				"dbname" => $config->slave->name,
				"options" => array(
					PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'
				)
		));
		$slave->setEventsManager($eventsManager);
		return $slave;
	});
	for ($i=1; $i<=MD_SLAVE_NUM; $i++) {
		$s = "slave$i";
		$di->set("dbSlave$i", function() use ($config, $logger, $s) {
			$eventsManager = new EventsManager();
			$eventsManager->attach('db', function($event, $connection) use ($config, $logger) {
				if ($event->getType() == 'beforeQuery' && $config->logger->enabled) {
					$logger->log($connection->getSQLStatement()."\n".implode(",",$connection->getSQLVariables()), Logger::INFO);
				}
			});
			$slave =  new \Phalcon\Db\Adapter\Pdo\Mysql(array(
					"host" => $config->$s->host,
					"port" => $config->$s->port,
					"username" => $config->$s->username,
					"password" => $config->$s->password,
					"dbname" => $config->$s->name,
					"options" => array(
						PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'
					)
			));
			$slave->setEventsManager($eventsManager);
		
			return $slave;
		});
	}
//     if ($config->logger->enabled) {
        $di->set('logger', function () use ($config) {

            $logger = new FileLogger(__DIR__."/../app/logs/main.log");
            $formatter = new \Phalcon\Logger\Formatter\Line($config->logger->format);
            $logger->setFormatter($formatter);
            return $logger;
        });
//     } else {
//         $di->set('logger', function () use ($config) {
//             $logger = new \Phalcon\Logger\Adapter\Syslog("ADVAPI", array(
//                 'option' => LOG_NDELAY,
//                 'facility' => LOG_DAEMON
//             ));
//             return $logger;
//         });
//     }

    $di->set('debugLogger', function () use ($config) {
    	$logger = new FileLogger(__DIR__ ."/../app/logs/debug.log");
        $formatter = new \Phalcon\Logger\Formatter\Line($config->logger->format);
        $logger->setFormatter($formatter);
        return $logger;
    });
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

    $di->set('modelsCache', function() use ($config) {

        //Cache data for one day by default
        $frontCache = new \Phalcon\Cache\Frontend\Data(array(
            "lifetime" => MD_CACHE_TIME
        ));

        //Memcached connection settings
        $cache = new \Phalcon\Cache\Backend\Memcache($frontCache, array(
            "host" => $config->cache->memcachedServer,
            "port" => $config->cache->memcachedPort
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
    			"lifetime" => MD_CACHE_TIME
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
    $mdrequest->setPrefix('/'.MAD_REQUEST_HANDLER);
    //Use the method 'indexAction' in ProductsController
    $mdrequest->get('/', 'get');
    $app->mount($mdrequest);

    //Mount MDRequest collection
    $mdtrack = new MicroCollection();
    //Set the main handler. ie. a controller instance
    $mdtrack->setHandler(new MDTrackController());
    //Set a common prefix for all routes
    $mdtrack->setPrefix('/'.MAD_TRACK_HANDLER);
    //Use the method 'indexAction' in ProductsController
    $mdtrack->get('/', 'get');

    $app->mount($mdtrack);
    
    //Mount MDRequest collection
    $mdclick = new MicroCollection();
    //Set the main handler. ie. a controller instance
    $mdclick->setHandler(new MDClickController());
    //Set a common prefix for all routes
    $mdclick->setPrefix('/'.MAD_CLICK_HANDLER);
    //Use the method 'indexAction' in ProductsController
    $mdclick->get('/', 'get');
    
    $app->mount($mdclick);
    
    $mdnetworkbatch = new MicroCollection();
    $mdnetworkbatch->setHandler(new MDNetworkBatchController());
    $mdnetworkbatch->setPrefix('/'.MAD_NETWORK_BATCH_HANDLER);
    $mdnetworkbatch->get('/', 'get');
    $app->mount($mdnetworkbatch);
    
    $mdmonitor = new MicroCollection();
    $mdmonitor->setHandler(new MDMonitorController());
    $mdmonitor->setPrefix('/'.MAD_MONITOR_HANDLER);
    $mdmonitor->get('/', 'get');
    $app->mount($mdmonitor);
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
		if(!isset($records['return_type'])) {
			$records['return_type'] = "xml";
		}
		switch($records['return_type']){
			case 'json' :
				$response = new JSONResponse();
				$response->send($records['data']);
				break;
			case 'xml' :
			default:
				$response = new XMLResponse();
				$response->send($records);
				break;
		}
        

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