<?php
return call_user_func ( function ($config) {
	
	$collection = new \Phalcon\Mvc\Micro\Collection ();
	
	$collection->setHandler ( 'MDVclogController' )->setLazy ( true );
	
	$collection->setPrefix ( '/' . $config->handle->mdvclog );
	
	$collection->get ( '/', 'get' );
	
	return $collection;
}, $config );