<?php

class Regions extends \Phalcon\Mvc\Model
{
    public function getSource()
    {
        return "md_regional_targeting";
    }

    public function initialize() {
        $this->useDynamicUpdate(true);
    }
}