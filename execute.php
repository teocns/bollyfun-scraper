<?php

include __DIR__.'/includes/lib/vendor/autoload.php';



class JavaScriptUnpacker
{
	private $unbaser;
	private $payload;
	private $symtab;
	private $radix;
	private $count;

	function Detect($source)
	{
		$source = preg_replace("/ /","",$source);
		preg_match("/eval\(function\(p,a,c,k,e,[r|d]?/", $source, $res);

		Debug::Write($res,"detection result");

		return (count($res) > 0);
	}

	function Unpack($source)
	{
		preg_match_all("/}\('(.*)', *(\d+), *(\d+), *'(.*?)'\.split\('\|'\)/",$source,$out);

		Debug::Write($out,"DOTALL", false);

		// Payload
		$this->payload = $out[1][0];
		Debug::Write($this->payload,"payload");
		// Words
		$this->symtab = preg_split("/\|/",$out[4][0]);
		Debug::Write($this->symtab,"symtab");
		// Radix
		$this->radix = (int)$out[2][0];
		Debug::Write($this->radix,"radix");
		// Words Count
		$this->count = (int)$out[3][0];
		Debug::Write($this->count,"count");

		if( $this->count != count($this->symtab)) return; // Malformed p.a.c.k.e.r symtab !

		//ToDo: Try catch
		$this->unbaser = new Unbaser($this->radix);

		$result = preg_replace_callback(
					'/\b\w+\b/',
						array($this, 'Lookup')
					,
					$this->payload
				);
		$result = str_replace('\\', '', $result);
		Debug::Write($result);
		$this->ReplaceStrings($result);
		return $result;
	}

	function Lookup($matches)
	{
		$word = $matches[0];
		$ub = $this->symtab[$this->unbaser->Unbase($word)];
		$ret = !empty($ub) ? $ub : $word;
		return $ret;
	}

	function ReplaceStrings($source)
	{
		preg_match_all("/var *(_\w+)\=\[\"(.*?)\"\];/",$source,$out);
		Debug::Write($out);
	}

}

class Unbaser
{
	private $base;
	private $dict;
	private $selector = 52;
	private $ALPHABET = array(
		52 => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOP',
		54 => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQR',
		62 => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
		95 => ' !"#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\]^_`abcdefghijklmnopqrstuvwxyz{|}~'
	);


	function __construct($base)
	{
		$this->base = $base;

		if($this->base > 62) $this->selector = 95;
		else if($this->base > 54) $this->selector = 62;
		else if($this->base > 52) $this->selector = 54;
	}

	function Unbase($val)
	{
		if( 2 <= $this->base && $this->base <= 36)
		{
			return intval($val,$this->base);
		}else{
			if(!isset($this->dict)){

				$this->dict = array_flip(str_split($this->ALPHABET[$this->selector]));
			}
			$ret = 0;
			$valArray = array_reverse(str_split($val));

			for($i = 0; $i < count($valArray) ; $i++)
			{
				$cipher = $valArray[$i];
				$ret += pow($this->base, $i) * $this->dict[$cipher];
			}
			return $ret;
			// UnbaseExtended($x, $base)
		}
	}

}


class Debug
{
	public static $debug = false;
	public static function Write($data, $header = "", $mDebug = true)
	{
		return;
		if(!self::$debug || !$mDebug) return;

		if(!empty($header))
			echo "<h4>".$header."</h4>";

		echo "<pre>";
		print_r($data);
		echo "</pre>";
	}

}

class Scraper {

	public function __construct(){
		$this->inst_dir = __DIR__;
		$this->configPhp();
	}

	public $scrapername = 'kshows';


	private $existing_video_ids = [];

