<?php namespace kin\db;
declare(encoding='UTF-8');

class pdo extends \PDO {

	private $stmt = null;
	private $save_stmt = null;

	private $object = null;
	private $query_hash = null;

	public function __construct($dsn, $username=null, $password=null, $options=array()) {
		parent::__construct($dsn, $username, $password, $options);
		$this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		$this->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
	}

	// Helper functions
	public function id() {
		return $this->lastInsertId();
	}

	public function now($time=0, $short=false) {
		$time = (0 === $time ? time() : $time);
		$format = (false === $short ? 'Y-m-d H:i:s' : 'Y-m-d');
		$date = date($format, $time);

		return $date;
	}

	public function prep($query) {
		$this->stmt = $this->prepare($query);
		return $this;
	}

	// Searching methods
	public function find_all($object='stdClass', $parameters=array()) {
		if (!is_object($this->stmt)) {
			return array();
		}

		$this->bind_parameters($this->stmt, $parameters)
			->execute();

		return $this->stmt->fetchAll(\PDO::FETCH_CLASS, $object);
	}

	public function select($query, $parameters=array()) {
		$this->stmt = $this->prep($query)
			->bind_parameters($this->stmt, $parameters);
		$this->stmt->execute();
		return $this->stmt;
	}
	
	public function select_exists($query, $parameters=array()) {
		$field_count = (int)$this->select($query, $parameters)
			->fetchColumn(0);
		
		return (0 === $field_count ? false : true);
	}

	public function select_one($query, $parameters=array(), $object='stdClass') {
		return $this->select($query, $parameters)
			->fetchObject($object);
	}

	// Modification methods
	public function delete(model $model) {
		if (!$model->is_saved()) {
			return false;
		}

		$table = $this->get_table(get_class($model));
		$query = 'delete from '.$table.' where id = :id';

		$modified = $this->modify($query, array('id' => $model->get_id()));
		return $modified;
	}

	public function modify($query, $parameters=array()) {
		$this->stmt = $this->prep($query)
			->bind_parameters($this->stmt, $parameters);
		$modified = $this->stmt->execute();
		return $modified;
	}

	public function save(model $model) {
		$is_insert = false;
		$table = $this->get_table(get_class($model));

		if (!$model->is_saved()) {
			if (isset($model->created)) {
				$model->set_created($this->now());
			}

			$values = $model->get_values();
			$members = array_keys($values);
			$members_string = implode(',', $members);

			$named_parameters = array_map(function($v) {
				return ':'.$v;
			}, $members);
			$named_parameters_string = implode(',', $named_parameters);

			$query = 'insert into '.$table.'('.$members_string.') values ('.$named_parameters_string.')';
			$is_insert = true;
		} else {
			if (isset($model->updated)) {
				$model->set_updated($this->now());
			}

			$values = $model->get_values();

			if (isset($model->created)) {
				unset($values['created']);
			}

			$members = array_keys($values);
			$named_parameters = array_map(function($v) {
				return ($v.' = :'.$v);
			}, $members);
			$named_parameters_string = implode(',', $named_parameters);

			$query = 'update '.$table.' set '.$named_parameters_string.' where id = :pid';
			$values['pid'] = $values['id'];
		}

		$query_hash = sha1($query);
		if ($query_hash !== $this->query_hash) {
			$this->query_hash = $query_hash;
			$this->save_stmt = $this->prepare($query);
		}

		if (is_object($this->save_stmt)) {
			$this->save_stmt = $this->bind_parameters($this->save_stmt, $values);
			$this->save_stmt->execute();

			if ($is_insert) {
				$id = $this->id();
				$model->set_id($id);
			}
		}

		return $model;
	}

	public function table_exists($table_to_test) {
		$table_exists = false;
		$result_tables = $this->query('SHOW TABLES');
		while ($table_row = $result_tables->fetch()) {
			$table = trim(current($table_row));
			if ($table_to_test === $table) {
				$table_exists = true;
				break;
			}
		}

		return $table_exists;
	}



	private function bind_parameters(\PDOStatement $stmt, array $parameters) {
		foreach ($parameters as $parameter => $value) {
			$type = \PDO::PARAM_STR;
			if (is_int($value)) {
				$type = \PDO::PARAM_INT;
			} elseif (is_bool($value)) {
				$type = \PDO::PARAM_BOOL;
			} elseif (is_null($value)) {
				$type = \PDO::PARAM_NULL;
			}
			$stmt->bindValue($parameter, $value, $type);
		}

		return $stmt;
	}

	private function get_table($class) {
		$table_class = strtolower($class);
		$table_bits = explode('\\', $table_class);
		$table = end($table_bits);

		return $table;
	}

}