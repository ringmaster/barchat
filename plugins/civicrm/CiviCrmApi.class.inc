<?php

class CiviCrmApi {

	protected $settings = array();

	/**
	 * Instantiate a new API object. You must specify, at least:
	 *
	 * - rest_url
	 * - site_key
	 * - api_key
	 *
	 * @param array $settings
	 */
	function __construct($settings = array()) {
		$this->settings = array_merge($this->settings, $settings);
	}

	function request($function, $params = array()) {
		$url = $this->getRequestUrl($function, $params);
		return $this->_request($url);
	}

	protected function _request($url) {
		if ($this->settings['debug']) {
			echo "Calling: $url\n";
		}
		if (function_exists('curl_init')) {
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			$response = curl_exec($ch);
			curl_close($ch);
		} else {
			$response = file_get_contents($url);
		}

		return $response;
	}

	function requestXML($function, $params = array()) {
		return simplexml_load_string($this->request($function, $params));
	}

	function requestArray($function, $params = array()) {
		return $this->objectsIntoArray($this->requestXML($function, $params));
	}

	/**
	 * From http://php.net/manual/en/book.simplexml.php
	 */
	protected function objectsIntoArray($arrObjData, $arrSkipIndices = array()) {
		$arrData = array();

		// if input is object, convert into array
		if (is_object($arrObjData)) {
			$arrObjData = get_object_vars($arrObjData);
		}

		if (is_array($arrObjData)) {
			foreach ($arrObjData as $index => $value) {
				if (is_object($value) || is_array($value)) {
					$value = $this->objectsIntoArray($value, $arrSkipIndices);
				}
				if (in_array($index, $arrSkipIndices)) {
					continue;
				}
				$arrData[$index] = $value;
			}
		}
		return $arrData;
	}

	/**
	 * Utility function that assembles a REST API call URL.
	 */
	function getRequestUrl($function, $params) {
		$params['q'] = "civicrm/$function";
		$params['key'] = $this->settings['site_key'];
		$params['api_key'] = $this->settings['api_key'];
		$query = http_build_query($params);

		return $this->settings['rest_url']."?$query";
	}

}