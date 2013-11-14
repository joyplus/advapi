<?php
/**
 * Created by PhpStorm.
 * User: scottliyq
 * Date: 13-11-10
 * Time: 下午4:33
 */

class Configuration extends \Phalcon\Mvc\Model
{

    public function initialize() {
        $this->useDynamicUpdate(true);
    }

    public function getSource()
    {
        return "md_configuration";
    }

}