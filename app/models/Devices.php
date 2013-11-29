<?php

class Devices extends \Phalcon\Mvc\Model
{
    public function getSource()
    {
        return "md_devices";
    }

    public function initialize() {
        $this->useDynamicUpdate(true);
    }
}