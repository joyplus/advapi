<?php

return call_user_func(function($config){

	$collection = new \Phalcon\Mvc\Micro\Collection();

	$collection->setHandler('MDNetworkBatchController')->setLazy(true);

    $collection->setPrefix('/'.$config->application->mdnetworkbatch);
    
    $collection->get('/', 'get');

	return $collection;
}, $config);