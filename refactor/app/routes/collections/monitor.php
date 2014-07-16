<?php
return call_user_func ( function ($config) {
	
	$collection = new \Phalcon\Mvc\Micro\Collection ();
	
	$collection->setHandler ( 'MDMonitorController' )->setLazy ( true );
	
	$collection->setPrefix ( '/' . $config->handle->mdmonitor );
	
	$collection->get ( '/', 'get' );
	
	return $collection;
}, $config );