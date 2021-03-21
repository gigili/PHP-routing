<?php

	namespace Gac\Routing;

	use Exception;
	use Gac\Routing\Exceptions\RouteNotFoundException;

	/**
	 * Custom routing utility
	 */
	class Routes
	{
		/**
		 * @var array $routes List of available routs
		 */
		private array $routes;

		/**
		 * Constructor function used to initialize the Routes utility
		 */
		public function __construct() {
			$this->routes = [];
		}

		/**
		 * Method used for adding new routes
		 *
		 * @param string $url URL of the rout
		 * @param null|callable $callback Callback method or an anonymous function to be executed
		 * @param array $params Parameters to be sent to the callback function
		 * @param array $method Allowed request methods (GET, POST, PUT...)
		 *
		 * @return Routes returns the instance of the Routes utility
		 * @throws Exception Throws an exception when you try to declare and already existing route
		 */
		public function add(string $url = "", callable $callback = NULL, array $method = ["GET"]): self {
			$tmpUrl = $url;

			foreach ($method as $m) {
				$url = $tmpUrl;
				$url = trim($url, "/");
				$url = preg_replace("/\s/", "-", $url);
				$url .= "-$m";
				$url = ltrim($url, "-");

				if (isset($this->routes[$url]) && $this->routes[$url]['allowed_method'] == $method) {
					throw new Exception("The specified path: ( $tmpUrl | $method ) already exists!", 50001);
				}

				$nUrl = NULL;
				if (strpos($url, ":") !== false) {
					$nUrl = preg_replace("/(:[\w\-_]+)/", "([\w\-\_\:]+)", $url);
					$nUrl = str_replace("/", "\/", $nUrl);
				}

				$this->routes[$url] = [
					"url" => $url,
					"callback" => $callback,
					"allowed_method" => $m,
					"params" => [],
					"regex" => $nUrl,
					"middleware" => []
				];
			}

			return $this;
		}

		/**
		 * Method which handles all the routing and mapping of dynamic routes
		 *
		 * @return Boolean Returns true if the route was found and called or false with a 404 status code on error
		 * @throws RouteNotFoundException|Exception Throws an exception if the middleware function can't be found
		 */
		public function route(): bool {
			$url = isset($_GET['myUri']) ? $_GET['myUri'] : "";
			$url = rtrim($url, "/");
			$url .= "-{$_SERVER['REQUEST_METHOD']}";
			$url = ltrim($url, "-");

			if (isset($this->routes[$url]) && $this->routes[$url]["allowed_method"] == $_SERVER["REQUEST_METHOD"]) {
				if (count($this->routes[$url]["middleware"]) > 0) {
					$this->execute_middleware($this->routes[$url]["middleware"]);
				}

				$this->routes[$url]["callback"]($this->routes[$url]["params"]);
				return true;
			}

			foreach ($this->routes as $route) {
				if (is_null($route["regex"]) === FALSE) {
					if (preg_match("/^{$route["regex"]}-{$_SERVER['REQUEST_METHOD']}/", $url) === 1) {
						$urlIndex = $route["url"];

						preg_match_all("/^{$route["regex"]}-{$_SERVER['REQUEST_METHOD']}/", $url, $tmpParams);
						preg_match_all("/^{$route["regex"]}-{$_SERVER['REQUEST_METHOD']}/", $route["url"], $paramNames);
						array_shift($tmpParams);
						array_shift($paramNames);

						$params = [];
						for ($x = 0; $x < count($paramNames); $x++) {
							$params[str_replace(":", "", $paramNames[$x][0])] = $tmpParams[$x][0];
						}

						if (is_array($this->routes[$urlIndex]["params"])) {
							$params = array_merge($params, $this->routes[$urlIndex]["params"]);
						}

						if (count($this->routes[$urlIndex]["middleware"]) > 0) {
							$this->execute_middleware($this->routes[$urlIndex]["middleware"]);
						}

						$this->routes[$urlIndex]["callback"]($params);
						return true;
					}
				}
			}
			throw new RouteNotFoundException();
		}

		/**
		 * Method used to set the middleware to be run before accessing API endpoint
		 *
		 * @param array $data List of methods to be executed before accessing the endpoint
		 *
		 * @return Routes returns an instance of it self so that the next method can be chained onto it.
		 * @throws Exception If the specified method is not found
		 */
		public function middleware(array $data): self {
			$routeKeys = array_keys($this->routes);
			$tmpRoute = $this->routes[$routeKeys[count($routeKeys) - 1]]["url"];

			foreach ($data as $function) {
				$param = NULL;

				if (!is_string($function)) {
					$param = $function[1];
					$function = $function[0];
				}

				if (function_exists($function)) {
					if (!is_null($param)) {
						array_push($this->routes[$tmpRoute]["middleware"], [$function, $param]);
					} else {
						array_push($this->routes[$tmpRoute]["middleware"], $function);
					}
				} else {
					throw new Exception("Function $function doesn't exists");
				}
			}

			return $this;
		}

		/**
		 * Method which executes each specified middleware before the endpoint is called
		 *
		 * @param array $data List of methods to be executed before accessing the endpoint
		 *
		 * @return Routes returns an instance of it self so that the next method can be chained onto it.
		 * @throws Exception If the specified method is not found
		 */
		private function execute_middleware(array $data): self {
			foreach ($data as $function) {
				$param = NULL;

				if (!is_string($function)) {
					$param = $function[1];
					$function = $function[0];
				}

				if (function_exists($function)) {
					if (!is_null($param)) {
						$function(is_array($param) && count($param) === 1 ? $param[0] : $param);
					} else {
						$function();
					}
				} else {
					throw new Exception("Function $function doesn't exists");
				}
			}

			return $this;
		}
	}
