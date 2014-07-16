<?php
class MDRequestController extends RESTController {
	public function get() {
		$result = $this->handleAdRequest();
		return $this->respond($result);
	}
	protected function handleAdRequest() {
		
	}
}