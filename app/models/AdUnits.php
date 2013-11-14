<?php
/**
 * Created by PhpStorm.
 * User: scottliyq
 * Date: 13-11-11
 * Time: 上午11:20
 */

class AdUnits extends \Phalcon\Mvc\Model
{
    public function getSource()
    {
        return "md_ad_units";
    }

    public function initialize() {
        $this->useDynamicUpdate(true);
    }
}