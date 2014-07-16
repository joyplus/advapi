<?php
use Phalcon\Logger;
class RESTController extends BaseController {
	const OPERATION_TYPE_NO_AD = '001';//请求无广告
	const OPERATION_TYPE_AD = '002'; //请求有广告
	const OPERATION_TYPE_IMPRESSION = '003'; //展现
	const OPERATION_TYPE_CREATIVE_URL = '004'; //图片代理
	const OPERATION_TYPE_CLICK = '005'; //点击
	/**
	 * 记录日志
	 *
	 * @param unknown $log        	
	 * @param unknown $level        	
	 */
	protected function log($log, $level = Logger::DEBUG) {
		if($level == Logger::ERROR) {
			$logger = $this->getDi()->get('loggerError');
			$logger->log($log, Logger::ERROR);
		} else if($level == Logger::INFO) {
			$logger = $this->getDi()->get('loggerDebug');
			$logger->log($log, Logger::INFO);
		} else if($level == Logger::DEBUG && LOGGER_ENABLE) {
			$logger = $this->getDi()->get('loggerDebug');
			$logger->log($log, Logger::DEBUG);
		} else if($level == Logger::WARNING && LOGGER_ENABLE) {
			$logger = $this->getDi()->get('loggerDebug');
			$logger->log($log, Logger::WARNING);
		}
	}
	
	/**
	 * 获取缓存数据
	 * 
	 * @param unknown $cacheKey        	
	 * @return Ambigous <boolean, unknown>
	 */
	function getCacheValue($cacheKey) {
		$cacheKey = md5($cacheKey);
		$result = $this->getDi()->get("cache")->get($cacheKey);
		return $result ? $result : false;
	}
	
	/**
	 * 设置缓存
	 * 
	 * @param unknown $key        	
	 * @param unknown $value        	
	 * @param string $time        	
	 * @return boolean
	 */
	function setCacheValue($key, $value, $time = CACHE_TIME) {
		if(!CACHE_ENABLE)
			return false;
		$cacheKey = md5($key);
		$this->getDi()->get("cache")->save($cacheKey, $value, $time);
	}
	
	/**
	 * 渲染json数据并输出
	 *
	 * @param unknown $template        	
	 * @param unknown $data        	
	 */
	protected function executeJson($template, $data) {
		$html = $this->executeTemplate($template, $data);
		$this->outputJson($html);
	}
	/**
	 * 渲染xml数据并输出
	 *
	 * @param unknown $template        	
	 * @param unknown $data        	
	 */
	protected function executeXml($template, $data) {
		$html = $this->executeTemplate($template, $data);
		$this->outputXml($html);
	}
	/**
	 * 渲染模板
	 *
	 * @param unknown $template        	
	 */
	protected function executeTemplate($template, $data) {
		return $this->view->render($template, $data);
	}
	/**
	 * 输出json数据
	 *
	 * @param unknown $template        	
	 * @param unknown $data        	
	 */
	protected function outputJson($array) {
		$response = $this->response;
		$response->setContentType('application/json;charset=UTF-8');
		$response->setContent(json_encode($array));
		$response->send();
	}
	
	/**
	 * 输出xml数据
	 *
	 * @param unknown $template        	
	 * @param unknown $data        	
	 */
	protected function outputXml($data) {
		$response = $this->response;
		$response->setContentType('text/xml;charset=UTF-8');
		$response->setContent($data);
		$response->send();
	}
	
	/**
	 * 获取配置文件信息
	 *
	 * @param unknown $section        	
	 * @param unknown $key        	
	 * @return boolean
	 */
	protected function config($section, $key) {
		$config = $this->di->get("config");
		$s = $config->$section;
		return $s ? $s->$key : false;
	}
	protected function configHandle($key) {
		return self::config("handle", $key);
	}
	protected function configApp($key) {
		return self::config("application", $key);
	}
	
