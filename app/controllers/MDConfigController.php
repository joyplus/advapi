<?php

class MDConfigController extends RESTController{
	public function timestamp() {
		$data = "<timestamp>".time()."</timestamp>";
		$this->outputXml($data);
	}
}