<?php

class Request {
	public static $info = null;
	public static $errno = null;
	public static $rotated_proxiesnum = 0;
	public static $rotated_proxies = null;
	public static $rotated_proxies_type = null;
	public static $rotated_interfaces = null;
	public static $sleep_after_requests = null;
	public static $cache_home = __DIR__;
	public static $overwrite_cache = false;
	public static $default_options = array(
		CURLOPT_USERAGENT=>'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.71 Safari/537.36',
		CURLOPT_REFERER=>'http://google.com',
		CURLOPT_VERBOSE => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HEADER => 0,
		CURLOPT_POST => false,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_COOKIEFILE => 'cookie.txt',
		CURLOPT_COOKIEJAR => 'cookie.txt',
		CURLOPT_CONNECTTIMEOUT => 100,
		CURLOPT_TIMEOUT => 100,
	);

	public static function mergeOptions(){
		$args = array_filter(func_get_args());
		return call_user_func_array('array_replace', array_reverse($args));
	}

	public static function setDefaultOptions($options){
		self::$default_options = self::mergeOptions($options, self::$default_options);
	}
	public static function getDefaultOptions(){
		return self::$default_options;
	}
	public static function setDefaultOption($key, $value){
		self::$default_options[$key] = $value;
	}
	public static function getDefaultOption($key){
		return self::$default_options[$key];
	}

	public static function setCacheHome($cache_home){
		self::$cache_home = $cache_home;
	}
	public static function setOverwriteCache($overwrite_cache){
		self::$overwrite_cache = $overwrite_cache;
	}
	public static function getCacheHome(){
		return self::$cache_home;
	}
	public static function getOverwriteCache(){
		return self::$overwrite_cache;
	}

	public static function setProxy($ip_addr, $proxy_type=7){
		self::setDefaultOptions(array(CURLOPT_PROXY => $ip_addr, CURLOPT_PROXYTYPE => $proxy_type));
	}
	public static function setTorProxy($port=9150){
		self::setProxy('127.0.0.1:'.$port, 7);
	}
	public static function rotateProxies($rotated_proxies, $rotated_proxies_type=7){
		self::$rotated_proxies = $rotated_proxies;
		self::$rotated_proxies_type = $rotated_proxies_type;
	}

	public static function setInterface($interface){
		self::setDefaultOptions(array(CURLOPT_INTERFACE => $interface));
	}
	public static function rotateInterfaces($rotated_interfaces){
		self::$rotated_interfaces = $rotated_interfaces;
	}

	public static function sleepAfterRequests($milliseconds){
		self::$sleep_after_requests = $milliseconds;
	}

	public static function send($method, $url, $data=array(), $headers=array(), $options=null){
		if(!is_null(self::$rotated_proxies)){
			$proxy = self::$rotated_proxies[self::$rotated_proxiesnum];
			self::setProxy($proxy, self::$rotated_proxies_type);
			self::$rotated_proxiesnum++;
			if(!isset(self::$rotated_proxies[self::$rotated_proxiesnum]))
				self::$rotated_proxiesnum = 0;
		}
		if(!is_null(self::$rotated_interfaces)){
			$interface_key = array_rand(self::$rotated_interfaces);
			$interface = self::$rotated_interfaces[$interface_key];
			self::setInterface($interface);
		}
		$options = self::mergeOptions(array(CURLOPT_URL => $url), $options, self::$default_options);
		if($method=='GET')
			$options = self::mergeOptions(array(CURLOPT_CUSTOMREQUEST => 'GET'), $options);
		if($method=='POST')
			$options = self::mergeOptions(array(CURLOPT_POST => true), $options);
		if(!empty($data))
			$options = self::mergeOptions(array(CURLOPT_POSTFIELDS => $data), $options);
		if(!empty($headers))
			$options = self::mergeOptions(array(CURLOPT_HTTPHEADER => $headers), $options);
		$ch = curl_init();
		curl_setopt_array($ch, $options);
		$response = curl_exec($ch);
		self::$errno = null;
		if($response === false)
			self::$errno = curl_errno($ch);
		self::$info = curl_getinfo($ch);
		curl_close($ch);
		if(!is_null(self::$sleep_after_requests)){
			usleep(self::$sleep_after_requests*1000);
		}
		return $response;
	}
	public static function get($url, $data=array(), $headers=array(), $options=null){
		return self::send('GET', $url, $data, $headers, $options);
	}
	public static function post($url, $data=array(), $headers=array(), $options=null){
		return self::send('POST', $url, $data, $headers, $options);
	}

	public static function safe_request($url, $type='get', $data=null, $options=null, $retry_times=3, $retry_interval=5000){
		$response = self::$type($url, $data, $options);
		if(empty($response)){
			if($retry_times>0){
				usleep($retry_interval*1000);
				return self::safe_request($url, $type, $data, $options, $retry_times-1, $retry_interval);
			}
			return null;
		}
		return $response;
	}
	public static function safe_get($url, $data=null, $options=null, $retry_times=3, $retry_interval=5000){
		return self::safe_request($url, 'get', $data, $options, $retry_times, $retry_interval);
	}
	public static function safe_post($url, $data=null, $options=null, $retry_times=3, $retry_interval=5000){
		return self::safe_request($url, 'post', $data, $options, $retry_times, $retry_interval);
	}

