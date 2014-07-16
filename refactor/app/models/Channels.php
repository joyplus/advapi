<?php
class Channels extends BaseModel {
	public $channel_id;
	public $channel_type;
	public $channel_name;
	public function getSource() {
		return "md_channels";
	}
	public function initialize() {
		$this->setReadConnectionService ( 'dbSlave' );
		$this->setWriteConnectionService ( 'dbMaster' );
		$this->useDynamicUpdate ( true );
	}
}