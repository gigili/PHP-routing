<?php
	/**
	 * Custom routing library
	 *
	 * @author Igor Ilić <github@igorilic.net>
	 * @copyright 2020-2021 Igor Ilić
	 * @license GNU General Public License v3.0
	 *
	 */

	declare(strict_types=1);

	namespace Gac\Routing;

	use Gac\Routing\Exceptions\CallbackNotFound;
	use Gac\Routing\Exceptions\RouteNotFoundException;
	use JetBrains\PhpStorm\Pure;


	class Routes
	{
		/**
		 * @var string GET Constant representing a GET request method
		 */
		public const GET = "GET";

		/**
		 * @var string POST Constant representing a POST request method
		 */
		public const POST = "POST";

		/**
		 * @var string PUT Constant representing a PUT request method
		 */
		public const PUT = "PUT";

		/**
		 * @var string PATCH Constant representing a PATCH request method
		 */
		public const PATCH = "PATCH";

		/**
		 * @var string DELETE Constant representing a DELETE request method
		 */
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

		/**
		 * Routes constructor
		 */
		public function __construct() {
			$this->request = new Request;
		}

		/**
		 * Method used for adding new routes
		 *
		 * @param string $path Path for the route
		 * @param callable|array|string $callback Callback method, an anonymous function or a class and method name to be executed
		 * @param string|array $methods Allowed request method(s) (GET, POST, PUT...)
		 *
		 * @return Routes returns an instance of it self so that the next method can be chained onto it
		 */
		public function add(string $path, callable|array|string $callback, string|array $methods = self::GET): self {
			if (is_string($methods)) $methods = [$methods];

			if (!empty($this->prefix)) $path = "{$this->prefix}{$path}"; // Prepend prefix to routes

			if ($path !== "/") $path = rtrim($path, "/");

			$regex = NULL;
			$arguments = NULL;
			if (str_contains($path, "{")) {
				$regex = preg_replace("/{.+?}/", "(.+?)", $path);
				$regex = str_replace("/", "\/", $regex);
				$regex = "^{$regex}$";
				preg_match_all("/{(.+?)}/", $path, $matches);
				if (isset($matches[1]) && count($matches) > 0) $arguments = $matches[1];
			}

			foreach ($methods as $method) {
				$this->routes[$method][$path] = [
					"callback" => $callback,
					"middlewares" => $this->middlewares
				];

				if (!is_null($regex)) {
					$this->routes[$method][$path]["regex"] = $regex;
					if (!is_null($arguments)) {
						$this->routes[$method][$path]["arguments"] = $arguments;
					}
				}
			}

			$this->middlewares = [];
			$this->prefix = "";

			return $this;
		}

		/**
		 * Method used to handle execution of routes and middlewares
		 *
		 * @throws RouteNotFoundException|CallbackNotFound
		 */
		public function route() {
			$path = $this->getPath();
			$method = $_SERVER["REQUEST_METHOD"];

			$route = $this->routes[$method][$path] ?? false;

			$arguments = [];
			if ($route === false) {
				$dynamic_routes = array_filter($this->routes[$method], fn($route) => !is_null($route["regex"] ?? NULL));
				foreach ($dynamic_routes as $route_path => $dynamic_route) {
					if (preg_match("/{$dynamic_route["regex"]}/", $path)) {
						$route = $dynamic_route;
						#dd($route, true);
						preg_match_all("/{$dynamic_route["regex"]}/", $path, $matches);
						if (count($matches) > 1) array_shift($matches);
						$matches = array_map(fn($m) => $m[0], $matches);

						$args = $route["arguments"] ?? [];
						foreach ($args as $index => $argumentName) {
							$type = "string";
							if (str_contains($argumentName, ":")) {
								$colonIndex = strpos($argumentName, ":");
								$type = substr($argumentName, 0, $colonIndex);
								$argumentName = substr($argumentName, $colonIndex + 1, strlen($argumentName));
							}

							$value = $matches[$index] ?? NULL;
							$value = match ($type) {
								"int" => intval($value),
								"float" => floatval($value),
								"double" => doubleval($value),
								"bool" => is_numeric($value) ? boolval($value) : ($value === "true"),
								default => (string)$value,
							};

							$arguments[$argumentName] = $value;
						}
						break;
					}
				}
			}

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
			call_user_func($callback, $this->request, ...$arguments);
		}

		/**
		 * Method which adds a prefix to route or a group of routes
		 *
		 * @param string $prefix Prefix to be added
		 *
		 * @return Routes returns an instance of it self so that the next method can be chained onto it
		 */
		public function prefix(string $prefix = ""): self {
			$this->prefix = $prefix;
			return $this;
		}

		/**
		 * Method used to set the middleware to be run before accessing API endpoint
		 *
		 * @param array $data List of middlewares to be executed before accessing the endpoint
		 *
		 * @return Routes returns an instance of it self so that the next method can be chained onto it
		 */
		public function middleware(array $data): self {
			$this->middlewares = $data;
			return $this;
		}

		/**
		 * Method which executes each specified middleware before the routes callback is executed
		 *
		 * @param array $data List of middlewares to be executed before accessing the endpoint
		 *
		 * @throws CallbackNotFound When the specified middleware method is not found
		 */
		private function execute_middleware(array $data) {
			foreach ($data as $function) {

				if (is_array($function)) {
					$function = [new $function[0], $function[1]];
				}

				if (!is_callable($function)) throw new CallbackNotFound("Middleware method {$function} not found", 404);

				call_user_func($function, $this->request);
			}
		}

		/**
		 * Method which returns the current path the user is trying to access
		 *
		 * @return string Returns the current path
		 */
		#[Pure] private function getPath(): string {
			$path = $_SERVER["REQUEST_URI"];
			$position = strpos($path, "?");

			$path = ($path !== "/") ? rtrim($path, "/") : $path;
			return ($position === false) ? $path : substr($path, 0, $position);
		}
	}