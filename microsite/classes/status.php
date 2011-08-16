<?php

class Status extends model
{
	function __construct()
	{
		$default_fields = array(
			'status' => 0,
			'type' => 'message',
			'channel' => '',
			'data' => '',
			'msgtime' => date('Y-m-d H:i:s'),
			'user_id' => 0,
			'cssclass' => '',
			'js' => '',
			'user_to' => 0,
			'received' => null,
		);
		if(!isset($this->fields)) {
			$this->fields = array();
		}
		$this->fields = array_merge($default_fields, $this->fields);
	}
	
	/**
	 * Status::create()
	 * 
	 * @return Status
	 */
	public static function create() {
		return new self();
	}
	
	public function insert() {
		parent::insert('presence');
	}
	
	public function update() {
		parent::update('presence', 'status');
	}
}

?>