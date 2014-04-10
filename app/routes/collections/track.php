<?php

return call_user_func(function($config){

	$collection = new \Phalcon\Mvc\Micro\Collection();

	$collection->setHandler('MDTrackController')->setLazy(true);

    $collection->setPrefix('/'.$config->application->mdtrack);
    
    $collection->get('/', 'get');

	return $collection;
}, $config);