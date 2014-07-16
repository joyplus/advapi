<?php

class MDConfigController extends RESTController {
	
	/**
	 * 返回配置xml
	 */
	public function admaster() {
		$this->executeXml ( "config/admaster", null );
	}
	
	/**
	 * 返回qiniu uptoken
	 */
	public function preupload() {
		require __DIR__ . '/../libraries/qiniu/rs.php';
		
		Qiniu_SetKeys($this->config('qiniu', 'accessKey'), $this->config('qiniu', 'secretKey'));
		$putPolicy = new Qiniu_RS_PutPolicy($this->config('qiniu', 'preUploadBucket'));
		$upToken = $putPolicy->Token(null);
		$data = "<preupload>";
		$data .= "<ip>".$this->request->getClientAddress(TRUE)."</ip>";
		$data .= "<timestamp>".time()."</timestamp>";
		$data .= "<uptoken>".$upToken."</uptoken>";
		$data .= "</preupload>";
		$this->outputXml($data);
	}
}