	public static function getCacheKey($type='get', $url, $data=null, $headers=null, $options=null, $cache_home=null, $overwrite_cache=null){
		if(is_null($cache_home))$cache_home = self::$cache_home;
		if(is_null($overwrite_cache))$overwrite_cache = self::$overwrite_cache;
		$cache_dir = $cache_home . '/cache';
		if(!file_exists($cache_dir))mkdir($cache_dir, 0755, true);
		$cache_fn = $cache_dir.'/'.md5($url.$type.json_encode($data));
		return $cache_fn;
	}
	public static function cacheSend($type='get', $url, $data=null, $headers=null, $options=null, $cache_home=null, $overwrite_cache=null){
		if(is_null($cache_home))$cache_home = self::$cache_home;
		if(is_null($overwrite_cache))$overwrite_cache = self::$overwrite_cache;
		$cache_dir = $cache_home . '/cache';
		if(!file_exists($cache_dir))mkdir($cache_dir, 0755, true);
		$cache_fn = $cache_dir.'/'.md5($url.$type.json_encode($data));
		if(!$overwrite_cache and file_exists($cache_fn))return file_get_contents($cache_fn);
		$content = self::send($type, $url, $data, $headers, $options);
		if(is_null($content))return null;
		file_put_contents($cache_fn, $content);
		return $content;
	}
	public static function cache_get($url, $data=null, $options=null, $cache_home=null, $overwrite_cache=null){
		return self::cache_request($url, 'get', $data, $options, $cache_home, $overwrite_cache);
	}
	public static function cache_post($url, $data=null, $options=null, $cache_home=null, $overwrite_cache=null){
		return self::cache_request($url, 'post', $data, $options, $cache_home, $overwrite_cache);
	}

	public static function cache_safe_request($url, $type='get', $data=null, $options=null, $cache_home=null, $overwrite_cache=null, $retry_times=3, $retry_interval=5000){
		if(is_null($cache_home))$cache_home = self::$cache_home;
		if(is_null($overwrite_cache))$overwrite_cache = self::$overwrite_cache;
		$cache_dir = $cache_home . '/cache';
		if(!file_exists($cache_dir))mkdir($cache_dir, 0755, true);
		$cache_fn = $cache_dir.'/'.md5($url.$type.json_encode($data));
		if(!$overwrite_cache and file_exists($cache_fn))return file_get_contents($cache_fn);
		$content = self::safe_request($url, $type, $data, $options, $retry_times, $retry_interval);
		if(is_null($content))return null;
		file_put_contents($cache_fn, $content);
		return $content;
	}
	public static function cache_safe_get($url, $data=null, $options=null, $cache_home=null, $overwrite_cache=null, $retry_times=3, $retry_interval=5000){
		return self::cache_safe_request($url, 'get', $data, $options, $cache_home, $overwrite_cache, $retry_times, $retry_interval);
	}
	public static function cache_safe_post($url, $data=null, $options=null, $cache_home=null, $overwrite_cache=null, $retry_times=3, $retry_interval=5000){
		return self::cache_safe_request($url, 'post', $data, $options, $cache_home, $overwrite_cache, $retry_times, $retry_interval);
	}
}

if (!defined('PHP_QUERY_RFC1738')) {
	define('PHP_QUERY_RFC1738', 1);
}

if (!defined('PHP_QUERY_RFC3986')) {
	define('PHP_QUERY_RFC3986', 2);
}

if (!function_exists('http_build_query')) {
	function http_build_query($query_data, $numeric_prefix = null, $arg_separator = '&', $enc_type = PHP_QUERY_RFC1738, $recursive_prefix = null) {
		$query = '';

		// Check for arg_separator.output INI setting
		$arg_separator_ini = ini_get('arg_separator.output');
		if ('&' == $arg_separator && $arg_separator_ini) {
			$arg_separator = $arg_separator_ini;
		}

		// Loop thru query data
		foreach ($query_data as $key => $value) {
			// Handle numeric_prefix
			if (!$recursive_prefix && $numeric_prefix && is_int($key)) {
				$key = $numeric_prefix . $key;
			}

			// Handle recursive sub-arrays
			if ($recursive_prefix) {
				$key = $recursive_prefix . http_build_query_polyfill_encode('[' . $key . ']', $enc_type);
			}

			if (is_array($value)) {
				// Run recursively if necessary
				$query .= http_build_query($value, $numeric_prefix, $arg_separator, $enc_type, $key);
			} else {
				// Otherwise just encode
				$query .= $key . '=' . http_build_query_polyfill_encode($value, $enc_type) . $arg_separator;
			}
		}

		$query = rtrim($query, $arg_separator);
		return $query;
	}

	function http_build_query_polyfill_encode($value, $enc_type) {
		if (PHP_QUERY_RFC3986 == $enc_type) {
			return rawurlencode($value);
		}

		return urlencode($value);
	}
}