	/**
	 * 解析ip获取地址code
	 */
	protected function getCodeFromIp($ip) {
		$cities = array (
				'CN_01' => '北京市', 
				'CN_02' => '天津市', 
				'CN_09' => '上海市', 
				'CN_22' => '重庆市', 
				'CN_32' => '香港', 
				'CN_33' => '澳门' 
		);
		$regions = array (
				'CN_05' => '内蒙古', 
				'CN_20' => '广西', 
				'CN_26' => '西藏', 
				'CN_30' => '宁夏', 
				'CN_31' => '新疆' 
		);
		$address = IpCodePlugin::getAddressFromIp($ip);
		$this->log("[getCodeFromIp] find address->" . $address, Logger::DEBUG);
		if(!empty($address)) {
			foreach($cities as $key => $value) {
				$pattern = "/^" . $value . "\.*/iu";
				if(preg_match($pattern, $address))
					return array (
							$key, 
							$key 
					);
			}
			
			foreach($regions as $key => $value) {
				$pattern = "/^" . $value . "([\x{4e00}-\x{9fa5}]*)/iu";
				if(preg_match($pattern, $address, $matchs)) {
					if(!empty($matchs[1])) {
						$code = $this->getCodeFromAddress($matchs[1]);
						return array (
								$key, 
								$code 
						);
					}
					return array (
							$key 
					);
				}
			}
			
			$pattern = "/([\x{4e00}-\x{9fa5}]+省)([\x{4e00}-\x{9fa5}]*)/iu";
			if(preg_match($pattern, $address, $matchs)) {
				$code1 = "";
				$code2 = "";
				if(!empty($matchs[1])) {
					$code1 = $this->getCodeFromAddress($matchs[1]);
				}
				if(!empty($matchs[2])) {
					$code2 = $this->getCodeFromAddress($matchs[2]);
				}
				$this->log("match code:" . $code1 . "--" . $code2);
				return array (
						$code1, 
						$code2 
				);
			}
		}
		return array ();
	}
	
	/**
	 * 从地狱信息获取对应code
	 * 
	 * @param unknown $region_name        	
	 * @return string
	 */
	protected function getCodeFromAddress($region_name) {
		$region = Regions::findFirst(array (
				"conditions" => "region_name= :region_name:", 
				"bind" => array (
						"region_name" => $region_name 
				), 
				"cache" => array (
						"key" => CACHE_PREFIX . "_REGIONS_" . $region_name, 
						"lifetime" => CACHE_TIME 
				) 
		));
		return $region ? $region->targeting_code : "";
	}
	
	/**
	 * In-Place, recursive conversion of array keys in snake_Case to camelCase
	 *
	 * @param array $snakeArray
	 *        	Array with snake_keys
	 * @return no return value, array is edited in place
	 */
	protected function arrayKeysToSnake($snakeArray) {
		foreach($snakeArray as $k => $v) {
			if(is_array($v)) {
				$v = $this->arrayKeysToSnake($v);
			}
			$snakeArray[$this->snakeToCamel($k)] = $v;
			if($this->snakeToCamel($k) != $k) {
				unset($snakeArray[$k]);
			}
		}
		return $snakeArray;
	}
	
	/**
	 * Replaces underscores with spaces, uppercases the first letters of each word,
	 * lowercases the very first letter, then strips the spaces
	 *
	 * @param string $val
	 *        	String to be converted
	 * @return string Converted string
	 */
	protected function snakeToCamel($val) {
		return str_replace(' ', '', lcfirst(ucwords(str_replace('_', ' ', $val))));
	}
	
	/**
	 * 发送数据到beanstalk
	 * 
	 * @param unknown $queue        	
	 * @param unknown $tube        	
	 * @param unknown $data        	
	 */
	protected function sendToBeanstalk($tube, $data) {
		$queue = $this->getDi()->get('beanstalk');
		$queue->choose(BUSINESS_ID . $tube);
		$queue->put($data);
	}
	
	/**
	 * 状态code
	 * 
	 * @return multitype:string
	 */
	protected function codeSuccess() {
		return array (
				"code" => "00000" 
		);
	}
	protected function codeInputError() {
		return array (
				"code" => "30001" 
		);
	}
	protected function codeNoAds() {
		return array (
				"code" => "20001" 
		);
	}
	
	/**
	 * 投放次数 -1
	 * 
	 * @param unknown $campaign_id        	
	 * @param unknown $number        	
	 * @return boolean
	 */
	protected function deductImpressionNum($campaign_id, $number) {
		$sql = "UPDATE md_campaign_limit SET total_amount_left = total_amount_left - :number WHERE campaign_id = :campaign_id AND total_amount_left>0";
		
		$cam = new CampaignLimit();
		$connection = $cam->getWriteConnection();
		$result = $connection->execute($sql, array (
				"number" => $number, 
				"campaign_id" => $campaign_id 
		));
		return $connection->affectedRows() > 0;
	}
	
	/**
	 * 判断ip是否合法
	 * @param unknown $ip
	 * @param string $include_priv_res
	 * @return boolean
	 */
	function isValidIp($ip, $include_priv_res = true) {
		return $include_priv_res ?
			filter_var($ip, FILTER_VALIDATE_IP) !== false :
			filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
	}
}