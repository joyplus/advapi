<?php
return call_user_func(function ($config) {
	
	$collection = new \Phalcon\Mvc\Micro\Collection();
	
	$collection->setHandler('MDTrackClickController')->setLazy(true);
	
	$collection->get('/' . $config->handle->mdtrack, 'track');
	
	$collection->get('/' . $config->handle->mdclick, 'click');
	
	return $collection;
	
}, $config);