	public function execute(){
		$outname = 'kshows-results.json';
		$video_localdir = '/www/wwwroot/videos/videos/indian';
		if(!file_exists($video_localdir))
			mkdir($video_localdir, 0755, true);
		if(!file_exists($video_localdir))
			throw new \Exception("Cannot access $video_localdir");
		$cache_dir = __DIR__.'/tmp';
		if(!file_exists($cache_dir))
			mkdir($cache_dir, 0755, true);
		if(!file_exists($cache_dir))
			throw new \Exception("Cannot access $cache_dir");
		\Request::setCacheHome($cache_dir);
		\Request::setDefaultOptions([
			CURLOPT_TIMEOUT => 30,
			CURLOPT_VERBOSE => false,
			//CURLOPT_RETURNTRANSFER => 0
		]); 

		$newline = '<br>';
		if(php_sapi_name() === 'cli')
			$newline = "\n";
		$keys_fn = __DIR__.'/keys.json';
		$keys = [];
		if(file_exists($keys_fn))
			$keys = json_decode(file_get_contents($keys_fn), true);
		$links = $this->fetchLinks();
		echo 'Found '.count($links).' links';
		$downloads = [];
		$threads = 1;
		foreach($links as $link_x => $link){
			$is_last_link = (count($links) - 1) == $link_x;
			echo "{$newline}";
			echo "{$newline}Fetching $link";
			$video_link = null;
			try {
				$video_link = $this->getVideoLinkFromPage($link);
			} catch (Exception $e) {
				echo "{$newline}Couldn't fetch '$link' ", $e->getMessage();
				continue;
			}
			if(is_null($video_link)){
				echo "{$newline}No video found";
				continue;
			}
			echo "{$newline}Found $video_link";
			$video_basename = basename($link);
			$video_name = $video_localdir.'/'.$video_basename.'.mp4';

			/*remove [4th-june-2021-full-] like strings from video file name.*/
			/* added by chirag on 04-06-2021 */
			/* new date format added on 21-06-2021 */
			/* new date format and episode removal added by XPedDev on 22-08-2021 */
			$date_formate = "(\d+)(rd|th|st|nd)-(january|february|march|april|may|june|july|august|september|october|november|december)-(\d+)-full-";
			$date_formate2 = "(\d+)(rd|th|st|nd)-(january|february|march|april|may|june|july|august|september|october|november|december)-(\d+)-";
			$video_name = preg_replace("/$date_formate/","$1-$3-$4-",strtolower($video_name));
			$video_name = preg_replace("/$date_formate2/","$1-$3-$4-",strtolower($video_name));
			$video_name = preg_replace("/-episode(-\d+)?/", "",strtolower($video_name));

			if(isset($keys[$video_basename]) || file_exists($video_name)){
			//if(file_exists($video_name)){
				echo "{$newline}Skipping duplicate $video_name";
				continue;
			}
			echo "{$newline}Queueing...";
			$video_name_tmp = $video_name.'.tmp';
			if (!is_readable($video_name_tmp)){
				continue;
			}
			if(file_exists($video_name_tmp)){
				try{
					unlink($video_name_tmp);
				}
				catch(Exception $e){
					echo "{$newline}Couldn't delete $video_name_tmp";
					continue;
				}
			}
			$downloads[$video_name] = compact('video_link', 'video_basename', 'video_name', 'video_name_tmp');
			if(count($downloads) >= $threads || $is_last_link){
				echo "{$newline}{$newline}Downloading...";
				$outs = [];
				$requests = [];
				foreach($downloads as $download_x => $download){
					extract($download);
					$outs[$download_x] = fopen($video_name_tmp, 'w+');
					$requests[$download_x] = ['options' => []];
					$requests[$download_x]['options'][CURLOPT_URL] = $video_link;
					$requests[$download_x]['options'][CURLOPT_FILE] = $outs[$download_x];
					$requests[$download_x]['options'][CURLOPT_TIMEOUT] = 100000;
					$requests[$download_x]['options'][CURLOPT_FOLLOWLOCATION] = TRUE;
					//$requests[$download_x]['options'][CURLOPT_RETURNTRANSFER] = TRUE;
					//$requests[$download_x]['options'][CURLOPT_VERBOSE] = 0;
					
				}
				$this->multiRequest($requests);
				foreach($downloads as $download_x => $download){
					$keys[$video_basename] = 1;
					extract($download);
					if(!file_exists($video_name_tmp)){
						echo "{$newline}Could not download";
						unset($keys[$video_basename]);
						continue;
					}
					if(filesize($video_name_tmp) <= 500000){
						echo "{$newline}File too small";
						unset($keys[$video_basename]);
						continue;
					}
					fclose($outs[$download_x]);

					/*remove [4th-june-2021-full-] like strings from video file name.*/
					/* added by chirag on 04-06-2021 */
					$date_formate = "(\d+)(rd|th|st|nd)-(january|february|march|april|may|june|july|august|september|october|november|december)-(\d+)-full-";
					$video_name = preg_replace("/$date_formate/","",strtolower($video_name));


					rename($video_name_tmp, $video_name);
					chown($video_name, 'www');
					file_put_contents($keys_fn, json_encode($keys, JSON_PRETTY_PRINT));
					echo "{$newline}Successfully downloaded to $video_name";
				}
				$downloads = [];
			}
		}
	}
	public function multiRequest(&$datas){
		$contents = [];
		foreach($datas as $data){
			$instance = curl_init();
			try{
				curl_setopt_array($instance, $data['options']);
			}
			catch (Exception $e){
				continue;
			}
			$instances[] = $instance;
		}
		$mh = curl_multi_init();
		foreach($instances as &$instance){
			if($instance !== false)
				curl_multi_add_handle($mh, $instance);
		}
		$running = null;
		do {
			curl_multi_exec($mh, $running);
		} while ($running);
		foreach($instances as &$instance){
			if($instance !== false)
				curl_multi_remove_handle($mh, $instance);
		}
		curl_multi_close($mh);
		foreach($instances as $offset=>&$instance){
			$datas[$offset]['content'] = curl_multi_getcontent($instance);
		}
	}
	public function fetchLinks(){
		$links = [];
		$url = "https://bollyfuntv.net/";
		$contents = \Request::send('GET', $url);
		if($this->checkForValidity($contents) === false)
			throw new \Exception('Could not retrieve page');
		$dom = new \Zend\Dom\Query($contents);
		try {
			$links_els = $dom->execute('.recent-item .post-thumbnail > a');
		} catch (\Exception $e){
			throw new \Exception('Could not retrieve page');
		}
		foreach($links_els as $links_el)
			$links[] = $links_el->getAttribute('href');
		return $links;
	}
	/**
	 * Download helper to download files in chunks and save it.
	 *
	 * @author Syed I.R <syed@lukonet.com>
	 * @link https://github.com/irazasyed
	 *
	 * @param	string	$srcName			Source Path/URL to the file you want to download
	 * @param	string	$dstName			Destination Path to save your file
	 * @param	integer $chunkSize		(Optional) How many bytes to download per chunk (In MB). Defaults to 1 MB.
	 * @param	boolean $returnbytes	(Optional) Return number of bytes saved. Default: true
	 *
	 * @return integer							 Returns number of bytes delivered.
	 */
	function downloadFile($srcName, $dstName, $chunkSize = 10, $returnbytes = true) {
		$chunksize = $chunkSize*(1024*1024); // How many bytes per chunk
		$data = '';
		$bytesCount = 0;
		$handle = fopen($srcName, 'rb');
		$fp = fopen($dstName, 'w');
		if ($handle === false) {
			return false;
		}
		while (!feof($handle)) {
			$data = fread($handle, $chunksize);
			fwrite($fp, $data, strlen($data));
			if ($returnbytes) {
				$bytesCount += strlen($data);
			}
		}
		$status = fclose($handle);
		fclose($fp);
		if ($returnbytes && $status) {
			return $bytesCount; // Return number of bytes delivered like readfile() does.
		}
		return $status;
	}


