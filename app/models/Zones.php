<?php
/**
 * Created by PhpStorm.
 * User: scottliyq
 * Date: 13-11-5
 * Time: 上午11:45
 */
class Zones extends \Phalcon\Mvc\Model
{

    public function initialize() {
        $this->useDynamicUpdate(true);
    }

    public function getSource()
    {
        return "md_zones";
    }

}