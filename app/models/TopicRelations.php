<?php
class TopicRelations extends BaseModel {
	public $id;
	public $topic_id;
	public $topic_item_id;
	
	public function initialize() {
		$this->setReadConnectionService('dbSlave');
		$this->setWriteConnectionService('dbMaster');
		$this->useDynamicUpdate(true);
	}
	
	public function getSource() {
		return "md_vod_topic_items_relation";
	}
}