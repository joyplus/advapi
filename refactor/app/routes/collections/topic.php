<?php
return call_user_func ( function ($config) {
	
	$collection = new \Phalcon\Mvc\Micro\Collection ();
	$collection->setHandler ( 'MDTopicController' )->setLazy ( true );
	$collection->get ( '/' . $config->handle->topic, 'listTopic' );
	$collection->get ( '/' . $config->handle->topicGet, 'get' );
	return $collection;
	
}, $config );