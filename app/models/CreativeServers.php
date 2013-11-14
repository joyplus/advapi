<?php
/**
 * Created by PhpStorm.
 * User: scottliyq
 * Date: 13-11-11
 * Time: 下午3:39
 */

class CreativeServers extends \Phalcon\Mvc\Model
{

    public function initialize() {
        $this->useDynamicUpdate(true);
    }

    public function getSource()
    {
        return "md_creative_servers";
    }

}