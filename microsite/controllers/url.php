<?php

class UrlController {
	private $_headers= '';
	
	function tos3($href, $filename = '') {
		$access = DB::get()->assoc("SELECT name, value FROM options WHERE grouping = 'Amazon Web Services'");
		$bucketname = $access['S3 Bucket Name'];

		$s3 = new AmazonS3($access['AWS Access Key ID'], $access['AWS Secret Access Key']);

		if($filename == '') {
			$basename = basename($href);
			if($qpos = strpos($basename, '?')) {
				$basename = substr($basename, 0, $qpos);
			}
		}
		else {
			$basename = $filename;
		}
		
		$s3filename = strtolower(preg_replace('%\W+%', '', (Auth::user()->username))) . '/' . date('Ym') . '/' . dechex(crc32($href)) . '_' . $basename;
		$headers = get_headers($href, 1);
		$opt = array(
			'filename' => $s3filename,
			'body' => file_get_contents($href),
			'contentType' => $headers['Content-Type'],
			'acl' => S3_ACL_OPEN,
		);
		$s3->create_object($bucketname, $opt);
		$href = "http://{$bucketname}.s3.amazonaws.com/{$s3filename}";
		return $href;
	}

	function get_thumbnail($href) {
		$href = str_replace('&', '&amp;', $href);
		
		$apikey = DB::get()->val("SELECT value FROM options WHERE grouping = 'Thumbnails' AND name = 'Bluga API Key'");
		
		$thumbrequest = <<< REQUEST
<webthumb>
	<apikey>{$apikey}</apikey>
	<request>
		<url>{$href}</url>
		<outputType>png</outputType>
		<fullthumb>1</fullthumb>
		<width>1280</width>
		<height>1024</height>
		<notify>http://{$_SERVER['HTTP_HOST']}/url/fetch</notify>
	</request>
</webthumb>
REQUEST;

		$result = self::execute( 'http://webthumb.bluga.net/api.php', 'POST', $thumbrequest);
		try{
			$xml = new SimpleXMLElement( $result );
		}
		catch(Exception $e) {
			echo $result;
		}
		$job = (string)$xml->jobs->job;
		return $job;
		/*
		$xml = new SimpleXMLElement( $result );
		$job = (string)$xml->jobs->job;
		$this->db->query('UPDATE parts SET job = ? WHERE id = ?', array($job, $part->id));
		*/
	}
	
	public function cache_thumb($job, $user_id) {
		$keepsizes = array('thumb_medium.', 'thumb_large.');
		
		$apikey = DB::get()->val("SELECT value FROM options WHERE grouping = 'Thumbnails' AND name = 'Bluga API Key'");
		$username = DB::get()->val('SELECT username FROM users where id = ?', array($user_id));
		
		$statusrequest = <<< STATUSREQ
<webthumb>
	<apikey>{$apikey}</apikey>
	<status>
		<job>$job</job>
	</status>
</webthumb>
STATUSREQ;

		//header('Content-type: text/plain');
		//echo "{$statusrequest}\n";
		$xml = new SimpleXMLElement(self::execute( 'http://webthumb.bluga.net/api.php', 'POST', $statusrequest));
		//echo "$jobs\n";
		//echo $xml->asXML();
		$href = false;
		
		foreach($xml->jobStatus->status as $status){
			if((string)$status == 'Complete') {
				$zipurl = $status['pickup'];
				
				$zipfiledata = self::execute($zipurl);
				$zipfile = tempnam(sys_get_temp_dir(), 'thm');
				
				file_put_contents($zipfile, $zipfiledata);
				if(file_exists($zipfile)) {
				
					$zip = zip_open($zipfile);
					$names = array();
					while($zip_entry = zip_read($zip)) {
						$size = zip_entry_filesize($zip_entry);
						$zdata = zip_entry_read($zip_entry, $size);
						$zfile = zip_entry_name($zip_entry);
						$keep = false;
						foreach($keepsizes as $size) {
							if(strpos($zfile, $size) !== false) {
								$keep = true;
								break;
							}
						}
						if(strpos($zfile, '-') === false) {
							$keep = true;
						}
						
						if($keep) {
	
							$access = DB::get()->assoc("SELECT name, value FROM options WHERE grouping = 'Amazon Web Services'");
							$bucketname = $access['S3 Bucket Name'];
					
							$s3 = new AmazonS3($access['AWS Access Key ID'], $access['AWS Secret Access Key']);
							$s3filename = strtolower(preg_replace('%\W+%', '', $username)) . '/' . date('Ym') . '/webthumb_';
							$s3filename .= basename($zfile);
							$s3filename = trim($s3filename, '/');
							$headers = get_headers($href, 1);
							$opt = array(
								'filename' => $s3filename,
								'body' => $zdata,
								'contentType' => 'image/png',
								'acl' => S3_ACL_OPEN,
							);
							$s3->create_object($bucketname, $opt);
							$href = "http://{$bucketname}.s3.amazonaws.com/{$s3filename}#{$username}:{$user_id}";
						}

					}
					zip_close($zip);
					unlink($zipfile);
				}
			}
		}
		return $href;
	}
	
	private function execute( $url, $method = 'GET', $body = '', $headers = array(), $timeout = 30)
	{
		$merged_headers= array();
		foreach ( $headers as $k => $v ) {
			$merged_headers[] = $k . ': ' . $v;
		}
		
		$ch = curl_init();
		
		curl_setopt( $ch, CURLOPT_URL, $url ); // The URL.
		curl_setopt( $ch, CURLOPT_HEADERFUNCTION, array('UrlController', '_headerfunction' ) ); // The header of the response.
		curl_setopt( $ch, CURLOPT_MAXREDIRS, 5 ); // Maximum number of redirections to follow.
		curl_setopt( $ch, CURLOPT_CRLF, true ); // Convert UNIX newlines to \r\n.
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true ); // Follow 302's and the like.
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true ); // Return the data from the stream.
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, $timeout );
		curl_setopt( $ch, CURLOPT_TIMEOUT, $timeout );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $merged_headers ); // headers to send
		
		if ( $method === 'POST' ) {
			curl_setopt( $ch, CURLOPT_POST, true ); // POST mode.
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );
		}
		
		$body= curl_exec( $ch );
		
		if ( curl_errno( $ch ) !== 0 ) {
			echo curl_error($ch);
			die('CURL error');
		}
		
		if ( curl_getinfo( $ch, CURLINFO_HTTP_CODE ) !== 200 ) {
			die('Not 200 response code');
		}
		
		curl_close( $ch );
		
		// this fixes an E_NOTICE in the array_pop
		$tmp_headers = explode("\r\n\r\n", substr( $this->_headers, 0, -4 ) );
		
		$response_headers = array_pop( $tmp_headers );
		$response_body = $body;
		
		return $body;
	}
	
	public function _headerfunction( $ch, $str ) {
	//	$this->_headers .= $str;
		
		return strlen( $str );
	}

}

?>
