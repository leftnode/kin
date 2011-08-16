<?php namespace kin;
declare(encoding='UTF-8');

require_once(__DIR__.'/http/request.php');
require_once(__DIR__.'/http/response.php');

require_once(__DIR__.'/app/compiler.php');
require_once(__DIR__.'/app/dispatcher.php');
require_once(__DIR__.'/app/route.php');
require_once(__DIR__.'/app/router.php');
require_once(__DIR__.'/app/settings.php');

require_once(__DIR__.'/view.php');

class app {

	public $request = null;
	public $response = null;
	public $settings = null;

	private $controller = null;
	private $route = null;

	private $routes = array();
	private $exception_routes = array();
	
	public function __construct() {
		$this->build_request()
			->build_response();
	}
	
	
	
	public function attach_all_routes(array $routes, array $exception_routes) {
		$this->routes = $routes;
		$this->exception_routes = $exception_routes;
		return($this);
	}
	
	public function attach_settings(app\settings $settings) {
		$this->settings = $settings;
		$this->settings->compile();
		return($this);
	}
	
	
	
	public function run() {
		try {
			$this->check_settings()
				->compile_request();
			
			if (isset($this->settings->content_type)) {
				$this->response->set_content_type($this->settings->content_type);
			}

			$this->build_and_execute_router()
				->build_and_execute_compiler()
				->build_and_execute_dispatcher();
			
			if (!$this->controller->has_content_type()) {
				$this->controller->set_content_type($this->response->get_content_type());
			}
			
			$type = $this->settings->type;
			if (empty($type)) {
				$type = $this->request->get_type();
			}
			
			$content = $this->build_and_render_view($type);
			$this->response
				->set_headers($this->controller->get_headers())
				->set_content_type($this->controller->get_content_type())
				->set_response_code($this->controller->get_response_code())
				->set_content($content);
			
		} catch (\Exception $e) {
			$this->response->set_content($e->getMessage());
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
			throw new \kin\exception\unrecoverable("A kin\\settings object must be attached to the app object before it can run.");
		}
		return($this);
	}
	
	private function compile_request() {
		$http_headers = filter_input_array(INPUT_SERVER, array(
			'HTTP_ACCEPT' => array(),
			'REQUEST_METHOD' => array(),
			'PATH_INFO' => array()
		));
		
		if (isset($this->settings->accept)) {
			$http_headers['HTTP_ACCEPT'] = $this->settings->accept;
		}
		
		$this->request->set_accept($http_headers['HTTP_ACCEPT'])
			->set_method($http_headers['REQUEST_METHOD'])
			->set_path($http_headers['PATH_INFO']);
		return($this->copy_request_accept_to_response_content_type());
	}
	
	private function copy_request_accept_to_response_content_type() {
		$this->response->set_content_type($this->request->get_accept());
		return($this);
	}
	
	private function build_request() {
		$this->request = new http\request;
		return($this);
	}
	
	private function build_response() {
		$this->response = new http\response;
		return($this->copy_request_accept_to_response_content_type());
	}
	
	private function build_and_execute_router() {
		$router = new app\router;
		$router->set_path($this->request->get_path())
			->set_request_method($this->request->get_method())
			->set_routes($this->routes)
			->set_exception_routes($this->exception_routes)
			->route();
		$this->route = $router->get_route();
		return($this);
	}
	
	private function build_and_execute_compiler() {
		$compiler = new app\compiler;
		$compiler->set_class($this->route->get_class())
			->set_file($this->route->get_controller())
			->set_path($this->settings->controllers_path)
			->compile();
		$this->controller = $compiler->get_controller()
			->attach_request($this->request);
		return($this);
	}
	
	private function build_and_execute_dispatcher() {
		$dispatcher = new app\dispatcher;
		$dispatcher->attach_controller($this->controller)
			->set_action($this->route->get_action())
			->set_arguments($this->route->get_arguments())
			->dispatch();
		$this->controller = $dispatcher->get_controller();
		return($this);
	}
	
	private function build_and_render_view($type) {
		$content = '';
		if ($this->controller->has_view()) {
			$view = new view;
			$view->set_payload($this->controller->get_payload())
				->set_file($this->controller->get_view())
				->set_path($this->settings->views_path)
				->set_type($type)
				->render();
			$content = $view->get_rendering();
		}
		return($content);
	}
	
}