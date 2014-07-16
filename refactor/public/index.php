<?php
error_reporting(E_ALL &  ~ E_NOTICE);

try {
	
	/**
	 * Read the configuration
	 */
	$config = new Phalcon\Config\Adapter\Ini(__DIR__ . '/../app/config/config.ini');
	
	define("BUSINESS_ID", $config->application->business_id);
	
	define('DB_SLAVE_NUM', $config->slave->slaveNum); // 从数据库个数
	
	define("CACHE_PREFIX", $config->cache->prefix);
	define("CACHE_ENABLE", $config->cache->enable);
	define("CACHE_TIME", $config->cache->time);
	
	define("LOGGER_ENABLE", $config->logger->enable);
	
	$loader = new \Phalcon\Loader();
	
	/**
	 * We're a registering a set of directories taken from the configuration file
	 */
	$loader->registerDirs(array (
			__DIR__ . $config->application->controllersDir, 
			__DIR__ . $config->application->listenersDir, 
			__DIR__ . $config->application->pluginsDir, 
			__DIR__ . $config->application->libraryDir, 
			__DIR__ . $config->application->modelsDir, 
			__DIR__ . $config->application->viewsDir, 
			__DIR__ . $config->application->routesDir 
	));
	$loader->register();
	
	/**
	 * The FactoryDefault Dependency Injector automatically register the right services providing a full stack framework
	 */
	$di = new \Phalcon\DI\FactoryDefault();
	
	$formatter = new \Phalcon\Logger\Formatter\Line($config->logger->format);
	$loggerDebug = new Phalcon\Logger\Adapter\File($config->logger->pathDebug);
	$loggerDebug->setFormatter($formatter);
	$loggerInfo = new Phalcon\Logger\Adapter\File($config->logger->pathInfo);
	$loggerInfo->setFormatter($formatter);
	$loggerError = new Phalcon\Logger\Adapter\File($config->logger->pathError);
	$loggerError->setFormatter($formatter);
	$di->set('loggerDebug', function () use($loggerDebug) {
		return $loggerDebug;
	});
	$di->set('loggerError', function () use($loggerError) {
		return $loggerError;
	});
	$di->set('loggerInfo', function () use($loggerInfo) {
		return $loggerInfo;
	});
	
	$di->set('config', function () use($config) {
		return $config;
	});
	
	$di->set('dbMaster', function () use($config, $di) {
		$eventsManager = $di->getShared('eventsManager');
		$dbListener = new DbListener($di);
		$eventsManager->attach('db', $dbListener);
		$master = new \Phalcon\Db\Adapter\Pdo\Mysql(array (
				"host" => $config->master->host, 
				"port" => $config->master->port, 
				"username" => $config->master->username, 
				"password" => $config->master->password, 
				"dbname" => $config->master->name, 
				"options" => array (
						PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8' 
				) 
		));
		$master->setEventsManager($eventsManager);
		return $master;
	});
	
	$di->set('dbSlave', function () use($config, $di) {
		$eventsManager = $di->getShared('eventsManager');
		$dbListener = new DbListener($di);
		$eventsManager->attach('db', $dbListener);
		$slave = new \Phalcon\Db\Adapter\Pdo\Mysql(array (
				"host" => $config->slave->host, 
				"port" => $config->slave->port, 
				"username" => $config->slave->username, 
				"password" => $config->slave->password, 
				"dbname" => $config->slave->name, 
				"options" => array (
						PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8' 
				) 
		));
		$slave->setEventsManager($eventsManager);
		return $slave;
	});
	
	for($i = 1; $i <= $config->slave->slaveNum; $i ++ ) {
		$s = "slave$i";
		$di->set("dbSlave$i", function () use($config, $di, $s) {
			$eventsManager = $di->getShared('eventsManager');
			$dbListener = new DbListener($di);
			$eventsManager->attach('db', $dbListener);
			$slave = new \Phalcon\Db\Adapter\Pdo\Mysql(array (
					"host" => $config->$s->host, 
					"port" => $config->$s->port, 
					"username" => $config->$s->username, 
					"password" => $config->$s->password, 
					"dbname" => $config->$s->name, 
					"options" => array (
							PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8' 
					) 
			));
			$slave->setEventsManager($eventsManager);
			
			return $slave;
		});
	}
	
	/**
	 * The URL component is used to generate all kind of urls in the application
	 */
	$di->set('url', function () use($config) {
		$url = new \Phalcon\Mvc\Url();
		$url->setBaseUri($config->application->baseUri);
		return $url;
	});
	
	$di->set('modelsCache', function () use($config) {
		$frontCache = new \Phalcon\Cache\Frontend\Data(array (
				"lifetime" => $config->cache->time 
		));
		$cache = new \Phalcon\Cache\Backend\Memcache($frontCache, array (
				"host" => $config->cache->server, 
				"port" => $config->cache->port 
		));
		return $cache;
	});
	$di->set('cache', function () use($config) {
		$frontCache = new \Phalcon\Cache\Frontend\None();
		$cacheData = new \Phalcon\Cache\Backend\Memcache($frontCache, array (
				"host" => $config->cache->server, 
				"port" => $config->cache->port 
		));
		
		return $cacheData;
	});
	$di->set('modelsMetadata', function () use($config) {
		if(isset($config->models->metadata)) {
			$metaDataConfig = $config->models->metadata;
			$metadataAdapter = 'Phalcon\Mvc\Model\Metadata\\' . $metaDataConfig->adapter;
			return new $metadataAdapter();
		}
		return new Phalcon\Mvc\Model\Metadata\Memory();
	});
	
	$di->set('view', function () use($config) {
		$view = new \Phalcon\Mvc\View\Simple();
		$view->setViewsDir(__DIR__ . $config->application->viewsDir);
		return $view;
	});
	
	// beanstalk
	$di->set('beanstalk', function () use($config) {
		$queue = new Phalcon\Queue\Beanstalk(array (
				'host' => $config->beanstalk->host, 
				'port' => $config->beanstalk->port 
		));
		return $queue;
	});
	
	$di->set('collections', function () use($config) {
		return include (__DIR__ . '/../app/routes/routeLoader.php');
	});
	
	$app = new Phalcon\Mvc\Micro();
	$app->setDI($di);
	
	foreach($di->get('collections') as $collection) {
		$app->mount($collection);
	}
	
	$app->notFound(function () use($app) {
		sendError($app, "30000", "url not found");
	});
	
	$app->handle();
} catch(PDOException $e) {
	sendError($app, "42000", $e->getMessage());
} catch(Phalcon\Db\Exception $e) {
	sendError($app, "42000", $e->getMessage());
} catch(Phalcon\Mvc\Model\Exception $e) {
	sendError($app, "42000", $e->getMessage());
} catch(Phalcon\Exception $e) {
	sendError($app, "41000", $e->getMessage());
} catch(Exception $e) {
	sendError($app, "41000", $e->getMessage());
}
function sendError($app, $code, $message) {
	$app->getDi()->get("loggerError")->log($message, Phalcon\Logger::ERROR);
	$rq = $app->getDi()->get("request")->get("rq", null, 0);
	
	if($rq == 0) {
		$result = $app->view->render("error/xml", array (
				"code" => $code 
		));
		$app->response->setContentType('text/xml;charset=UTF-8');
	} else {
		$result = json_encode(array (
				"_meta" => array (
						"code" => $code 
				) 
		));
		$app->response->setContentType('application/json;charset=UTF-8');
	}
	
	$app->response->setContent($result);
	$app->response->send();
}
