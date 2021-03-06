<?php namespace kin;
require_once(__DIR__."/http/request.php");
require_once(__DIR__."/http/response.php");

require_once(__DIR__."/app/compiler.php");
require_once(__DIR__."/app/dispatcher.php");
require_once(__DIR__."/app/helper.php");
require_once(__DIR__."/app/route.php");
require_once(__DIR__."/app/router.php");
require_once(__DIR__."/app/settings.php");

require_once(__DIR__."/view.php");

class app {

	public $controller = null;
	public $helper = null;
	public $request = null;
	public $response = null;
	public $route = null;
	public $settings = null;
	public $view = null;
	public $start_time = 0.0;
	public $routes = array();
	public $exception_routes = array();
	
	public function __construct() {
		$this->start_time = microtime(true);
		$this->build_helper()
			->build_request()
			->build_response();
	}
	
	public function attach_all_routes(array $routes, array $exception_routes) {
		$this->routes = $routes;
		$this->exception_routes = $exception_routes;
		return($this);
	}
	
	public function attach_settings(settings $settings) {
		$this->settings = $settings;
		$this->settings->compile();
		
		$this->helper->attach_settings($this->settings);
		return($this);
	}
	
	public function run() {
		try {
			$this->check_settings()
				->compile_request();

			$this->build_and_execute_router()
				->build_and_execute_compiler()
				->build_and_execute_dispatcher();
	
			$this->build_and_render_view();
			$this->response
				->set_headers($this->controller->get_headers())
				->set_content_type($this->view->get_content_type())
				->set_response_code($this->controller->get_response_code())
				->set_content($this->view->get_rendering());
		} catch (\Exception $e) {
			$response_code = (int)$e->getCode();
			if (0 === $response_code) {
				$response_code = 500;
			}
			
			$this->response->set_content_type("text/plain")
				->set_response_code($response_code)
				->set_content($e->getMessage());
		}
		return($this->response->respond());
	}
	
	public function get_controller() {
		return($this->controller);
	}
	
	public function get_request() {
		return($this->request);
	}
	
	public function get_response() {
		return($this->response);
	}
	
	public function get_route() {
		return($this->route);
	}
	
	

	private function check_settings() {
		if (is_null($this->settings)) {
			throw new unrecoverable("A \\kin\\settings object must be attached to the app object before it can run.");
		}
		return($this);
	}
	
	private function compile_request() {
		$http_headers = filter_input_array(INPUT_SERVER, array(
			"HTTP_ACCEPT" => array(),
			"REQUEST_METHOD" => array(),
			"PATH_INFO" => array()
		));
		
		if (isset($this->settings->accept)) {
			$http_headers["HTTP_ACCEPT"] = $this->settings->accept;
		}
		
		$this->request->set_accept_header($http_headers["HTTP_ACCEPT"])
			->set_method($http_headers["REQUEST_METHOD"])
			->set_path($http_headers["PATH_INFO"]);
		return($this);
	}

	private function build_helper() {
		$this->helper = new helper;
		return($this);
	}
	
	private function build_request() {
		$this->request = new request;
		return($this);
	}
	
	private function build_response() {
		$this->response = new response;
		$this->response->set_start_time($this->start_time);
		return($this);
	}
	
	private function build_and_execute_router() {
		$router = new router;
		$router->set_path($this->request->get_path())
			->set_request_method($this->request->get_method())
			->set_routes($this->routes)
			->set_exception_routes($this->exception_routes)
			->route();
		$this->route = $router->get_route();
		return($this);
	}
	
	private function build_and_execute_compiler() {
		$compiler = new compiler;
		$compiler->set_class($this->route->get_class())
			->set_file($this->route->get_controller())
			->set_path($this->settings->controllers_path)
			->compile();
		$this->controller = $compiler->get_controller()
			->attach_helper($this->helper);
		return($this);
	}
	
	private function build_and_execute_dispatcher() {
		$dispatcher = new dispatcher;
		$dispatcher->attach_controller($this->controller)
			->set_action($this->route->get_action())
			->set_arguments($this->route->get_arguments())
			->dispatch();
		$this->controller = $dispatcher->get_controller();
		return($this);
	}
	
	private function build_and_render_view() {
		$this->view = new view;
		if ($this->controller->has_view()) {
			$this->view->attach_helper($this->helper)
				->set_payload($this->controller->get_payload())
				->set_file($this->controller->get_view())
				->set_path($this->settings->views_path)
				->set_acceptable_types($this->request->get_acceptable_types())
				->render();
		}
		return($this);
	}
	
}
