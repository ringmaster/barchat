<?php
/**
 * CiviCRM integration plugin for barchat.
 * 
 * Provides commands that allow users to query a CiviCRM instance using the API
 * to lookup contacts.
 * 
 * Commands:
 *  - /contact <search text>
 *    - Searches name and email fields to matches. If multiple matches, displays
 *      all matches. If single match, display detailed contact info.
 */
 
class Civicrm extends Plugin {
	
	function commands($cmds){
		$cmds[1]['contact'] = array(
			'%^(?:\?|/contact\s+)(?P<criteria>.+)$%i',
			array($this, '_contact'),
			CMD_LAST,
		);
		
		return $cmds;
	}

	function get_api() {
		static $api = NULL;

		if (!is_a($api, 'CiviCrmApi')) {
			include_once 'CiviCrmApi.class.inc';
			$api = new CiviCrmApi(array(
				'rest_url' => 'http://crm.rockriverstar.com/sites/all/modules/civicrm/extern/rest.php',
				'site_key' => 'd3678d8a682feb2c6034d9dbc08e8463',
				'api_key' => 'a7ad80804fa9f42b01d27e3456fa03ad',
			));
		}

		return $api;
	}
	
	function get_call_num($phone) {
		// Let's isolate a valid phone number for /call
		if (preg_match('%\(?\b[0-9]{3}\)?[-. ]?[0-9]{3}[-. ]?[0-9]{4}\b%', $phone, $matches)) {
			$callnum = preg_replace('%[^0-9]%', '', $matches[0]);
			return '<a href="#" onclick="send(\'/call '.$callnum.'\');return false;">'.$phone.'</a>';
		} else {
			return $phone;
		}
	}
	
	function _contact($params) {
		$criteria = $params['criteria'];
		$user = $params['user'];
		$channel = $params['channel'];
		$api = $this->get_api();

		if(is_numeric($criteria)) {
			$bycriteria = array('id' => $criteria);
		}
		else {
			$bycriteria = array('display_name' => $criteria);
		}

		$results = $api->requestArray('Contact/Get', $bycriteria);

		if (isset($results['Result'])) {
			$result = $results['Result'];
			if (isset($result['is_error'])) {
				if ($result['is_error']) {
					// Error message in $result['error_message']
					$msg = $result['error_message'];
				} else {
					// No error, just no results.
					$msg = 'No contacts match that criteria.';
				}
				Status::create()
					->data($msg)
					->type('system')
					->user_to($user->id)
					->cssclass('error')
					->channel($channel)
					->insert();
			} elseif (isset($result['contact_id'])) {
				// Single result was sent back.
				$callnum = $this->get_call_num($result['phone']);
				$msg = Utils::cmdout($params);
				$msg .= '<span class="crm-name">
					<a href="http://crm.rockriverstar.com/civicrm/contact/view?reset=1&cid='.$result['contact_id'].'" target="_blank">'
					.$result['display_name'].'</a>'
					.'</span>';
				$msg .= '<table class="crm-data">';
				$msg .= '<tr><td>Primary Phone: '.$this->get_call_num($result['phone']).'</td>';
				$msg .= '<td>Primary Email: '.$result['email'].'</td></tr>';
				$msg .= '<tr>'
					.'<td>Title: '.$result['job_title'].'</td>'
					.'<td>Employer: '.$result['current_employer'].'</td>'
					.'</tr>';
				$msg .= '</table>';
				Status::create()
					->data($msg)
					->user_id($user->id)
					->cssclass('crm-contact')
					->channel($channel)
					->insert();
			} else {
				// Multiple results.
				$msg = Utils::cmdout($params);
				$msg .= '<table><thead><tr><th>Name</th><th>Phone</th><th>Email</th></tr></thead>';
				foreach ($result as $contact) {
					if (!$contact['is_deleted']) {
						$callnum = $this->get_call_num($contact['phone']);
						$msg .= '<tr>';
						$msg .= '<td><a href="http://crm.rockriverstar.com/civicrm/contact/view?reset=1&cid='.$contact['contact_id'].'" target="_blank">'.$contact['display_name'].'</a></td>';
						$msg .= '<td>'.$this->get_call_num($contact['phone']).'</td>';
						$msg .= '<td><a href="mailto:'.$contact['email'].'">'.$contact['email'].'</a></td>';
						$msg .= '</tr>';
					}
				}
				$msg .= '</table>';
				Status::create()
					->data($msg)
					->user_id($user->id)
					->cssclass('crm-contact')
					->channel($channel)
					->insert();
			}
		} else {
			// Something has gone horribly wrong.
			Status::create()
				->data('Something has gone horribly wrong.')
				->type('system')
				->user_to($user->id)
				->cssclass('error')
				->channel($channel)
				->insert();
		}

		return true;
	}
}