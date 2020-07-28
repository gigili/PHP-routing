<?php


	/**
	 * Custom routing class
	 */
	class Routes
	{
		/**
		 * @var array $routes List of available routs
		 */
		private $routes;

		/**
		 * Constructor function used to initialize the Routes class
		 */
		public function construct() {
			$this->routes = [];
		}

		/**
		 * Method used for adding new routes
		 *
		 * @param string $url URL of the rout
		 * @param null|callable $callback Callback method or an anonymous function to be executed
		 * @param null|array $params Parameters to be sent to the callback function
		 * @param string $method Allowed request methods (GET, POST, PUT...)
		 *
		 * @throws Exception Throws an exception when you try to declare and already existing route
		 */
		public function add($url = "", $callback = NULL, $params = [], $method = "GET") {
			if (is_string($method)) {
				$method = [$method];
			}

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
				if (strpos($url, ":") !== FALSE) {
					$nUrl = preg_replace("/(:[\w\-_]+)/", "([\w\-\_\:]+)", $url);
					$nUrl = str_replace("/", "\/", $nUrl);
				}

				$this->routes[$url] = [
					"url" => $url,
					"callback" => $callback,
					"allowed_method" => $m,
					"params" => $params,
					"regex" => $nUrl
				];
			}
		}

		/**
		 * Method which handles all the routing and mapping of dynamic routes
		 *
		 * @return Boolean Returns true if the route was found and called or false with a 404 status code on error
		 */
		public function route() {
			$url = isset($_GET['myUri']) ? $_GET['myUri'] : "";
			$url = rtrim($url, "/");
			$url .= "-{$_SERVER['REQUEST_METHOD']}";
			$url = ltrim($url, "-");

			if (isset($this->routes[$url]) && $this->routes[$url]["allowed_method"] == $_SERVER["REQUEST_METHOD"]) {
				$this->routes[$url]["callback"]($this->routes[$url]["params"]);
				return true;
			}

			foreach ($this->routes as $ruta) {
				if (is_null($ruta["regex"]) === FALSE) {
					if (preg_match("/^{$ruta["regex"]}-{$_SERVER['REQUEST_METHOD']}/", $url) === 1) {
						$urlIndex = $ruta["url"];

						preg_match_all("/^{$ruta["regex"]}-{$_SERVER['REQUEST_METHOD']}/", $url, $tmpParams);
						preg_match_all("/^{$ruta["regex"]}-{$_SERVER['REQUEST_METHOD']}/", $ruta["url"], $paramNames);
						array_shift($tmpParams);
						array_shift($paramNames);

						$params = [];
						for ($x = 0; $x < count($paramNames); $x++) {
							$params[str_replace(":", "", $paramNames[$x][0])] = $tmpParams[$x][0];
						}

						if (is_array($this->routes[$urlIndex]["params"])) {
							$params = array_merge($params, $this->routes[$urlIndex]["params"]);
						}

						$this->routes[$urlIndex]["callback"]($params);
						return true;
					}
				}
			}

			header('HTTP/1.1 404 Not Found');
			return false;
		}
	}
