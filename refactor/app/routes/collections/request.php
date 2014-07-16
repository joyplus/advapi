<?php
return call_user_func ( function ($config) {
	
	$collection = new \Phalcon\Mvc\Micro\Collection ();
	
	$collection->setHandler ( 'MDRequestController' )->setLazy ( true );
	
	$collection->setPrefix ( '/' . $config->handle->mdrequest );
	
	$collection->get ( '/', 'get' );
	
	return $collection;
}, $config );