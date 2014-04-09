<?php

return call_user_func(function(){

	$collection = new \Phalcon\Mvc\Micro\Collection();

	$collection->setHandler('MDRequestController');

    $collection->setPrefix('/'.MAD_REQUEST_HANDLER);
    
    $collection->get('/', 'get');

	return $collection;
});