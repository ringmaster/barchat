<?php

class Option extends Model
{
	function __construct(){
		$this->table = 'options';
	}

	public static function create() {
		$o = new self();
		$o->fields = array(
			'grouping'=>'',
			'name' => '',
			'value' => '',
			'room' => '',
			'user_id' => 0,
		);
		return $o;
	}
	
	public function insert() {
		return parent::insert('options');
	}
	
	public function update() {
		return parent::update('options', array('grouping' => $this->grouping, 'name' => $this->name));
	}
	
	public function update_insert() {
		return parent::update_insert('options', array('grouping' => $this->grouping, 'name' => $this->name));
	}
	
	public static function get($grouping, $name) {
		$sql = "SELECT * FROM options WHERE grouping = :grouping AND name = :name AND (user_id = 0 OR user_id = :user_id) ORDER BY user_id DESC;";
		
		return DB::get()->row($sql, array('grouping'=>$grouping, 'name'=>$name, 'user_id'=>Auth::user_id()), __CLASS__);
	}
	
	function __toString() {
		return $this->value;
	}
}

?>