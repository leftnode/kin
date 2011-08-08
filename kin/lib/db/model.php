<?php namespace kin\db;
declare(encoding='UTF-8');

class model {

	private $types = array();
	private $values = array();
	private $compiled = false;

	protected $members = array();

	const type_int = 1;
	const type_float = 2;
	const type_string = 4;

	const status_enabled = 1;
	const status_disabled = 0;

	public function __construct($model=null) {
		$this->compile();
		if (!is_null($model)) {
			$this->load($model);
		}
	}



	public function __call($method, $argv) {
		$argc = count($argv);

		$method = strtolower($method);
		$k = substr($method, 4);

		if (0 === $argc) {
			// If the length is 0, assume this is a get()
			$v = $this->__get($k);
			return($v);
		} else {
			$v = current($argv);
			$this->__set($k, $v);
			return($this);
		}
	}

	public function __isset($k) {
		return(array_key_exists($k, $this->members));
	}

	public function __set($k, $v) {
		// When building this from a fetchObject() on PDO, the __set methods are
		// called before the constructor, and thus we need to compile the object
		// first if it isn't already. Don't worry, this only runs once.
		$this->compile();
		
		if (array_key_exists($k, $this->members)) {
			if ($this->is_type_int($k)) {
				$v = (int)$v;
			} elseif ($this->is_type_float($k)) {
				$v = (float)$v;
			} else {
				$v = (string)$v;
			}

			$this->values[$k] = $v;
		}

		return(true);
	}

	public function __get($k) {
		if (array_key_exists($k, $this->values)) {
			return $this->values[$k];
		}
		return null;
	}

	public function copy(model $model) {
		foreach ($model->get_values() as $member => $value) {
			$this->__set($member, $value);
		}
		return($this);
	}

	public function merge(model $model) {
		foreach ($model->get_values() as $member => $value) {
			if (isset($this->$member) && empty($this->$member)) {
				$this->__set($member, $value);
			}
		}
		return($this);
	}

	public function load($model) {
		if (is_array($model) || is_object($model)) {
			foreach ($model as $k => $v) {
				$this->__set($k, $v);
			}
		}
		return($this);
	}

	public function disable() {
		return($this->set_status(self::status_disabled));
	}

	public function enable() {
		return($this->set_status(self::status_enabled));
	}

	public function extract() {
		$member_values = array();
		$members = func_get_args();

		foreach ($members as $member) {
			if (isset($this->$member)) {
				$member_values[$member] = $this->__get($member);
			}
		}
		return($member_values);
	}

	// Traits
	public function is_compiled() {
		return($this->compiled);
	}
	
	public function is_saved() {
		return($this->id > 0);
	}

	public function is_disabled() {
		return($this->status == self::status_disabled);
	}
	
	public function is_enabled() {
		return($this->status == self::status_enabled);
	}


	// Getters
	public function get_members() {
		return($this->members);
	}

	public function get_values() {
		return($this->values);
	}



	private function compile() {
		if ($this->is_compiled()) {
			return($this);
		}
		
		// Take the predefined members and build out the arrays we'll need
		$types = array(self::type_int => true, self::type_float => true, self::type_string => true);

		foreach ($this->members as $member => $type) {
			$this->types[$member] = (!array_key_exists($type, $types) ? self::type_string : $type);

			$initial_value = '';
			if ($this->is_type_int($member)) {
				$initial_value = 0;
			} elseif ($this->is_type_float($member)) {
				$initial_value = 0.0;
			} elseif ($this->is_type_string($member)) {
				$initial_value = '';
			}

			$this->values[$member] = $initial_value;
			$this->members[$member] = true;
		}

		$this->compiled = true;
		return($this);
	}

	// Private traits
	private function is_type_int($member) {
		return($this->is_type($member, self::type_int));
	}

	private function is_type_float($member) {
		return($this->is_type($member, self::type_float));
	}

	private function is_type_string($member) {
		return($this->is_type($member, self::type_string));
	}

	private function is_type($member, $type) {
		return(array_key_exists($member, $this->types) && $type === $this->types[$member]);
	}

}