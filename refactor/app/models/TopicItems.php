<?php
class TopicItems extends BaseModel {
	public $id;
	public $name;
	public $description;
	public $pic_url;
	public $uri;
	public $business_id;
	public $create_time;
	public $column;
	public $zone;
	public function initialize() {
		$this->setReadConnectionService ( 'dbSlave' );
		$this->setWriteConnectionService ( 'dbMaster' );
		$this->useDynamicUpdate ( true );
	}
	public function getSource() {
		return "md_vod_topic_items";
	}
}