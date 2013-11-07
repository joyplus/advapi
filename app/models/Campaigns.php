<?php
/**
 * Created by PhpStorm.
 * User: scottliyq
 * Date: 13-11-4
 * Time: 下午1:05
 */

class Campaigns extends \Phalcon\Mvc\Model
{
    public function getSource()
    {
        return "md_campaigns";
    }

    public function initialize() {
        $this->useDynamicUpdate(true);
    }
}