<?php

return call_user_func(function($config){

	$collection = new \Phalcon\Mvc\Micro\Collection();

	$collection->setHandler('VDTrackController')->setLazy(true);

    $collection->setPrefix('/'.$config->application->vdtrack);
    
    $collection->get('/', 'get');

	return $collection;
}, $config);