	private function isDuplicate($link){
		// Verify that existing_video_ids does not include $link
		if (in_array($link, $this->existing_video_ids)) {
			return true;
		}
		return false; 
	}
	public function getVideoLinkFromPage($link){
		$contents = \Request::send('GET', $link);
		if($this->checkForValidity($contents) === false)
			throw new \Exception('Could not retrieve page');
		//echo "Got contents";
		preg_match('/vkspeed.php\?id=([a-z0-9]+)/', $contents, $match);

		if(empty($match))return null;

		$video_id = $match[1];
		echo "Found video id $video_id";
		// Don't allow duplicate
		if ($this->isDuplicate($video_id)) {
			echo "Skipping duplicate video id: $video_id";
			return null;
		}
		#echo $video_id;
		$embed_url = "https://vkspeed.com/embed-$video_id.html";
		

		$contents = \Request::send('GET', $embed_url);
		if($this->checkForValidity($embed_url) === false)
			throw new \Exception('Could not retrieve page');

		$embed_script = "/(eval\(function\(p,a,c,k,e,d\){[.\s\S]+?)<\/script>/";
		preg_match($embed_script, $contents, $match);

		if(empty($match))return null;
		$javascript = $match[1];
		$myPacker = new JavaScriptUnpacker();
		$unpacked = $myPacker->Unpack($javascript);
		$embed_json = "/sources:(\[[^\]]+?\])/";
		$embed_file = '/"([^"]+.mp4)"/';
		preg_match($embed_file, $unpacked, $match);
		if(empty($match))return null;
		$video_link = $match[1];
		return $video_link;
	}

	public function checkForValidity(&$contents, $term = null){
		if(empty($contents))
			return false;
		return true;
	}

	public function sluggify($text){
		// replace non letter or digits by -
		$text = preg_replace('~[^\pL\d]+~u', '-', $text);
		// transliterate
		$text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
		// remove unwanted characters
		$text = preg_replace('~[^-\w]+~', '', $text);
		// trim
		$text = trim($text, '-');
		// remove duplicate -
		$text = preg_replace('~-+~', '-', $text);
		// lowercase
		$text = strtolower($text);
		if (empty($text)) {
			return 'n-a';
		}
		return $text;
	}
	public function TrimArray($Input){
		if (!is_array($Input))
			return trim($Input);
		return array_map([$this, 'TrimArray'], $Input);
	}

	public function configPhp(){
		ini_set('memory_limit', '-1');
		ini_set('max_input_time', '-1');
		ini_set('max_execution_time', '-1');
		ini_set("error_log", __DIR__."/error_log");
		set_time_limit(0);
		@ob_end_clean();
		ob_implicit_flush();
		ini_set('display_errors', '1');
		error_reporting(E_ALL);
	}
	public function getNodeDom(\DOMNode $node){
		$content = "";
		$children	= $node->childNodes;
		foreach ($children as $child){
			$content .= $node->ownerDocument->saveHTML($child);
		}
		$content = $node->C14N();
		$content = '<!doctype html><meta charset="UTF-8">'.$content;
		$dom = new \Zend\Dom\Query($content);
		return $dom;
	}

}

$scraper = new \Scraper();
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'execute';
$scraper->{$action}();


// $links = $scraper->getVideoLinkFromPage('https://bollyfuntv.net/crime-patrol-29th-october-2021-full-episode-537/');

// echo "\n";
// echo $links;
// echo "\n";



