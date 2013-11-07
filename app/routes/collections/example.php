<?php

/**
 * Collections let us define groups of routes that will all use the same controller.
 * We can also set the handler to be lazy loaded.  Collections can share a common prefix.
 * @var $exampleCollection
 */

// This is an Immeidately Invoked Function in php.  The return value of the
// anonymous function will be returned to any file that "includes" it.
// e.g. $collection = include('example.php');
return call_user_func(function(){

	$exampleCollection = new \Phalcon\Mvc\Micro\Collection();

	$exampleCollection
		// VERSION NUMBER SHOULD BE FIRST URL PARAMETER, ALWAYS
		->setPrefix('/v1/example')
		// Must be a string in order to support lazy loading
		->setHandler('ContactController');

    xdebug_debug_zval_stdout('$exampleCollection');

	return $exampleCollection;
});