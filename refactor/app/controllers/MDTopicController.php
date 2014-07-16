<?php
class MDTopicController extends RESTController {
	
	/**
	 * 获取具体榜单的数据
	 */
	public function get() {
		$params['s'] = $this->request->get("s", null, '');
		$params['business_id'] = $this->request->get("bid", null, '');
		$this->logDebug("[get] bid->" . $params['business_id']);
		$this->logDebug("[get] s->" . $params['s']);
		if( ! empty($params['s'])) {
			$topic = Topic::findFirst(array (
					"hash=:s:", 
					"bind" => array (
							"s" => $params['s'] 
					), 
					"cache" => array (
							"key" => CACHE_PREFIX . "_TOPIC_HASH_" . $params['s'], 
							"lifetime" => CACHE_TIME 
					) 
			));
		} else if( ! empty($params['business_id'])) {
			$topics = Topic::find(array (
					"business_id = :business_id:", 
					"bind" => array (
							"business_id" => $params['business_id'] 
					), 
					"cache" => array (
							"key" => CACHE_PREFIX . "_TOPIC_BUSINESS_" . $params['business_id'], 
							"lifetime" => CACHE_TIME 
					), 
					"order" => "id" 
			));
			foreach($topics as $t) {
				$rows[] = $t;
			}
			// 随机取一条记录
			shuffle($rows);
			$topic = $rows[0];
		}
		if( ! $topic) {
			$result['_meta']['code'] = "20001";
			$this->outputJson($result);
		}
		$this->logDebug("[get] topic id->" . $topic->id);
		$result['_meta']['code'] = "00000";
		$result['openType'] = $topic->open_type;
		$result['widgetPicUrl'] = $topic->widget_url;
		$ad = $this->getAdunit($topic->zone_hash);
		if($ad) {
			$params = "rq=1&ad=" . $ad->unit_hash . "&zone=" . $topic->zone_hash . "&dm=%dm%&i=%mac%";
			$result['creativeUrl'] = $ad->adv_creative_url;
			$result['trackingUrl'] = $this->configApp("serverprefix") . $this->configApp("serverhost") . "/" . $this->configHandle("mdmonitor") . "?" . $params;
			$result['trackingUrlMiaozhen'] = $ad->adv_impression_tracking_url ? adv_impression_tracking_url : "";
			$result['trackingUrlIresearch'] = $ad->adv_impression_tracking_url_iresearch ? $ad->adv_impression_tracking_url_iresearch : "";
			$result['trackingUrlAdmaster'] = $ad->adv_impression_tracking_url_admaster ? $ad->adv_impression_tracking_url_admaster : "";
			$result['trackingUrlNielsen'] = $ad->adv_impression_tracking_url_nielsen ? $ad->adv_impression_tracking_url_nielsen : "";
		} else {
			$result['creativeUrl'] = $topic->background_url;
			$result['trackingUrl'] = "";
			$result['trackingUrlMiaozhen'] = "";
			$result['trackingUrlIresearch'] = "";
			$result['trackingUrlAdmaster'] = "";
			$result['trackingUrlNielsen'] = "";
		}
		
		$items = TopicRelations::find(array (
				"topic_id = :topic_id:", 
				"bind" => array (
						"topic_id" => $topic->id 
				), 
				"cache" => array (
						"key" => CACHE_PREFIX . "_TOPIC_RELATIONS_" . $topic->id, 
						"lifetime" => CACHE_TIME 
				) 
		));
		foreach($items as $item) {
			$video = TopicItems::findFirst(array (
					"id=:id:", 
					"bind" => array (
							"id" => $item->topic_item_id 
					), 
					"cache" => array (
							"key" => CACHE_PREFIX . "_TOPIC_ITEM_ID_" . $item->topic_item_id, 
							"lifetime" => CACHE_TIME 
					) 
			));
			if($video) {
				$row = $this->arrayKeysToSnake($video->toArray());
				$row['column'] = Lov::getValue("topic_video_column", $row['column']);
				$row['zone'] = Lov::getValue("topic_video_zone", $row['zone']);
				$rows[] = $row;
			}
		}
		if(count($rows) < 1) {
			$result['_meta']['code'] = "20001";
		} else {
			$result['_meta']['count'] = count($rows);
		}
		$result['items'] = $rows;
		$this->logDebug("[get] results->" . json_encode($result));
		$this->outputJson($result);
	}
	private function getAdunit($zone_hash) {
		$date = date("Y-m-d");
		$zone = Zones::findByHash($zone_hash);
		if( ! $zone) {
			return false;
		}
		$this->logDebug("[get] zone id->" . $zone->entry_id);
		$campaign = $this->findCampaign($zone->entry_id, $date);
		if( ! $campaign) {
			return false;
		}
		$this->logDebug("[get] campaign id->" . $campaign->campaign_id);
		$ads = $this->findAdUnit($campaign, $date);
		if( ! ads) {
			return false;
		}
		$this->logDebug("[get] count ads->" . count($ads));
		if($campaign->rule == 1) { // 创意随机排序
			shuffle($ads);
			$ad_id = $ads[0]['ad_id'];
		} else {
			$ad_id = $ads[0]['ad_id'];
		}
		$ad = AdUnits::findFirst($ad_id);
		$this->logDebug("[get] ad id->" . $ad->adv_id);
		return $ad;
	}
	private function findCampaign($zone_id, $date) {
		$phql = "SELECT c.campaign_id AS campaign_id, c.creative_show_rule AS rule FROM Campaigns AS c 
    			LEFT JOIN CampaignTargeting AS t ON c.campaign_id=t.campaign_id
    			LEFT JOIN CampaignLimit AS ct ON c.campaign_id=ct.campaign_id 
    			LEFT JOIN AdUnits AS ad ON c.campaign_id=ad.campaign_id
    			WHERE (t.targeting_type='placement' AND t.targeting_code=:zone_id:)
    			AND c.campaign_status=1 AND c.campaign_start<=:campaign_start: 
    			AND c.campaign_end>=:campaign_end: AND (ad.adv_start<=:adv_start: 
    			AND ad.adv_end>=:adv_end: AND ad.adv_status=1 AND ad.adv_type='1')
    			AND ct.total_amount_left>=1 
    			ORDER BY c.campaign_priority";
		$params['zone_id'] = $zone_id;
		$params['campaign_start'] = $date;
		$params['campaign_end'] = $date;
		$params['adv_start'] = $date;
		$params['adv_end'] = $date;
		
		$result = $this->modelsManager->executeQuery($phql, $params);
		if(count($result) < 1) {
			return false;
		}
		return $result[0];
	}
	private function findAdUnit($campaign, $date) {
		$order = " adv_id";
		// 创意权重排序
		if($campaign->rule == 3) {
			$order = "creative_weight DESC";
		}
		$conditions = " campaign_id=:campaign_id: AND adv_type=1
    			AND adv_start<=:adv_start: AND adv_end>=:adv_end:
    			AND adv_status=1 AND del_flg<>1 ORDER BY " . $order;
		$params['campaign_id'] = $campaign->campaign_id;
		$params['adv_start'] = $date;
		$params['adv_end'] = $date;
		
		$result = AdUnits::find(array (
				$conditions, 
				"bind" => $params, 
				"cache" => array (
						"key" => CACHE_PREFIX . "_ADUNITS_" . $conditions . md5(serialize($params)), 
						"lifetime" => CACHE_TIME 
				) 
		));
		$adarray = array ();
		foreach($result as $item) {
			$add = array (
					'ad_id' => $item->adv_id, 
					'width' => $item->adv_width, 
					'height' => $item->adv_height, 
					'weight' => $item->creative_weight 
			);
			$adarray[] = $add;
		}
		return count($adarray) < 1 ? false : $adarray;
	}
	
	/**
	 * 获取榜单列表
	 */
	public function listTopic() {
		$params['business_id'] = $this->request->get("bid", null, '');
		$this->logDebug("[list] bid->" . $params['business_id']);
		$topics = Topic::find(array (
				"business_id = :business_id:", 
				"bind" => $params, 
				"cache" => array (
						"key" => CACHE_PREFIX . "_TOPIC_BUSINESS_" . $params['business_id'], 
						"lifetime" => CACHE_TIME 
				), 
				"order" => "id" 
		));
		foreach($topics as $t) {
			$row = $this->arrayKeysToSnake($t->toArray());
			
			$row['url'] = $this->configApp("serverprefix") . $this->configApp("serverhost") . "/" . $this->configHandle("topicGet") . "?s=" . $row['hash'];
			$rows[] = $row;
		}
		if(count($rows) < 1) {
			$result['_meta']['code'] = "20001";
		} else {
			$result['_meta']['code'] = "00000";
			$result['_meta']['count'] = count($rows);
		}
		$result['items'] = $rows;
		$this->logDebug("[listTopic] data->" . json_encode($result));
		$this->outputJson($result);
	}
	private function logDebug($log) {
		$this->log("[TopicController]" . $log, Phalcon\Logger::DEBUG);
	}
}