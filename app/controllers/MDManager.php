<?php
/**
 * Created by PhpStorm.
 * User: scottliyq
 * Date: 13-11-10
 * Time: 下午5:11
 */

class MDManager extends \Phalcon\DI\Injectable {

    public function __construct(){
        //parent::__construct();
        $di = \Phalcon\DI::getDefault();
        $this->setDI($di);
    }


} 