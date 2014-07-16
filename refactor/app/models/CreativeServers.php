<?php
/**
 * Created by PhpStorm.
 * User: scottliyq
 * Date: 13-11-11
 * Time: 下午3:39
 */
class CreativeServers extends BaseModel {
	public $entry_id;
	public $server_type;
	public $server_name;
	public $remote_host;
	public $remote_port;
	public $remote_user;
	public $remote_password;
	public $remote_directory;
	public $server_default_url;
	public $server_status;
	public function initialize() {
		$this->setReadConnectionService ( 'dbSlave' );
		$this->setWriteConnectionService ( 'dbMaster' );
		$this->useDynamicUpdate ( true );
	}
	public function getSource() {
		return "md_creative_servers";
	}
}