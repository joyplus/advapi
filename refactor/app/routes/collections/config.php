<?php
return call_user_func ( function ($config) {
	
	$collection = new \Phalcon\Mvc\Micro\Collection ();
	
	$collection->setHandler ( 'MDConfigController' )->setLazy ( true );
	
	$collection->get ( '/' . $config->handle->admaster, 'admaster' );
	$collection->get ( '/' . $config->handle->preupload, 'preupload' );
	
	return $collection;
}, $config );