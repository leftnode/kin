<?php namespace kin;
require_once(__DIR__.'/../exceptions/unrecoverable.php');

class router {

	public $path = "";
	public $request_method = "";
	public $routes = array();
	public $exception_routes = array();
	public $route = null;
	
	const route_404 = 404;
	
	public function __construct() { }

	public function route() {
		$this->check_routes()
			->check_exception_routes()
			->check_exception_route_404_exists();
		
		$this->route = $this->exception_routes[self::route_404];
		if (empty($this->path) || empty($this->request_method)) {
			return(true);
		}
		
		foreach ($this->routes as $r) {
			if ($this->check_route_matches_path($r->get_method(), $r->get_compiled_route())) {
				$this->format_route_arguments();
				$this->route = $r->set_arguments($this->arguments);
				break;
			}
		}
		return(true);
	}
	
	public function set_routes(array $routes) {
		$this->routes = $this->filter_out_invalid_routes($routes);
		return($this->check_routes());
	}
	
	public function set_exception_routes(array $exception_routes) {
		$this->exception_routes = $this->filter_out_invalid_routes($exception_routes);
		return($this->check_exception_routes()
			->check_exception_route_404_exists());
	}
	
	public function set_path($path) {
		$this->path = trim($path);
		return($this);
	}
	
	public function set_request_method($request_method) {
		$this->request_method = strtoupper($request_method);
		return($this);
	}

	public function get_arguments() {
		return $this->arguments;
	}
	
	public function get_routes() {
		return($this->routes);
	}
	
	public function get_route() {
		return($this->route);
	}
	
	
	
	private function filter_out_invalid_routes($routes) {
		return array_filter($routes, function($r) {
			return($r instanceof \kin\route);
		});
	}
	
	private function check_routes() {
		if (0 === count($this->routes)) {
			throw new unrecoverable("The router requires at least one \\kin\\route object set before it can route properly.");
		}
		return($this);
	}
	
	private function check_exception_routes() {
		if (0 === count($this->exception_routes)) {
			throw new unrecoverable("The router requires at least one \\kin\\route object set as an exception route before it can route properly.");
		}
		return($this);
	}
	
	private function check_exception_route_404_exists() {
		if (!array_key_exists(self::route_404, $this->exception_routes)) {
			throw new unrecoverable("The router requires at least one \\kin\\route object set as a 404 exception route before it can route properly.");
		}
		return($this);
	}
	
	private function check_request_methods_match($route_request_method) {
		return($route_request_method === $this->request_method);
	}

	private function check_routes_match($compiled_route) {
		$this->arguments = array();
		return(preg_match_all($compiled_route, $this->path, $this->arguments, PREG_SET_ORDER) > 0);
	}
	
	private function check_route_matches_path($route_request_method, $compiled_route) {
		return($this->check_request_methods_match($route_request_method) && $this->check_routes_match($compiled_route));
	}
	
	private function format_route_arguments() {
		if (isset($this->arguments[0])) {
			$this->arguments = $this->arguments[0];
			array_shift($this->arguments);
			$this->arguments = array_values($this->arguments);
		}
		return($this);
	}
	
}
