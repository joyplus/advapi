<?php
class Lov extends BaseModel
{
	public $key;
	public $code;
	public $value;
	public $description;

	public function initialize() {
		$this->setReadConnectionService('dbSlave');
		$this->setWriteConnectionService('dbMaster');
		$this->useDynamicUpdate(true);
	}

	public function getSource()
	{
		return "md_lov";
	}
	
	public static function getScreen($code) {
		if(!isset($code) || empty($code))
			return false;
		$s = Lov::findFirst(array(
			"key='screen_type' AND code='".$code."'",
			"cache"=>array("key"=>CACHE_PREFIX."_LOV_SCREEN_".$code)
		));
		if($s) {
			$size = $s->value;
			return explode("x",$size);
		}
		return false;
	}

}