<?php

use Phalcon\Events\Event,
	Phalcon\Mvc\User\Plugin;

class DbListener extends Plugin
{

	public function __construct($dependencyInjector) {
		$this->_dependencyInjector = $dependencyInjector;
	}
	
	public function beforeQuery($event, $connection) {
		if(LOGGER_ENABLE) {
			if ($connection->getSQLVariables()) {
				$this->di->get('loggerDebug')->log($connection->getSQLStatement()."\n"
						.implode(",",$connection->getSQLVariables()), Phalcon\Logger::DEBUG);
			} else {
				$this->di->get('loggerDebug')->log($connection->getSQLStatement()."\n", Phalcon\Logger::DEBUG);
			}
		}
	}

}
