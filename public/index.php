<?php
use Phalcon\Mvc\Micro\Collection as MicroCollection;

use Phalcon\Logger,
    Phalcon\Events\Manager as EventsManager,
    Phalcon\Logger\Adapter\File as FileLogger,
    Phalcon\Cache\Backend\Memcached;

error_reporting(E_ALL);

try {

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

        $logger = new FileLogger("../app/logs/db.log");

//Listen all the database events
        $eventsManager->attach('db', function($event, $connection) use ($logger) {
            if ($event->getType() == 'beforeQuery') {
                $logger->log($connection->getSQLStatement(), Logger::INFO);
            }
        });

        $connection = new \Phalcon\Db\Adapter\Pdo\Mysql(array(
            "host" => $config->database->host,
            "username" => $config->database->username,
            "password" => $config->database->password,
            "dbname" => $config->database->name
        ));

        //Assign the eventsManager to the db adapter instance
        $connection->setEventsManager($eventsManager);

        return $connection;
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

    /**
     * Configure cache
     */
    $di->set('cacheData', function() use ($config) {
        //Cache data for one hour
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


    //Mount Example collection
    $posts = new MicroCollection();
    //Set the main handler. ie. a controller instance
    $posts->setHandler(new ExampleController());
    //Set a common prefix for all routes
    $posts->setPrefix('/v1/example');
    //Use the method 'indexAction' in ProductsController
    $posts->get('/', 'get');
    $posts->put('/', 'insert');
    $app->mount($posts);

    //Mount MDRequest collection
    $mdrequest = new MicroCollection();
    //Set the main handler. ie. a controller instance
    $mdrequest->setHandler(new MDRequestController());
    //Set a common prefix for all routes
    $mdrequest->setPrefix('/v1/mdrequest');
    //Use the method 'indexAction' in ProductsController
    $mdrequest->get('/', 'get');
    $mdrequest->post('/', 'post');
    $mdrequest->put('/', 'put');
    $app->mount($mdrequest);

    /**
     * After a route is run, usually when its Controller returns a final value,
     * the application runs the following function which actually sends the response to the client.
     *
     * The default behavior is to send the Controller's returned value to the client as JSON.
     * However, by parsing the request querystring's 'type' paramter, it is easy to install
     * different response type handlers.  Below is an alternate csv handler.
     */
    $app->after(function() use ($app) {

        // OPTIONS have no body, send the headers, exit
        if($app->request->getMethod() == 'OPTIONS'){
            $app->response->setStatusCode('200', 'OK');
            $app->response->send();
            return;
        }

        // Respond by default as JSON
        if(!$app->request->get('type') || $app->request->get('type') == 'json'){

            // Results returned from the route's controller.  All Controllers should return an array
            $records = $app->getReturnedValue();

            $response = new JSONResponse();
            $response->useEnvelope(true) //this is default behavior
                ->convertSnakeCase(true) //this is also default behavior
                ->send($records);

            return;
        }
        else if($app->request->get('type') == 'csv'){

            $records = $app->getReturnedValue();
            $response = new CSVResponse();
            $response->useHeaderRow(true)->send($records);

            return;
        }
        else {
            throw new \PhalconRest\Exceptions\HTTPException(
                'Could not return results in specified format',
                403,
                array(
                    'dev' => 'Could not understand type specified by type paramter in query string.',
                    'internalCode' => 'NF1000',
                    'more' => 'Type may not be implemented. Choose either "csv" or "json"'
                )
            );
        }
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
} catch (Phalcon\Exception $e) {
	echo $e->getMessage();
} catch (PDOException $e){
	echo $e->getMessage();
}