<?php
return call_user_func ( function ($config) {
	
	$collection = new \Phalcon\Mvc\Micro\Collection ();
	
	$collection->setHandler ( 'MDCreativeController' )->setLazy ( true );
	
	$collection->setPrefix ( '/' . $config->handle->creative );
	
	$collection->get ( '/', 'get' );
	
	return $collection;
}, $config );