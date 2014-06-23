<?php

class MDConfigController extends RESTController{
	public function preupload() {
		require __DIR__ . '/../modules/qiniu/rs.php';
		Qiniu_SetKeys(QINIU_ACCESS_KEY, QINIU_SECRET_KEY);
		$putPolicy = new Qiniu_RS_PutPolicy(QINIU_PREUPLOAD_BUKECT);
		$upToken = $putPolicy->Token(null);
		$data = "<preupload>";
		$data .= "<ip>".$this->request->getClientAddress(TRUE)."</ip>";
		//文件上传大小限制 100KB
		$data .= "<filemaxsize>100</filemaxsize>";
		//上传频次 30min
		$data .= "<filefrq>30</filefrq>";
		$data .= "<timestamp>".time()."</timestamp>";
		$data .= "<uptoken>".$upToken."</uptoken>";
		$data .= "</preupload>";
		$this->outputXml($data);
	}
}