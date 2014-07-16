<?php
class ServerIp extends BaseModel {
	public $id;
	public $campaign_name;
	public $ip;
	public $del_flag;
	public function getSource() {
		return "md_server_ip";
	}
	public function initialize() {
		$this->setReadConnectionService ( 'dbSlave' );
		$this->setWriteConnectionService ( 'dbMaster' );
		$this->useDynamicUpdate ( true );
	}
}