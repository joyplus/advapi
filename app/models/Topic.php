<?php
class Topic extends BaseModel
{
	public $id;
	public $name;
	public $description;
	public $background_url;
	public $widget_url;
	public $pic_url;
	public $zone_hash;
	public $hash;
	public $business_id;
	public $create_time;
	public $status;
	

	public function initialize() {
		$this->setReadConnectionService('dbSlave');
		$this->setWriteConnectionService('dbMaster');
		$this->useDynamicUpdate(true);
	}

	public function getSource() {
		return "md_vod_topic";
	}
}