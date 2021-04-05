<?php
	/**
	 * Custom routing library
	 *
	 * @author Igor Ilić <github@igorilic.net>
	 * @package \Gac\Routing
	 * @copyright 2020-2021 Igor Ilić
	 * @license GNU General Public License v3.0
	 *
	 */

	namespace Gac\Routing;

	use Gac\Routing\Exceptions\CallbackNotFound;
	use Gac\Routing\Exceptions\RouteNotFoundException;
	use JetBrains\PhpStorm\Pure;


	class Routes
	{
		public const GET = "GET";
		public const POST = "POST";
		public const PUT = "PUT";
		public const PATCH = "PATCH";
		public const DELETE = "DELETE";

		/**
		 * @var Request $request Instance of a Request class to be passed as an argument to routes callback
		 */
		public Request $request;

		/**
		 * @var array $routes List of available routs
		 */
		private array $routes = [];

		/**
		 * @var string $prefix Prefix to be added to routes being created
		 */
		private string $prefix = "";

		/**
		 * @var array $middlewares List of middlewares to be executed before accessing a route
		 */
		private array $middlewares = [];

		public function __construct() {
			$this->request = new Request();
		}

		/**
		 * Method used for adding new routes
		 *
		 * @param string $path Path for the route
		 * @param callable|array|string $callback Callback method or an anonymous function to be executed
		 * @param string|array $methods Allowed request method(s) (GET, POST, PUT...)
		 *
		 * @return Routes returns the instance of the Routes utility
		 */
		public function add(string $path, callable|array|string $callback, string|array $methods = ["GET"]): self {
			if (is_string($methods)) $methods = [$methods];

			if (!empty($this->prefix)) $path = "{$this->prefix}{$path}"; // Prepend prefix to routes

			if ($path !== "/") $path = rtrim($path, "/");

			foreach ($methods as $method) {
				$this->routes[$method][$path] = [
					"callback" => $callback,
					"middlewares" => $this->middlewares
				];
			}

			return $this;
		}

		/**
		 * Method used to handle calling methods that will
		 *
		 * @throws RouteNotFoundException|CallbackNotFound
		 */
		public function route() {
			$path = $this->getPath();
			$method = $_SERVER["REQUEST_METHOD"];

			$route = $this->routes[$method][$path] ?? false;
			if ($route === false) throw new RouteNotFoundException("Route {$path} not found", 404);

			$middlewares = $route["middlewares"] ?? [];
			$this->execute_middleware($middlewares);

			$callback = $route["callback"] ?? false;
			if ($callback === false) throw new CallbackNotFound("No callback specified for {$path}", 404);

			if ((is_string($callback) && class_exists($callback)) || is_array($callback)) {
				$controller = is_string($callback) ? new $callback : new $callback[0]; // make a new instance of a controller class
				$fn = is_string($callback) ? "index" : $callback[1] ?? "index"; // get the method to be execute or fallback to index method
				$callback = [$controller, $fn];
			}

			if (!is_callable($callback)) throw new CallbackNotFound("Unable to execute callback for {$path}", 404);
			call_user_func($callback, $this->request);
		}

		public function prefix(string $prefix = ""): self {
			$this->prefix = $prefix;
			return $this;
		}

		/**
		 * Method used to set the middleware to be run before accessing API endpoint
		 *
		 * @param array $data List of methods to be executed before accessing the endpoint
		 *
		 * @return Routes returns an instance of it self so that the next method can be chained onto it.
		 * @throws RouteNotFoundException If the specified method is not found
		 */
		public function middleware(array $data): self {
			$this->middlewares = $data;
			return $this;
		}

		/**
		 * Method which executes each specified middleware before the routes method is called
		 *
		 * @param array $data List of methods to be executed before accessing the endpoint
		 *
		 * @throws CallbackNotFound When the specified middleware method is not found
		 */
		private function execute_middleware(array $data) {
			foreach ($data as $function) {
				$param = NULL;

				if (!is_string($function)) {
					$param = $function[1];
					$function = $function[0];
				}

				if (!is_callable($function)) throw new CallbackNotFound("Middleware method {$function} not found", 404);

				call_user_func($function, $param);
			}
		}

		#[Pure] private function getPath(): string {
			$path = $_SERVER["REQUEST_URI"];
			$position = strpos($path, "?");

			$path = ($path !== "/") ? rtrim($path, "/") : $path;
			return ($position === false) ? $path : substr($path, 0, $position);
		}
	}

	if (!function_exists("dump")) {
		function dump($data = [], $asJSON = false) {
			if ($asJSON) {
				echo json_encode($data);
			} else {
				echo "<pre>";
				print_r($data);
			}
		}
	}
	if (!function_exists("dd")) {
		function dd($data = [], $asJSON = false) {
			dump($data, $asJSON);
			die(1);
		}
	}