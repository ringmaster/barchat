<?php
class NetPlugin extends Plugin 
{
	function commands($cmds) 
	{
		$cmds[1]['ip'] = array('%^/ip\s+(?P<domain>[^:/\s]+)\b%i', array($this, '_ip'), CMD_LAST);
		$cmds[1]['whois'] = array('%^/whois\s+(?P<domain>[^:/\s]+)\b%i', array($this, '_whois'), CMD_LAST);
		$cmds[1]['ns'] = array('%^/ns\s+(?P<domain>[^:/\s]+)\b%i', array($this, '_ns'), CMD_LAST);
		$cmds[1]['dig'] = array('%^/dig\s+(?P<domain>[^:/\s]+)\s+(?P<record>any|all|a|ns|mx|cname|ns|ptr|txt|hinfo|soa|aaaa|a6|srv|naptr)\b%i', array($this, '_dig'), CMD_LAST);
		return $cmds;
	}
	
	function _ip($params) 
	{
		$user = $params['user'];
		$channel = $params['channel'];
		$domain = $params['domain'];
		
		if(strpos($domain, '.') === false) {
			$domain .= '.rockriverstar.com';
		}
		
		$output = Utils::cmdout($params);
		$host = gethostbyname($domain);
		$output .= $host;
		
		$rrs = dns_get_record(implode('.',array_reverse(explode('.', $host))).'.in-addr.arpa.', DNS_PTR);
		$revnames=array();
		foreach($rrs as $rr) {
			$revnames[]=$rr['target'];
		}
		if(count($revnames)) {
			$output .= '<br/>' . implode('.', $revnames);
		}

		Status::create()
			->data($output)
			->user_id($user->id)
			->channel($channel)
			->type('message')
			->cssclass('net ip')
			->insert();
		
		return true;
	}

	function _whois($params) 
	{
		$user = $params['user'];
		$channel = $params['channel'];
		$domain = $params['domain'];
		
		if(strpos($domain, '.') === false) {
			$domain .= '.rockriverstar.com';
		}
		
		$output = Utils::cmdout($params);
		$cmd = 'whois ' . escapeshellarg($domain);
		$output .= '<pre style="font-size:xx-small;">'. shell_exec($cmd) . '</pre>';
		
		Status::create()
			->data($output)
			->user_id($user->id)
			->channel($channel)
			->type('message')
			->cssclass('net ip')
			->insert();
		
		return true;
	}

	function _ns($params) 
	{
		$user = $params['user'];
		$channel = $params['channel'];
		$domain = $params['domain'];
		
		if(strpos($domain, '.') === false) {
			$domain .= '.rockriverstar.com';
		}
		
		$output = Utils::cmdout($params);
		if(preg_match('#\.([^.]+)$#', $domain, $matches)) {
			$cmd = 'dig +short ns ' . escapeshellarg($matches[1]) . '. | head -n 1';
			$rootserver = trim(shell_exec($cmd));
			$output .= 'Root server queried:' . $rootserver . "<br>";
			$cmd = 'dig musictogetherofcharlotte.com ns @' . $rootserver . " | awk '/NS\t/ {print \$5}'";
			$output .= "Name servers:\n<pre>". shell_exec($cmd). '</pre>';
		}
		else {
			$output .= 'Didn\'t find TLD on domain.';
		}
		
		Status::create()
			->data($output)
			->user_id($user->id)
			->channel($channel)
			->type('message')
			->cssclass('net ip')
			->insert();
		
		return true;
	}
	
	function _dig($params) 
	{
		$user = $params['user'];
		$channel = $params['channel'];
		$domain = $params['domain'];
		$record = $params['record'];
		
		if(strpos($domain, '.') === false) {
			$domain .= '.rockriverstar.com';
		}
		
		$output = Utils::cmdout($params);

		$records = array(
			'a' => DNS_A, 
			'cname' => DNS_CNAME, 
			'hinfo' => DNS_HINFO, 
			'mx' => DNS_MX, 
			'ns' => DNS_NS, 
			'ptr' => DNS_PTR, 
			'soa' => DNS_SOA, 
			'txt' => DNS_TXT, 
			'aaaa' => DNS_AAAA, 
			'srv' => DNS_SRV, 
			'naptr' => DNS_NAPTR, 
			'a6' => DNS_A6, 
			'all' => DNS_ALL,
			'and' => DNS_ANY,
		);
		
		$record = $records[strtolower($record)];
		
		$result = dns_get_record($domain, $record);
		if(count($result) > 0) {
			$cols = array_keys(reset($result));
			$output .= '<table class="net"><tr>';
			foreach($cols as $col) {
				$output .= '<th>' . $col . '</th>';
			}
			$output .= '</tr>';
			foreach($result as $res) {
				$output .= '<tr>';
				foreach($cols as $col) {
					$output .= '<td>' . $res[$col] . '</td>';
				}
				$output .= '</tr>';
			}
			$output .= '</table>';
		}
		else {
			$output .= 'No results found.';
		}
		
		//$output .= '<pre>' . print_r(,1) . '</pre>';

		Status::create()
			->data($output)
			->user_id($user->id)
			->channel($channel)
			->type('message')
			->cssclass('net ip')
			->insert();
		
		return true;
	}
	
}
?>
