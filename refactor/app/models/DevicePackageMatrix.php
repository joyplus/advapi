<?php
class DevicePackageMatrix extends BaseModel {
	public $id;
	public $device_id;
	public $package_id;
	public function initialize() {
		$this->setReadConnectionService ( 'dbSlave' );
		$this->setWriteConnectionService ( 'dbMaster' );
		$this->useDynamicUpdate ( true );
	}
	
	// 获取数据库表名
	public function getSource() {
		return "md_device_package_matrix";
	}
}