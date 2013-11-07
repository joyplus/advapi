<?php
/**
 * Created by PhpStorm.
 * User: scottliyq
 * Date: 13-11-5
 * Time: 下午1:58
 */

class Publications extends \Phalcon\Mvc\Model
{

    public function initialize() {
        $this->useDynamicUpdate(true);
    }

    public function getSource() {
        return "md_publications";
    }

}