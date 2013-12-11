<?php
/**
 * Created by PhpStorm.
 * User: scottliyq
 * Date: 13-11-10
 * Time: 下午4:33
 */

class Configuration extends Base
{
	public $entry_id;
	public $var_name;
	public $var_value;

    public function initialize() {
    	$this->setReadConnectionService('dbSlave');
    	$this->setWriteConnectionService('dbMaster');
        $this->useDynamicUpdate(true);
    }

    public function getSource()
    {
        return "md_configuration";
    }

}