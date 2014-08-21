<?php

class YzChips extends \Phalcon\Mvc\Model
{
    public $chip;
    public function initialize() {
        $this->setReadConnectionService('dbSlave');
        $this->setWriteConnectionService('dbMaster');
        $this->useDynamicUpdate(true);
        $this->skipAttributes(array('id'));
    }

    public function getSource() {
        return "md_yangzhi_chips";
    }
}