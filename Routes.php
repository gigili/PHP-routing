<?php
	/**
	 * Custom routing library
	 *
	 * @author Igor Ilić <github@igorilic.net>
	 * @copyright 2020-2021 Igor Ilić
	 * @license GNU General Public License v3.0
	 *
	 */

	declare( strict_types=1 );

	namespace Gac\Routing;


	use Closure;
	use Gac\Routing\Exceptions\CallbackNotFound;
	use Gac\Routing\Exceptions\RouteNotFoundException;
	use ReflectionException;
	use ReflectionFunction;
	use ReflectionMethod;
	use ReflectionNamedType;

	class Routes
	{
		/**
		 * @var string GET Constant representing a GET request method
		 */
		public const GET = 'GET';

		/**
		 * @var string POST Constant representing a POST request method
		 */
		public const POST = 'POST';

		/**
		 * @var string PUT Constant representing a PUT request method
		 */
		public const PUT = 'PUT';

		/**
		 * @var string PATCH Constant representing a PATCH request method
		 */
		public const PATCH = 'PATCH';

		/**
		 * @var string DELETE Constant representing a DELETE request method
		 */
		public const DELETE = 'DELETE';

		/**
		 * @var string $prefix Routes prefix
		 */
		private string $prefix = '';

		/**
		 * @var array $middlewares List of middlewares to be executed before the routes
		 */
		private array $middlewares = [];

		/**
		 * @var array $routes List of available routs
		 */
		private array $routes = [];

		/**
		 * @var array $routes Temporary holder of list until the all get stored in the primary $routes array
		 */
		private array $tmpRoutes = [];

		/**
		 * @var Request $request Instance of a Request class to be passed as an argument to routes callback
		 */
		public Request $request;

		/**
		 * Routes constructor
		 */
		public function __construct()
		{
			$this->request = new Request;
		}

		/**
		 * Method used to set the prefix for routes
		 *
		 * @param string $prefix Prefix to be added to all the routes in that chain.
		 *
		 * @return Routes Returns an instance of it self so that other methods could be chained onto it
		 */
		public function prefix(string $prefix = ''): self
		{
			$this->prefix = $prefix;
			return $this;
		}

		/**
		 * Method used to set the middlewares for routes
		 *
		 * @param array $data List of middlewares to be executed before the routes
		 *
		 * @return Routes Returns an instance of it self so that other methods could be chained onto it
		 */
		public function middleware(array $data): self
		{
			$this->middlewares = $data;
			return $this;
		}

		/**
		 * Method used to handle execution of routes and middlewares
		 *
		 */
		public function route(string $path, callable|array|string $callback, string|array $methods = self::GET): self
		{
			if ( is_string($methods) ) $methods = [ $methods ];

			if ( !empty($this->prefix) ) $path = $this->prefix . $path; // Prepend prefix to routes

			if ( $path !== '/' ) $path = rtrim($path, '/');

			$regex = NULL;
			$arguments = NULL;
			if ( str_contains($path, '{') ) {
				$regex = preg_replace('/{.+?}/', '(.+?)', $path);
				$regex = str_replace('/', '\/', $regex);
				$regex = "^{$regex}$";
				preg_match_all('/{(.+?)}/', $path, $matches);
				if ( isset($matches[1]) && count($matches) > 0 ) $arguments = $matches[1];
			}

			foreach ( $methods as $method ) {
				$this->tmpRoutes[$method][$path] = [
					'callback' => $callback,
					'middlewares' => $this->middlewares,
				];

				if ( !is_null($regex) ) {
					$this->tmpRoutes[$method][$path]['regex'] = $regex;
					if ( !is_null($arguments) ) {
						$this->tmpRoutes[$method][$path]['arguments'] = $arguments;
					}
				}
			}

			return $this;
		}

		/**
		 * Method used for adding new routes
		 *
		 * @param string $path Path for the route
		 * @param callable|array|string|null $callback Callback method, an anonymous function or a class and method name to be executed
		 * @param string|array|null $methods Allowed request method(s) (GET, POST, PUT...)
		 *
		 */
		public function add(string $path = '', callable|array|string|null $callback = NULL, string|array|null $methods = self::GET)
		{
			if ( empty($this->tmpRoutes) ) {
				$this->route($path, $callback, $methods);
			}

			foreach ( $this->tmpRoutes as $method => $route ) {
				if ( !isset($this->routes[$method]) ) $this->routes[$method] = [];
				$path = array_key_first($route);

				if ( count($this->middlewares) > 0 && count($route[$path]['middlewares']) === 0 ) {
					$route[$path]['middlewares'] = $this->middlewares;
				}

				if ( !empty($this->prefix) && !str_starts_with($path, $this->prefix) ) {
					$newPath = rtrim("{$this->prefix}$path", '/');
					$route[$newPath] = $route[$path];
					unset($route[$path]);
				}

				$this->routes[$method] = array_merge($this->routes[$method], $route);
			}

			$this->prefix = '';
			$this->middlewares = [];
			$this->tmpRoutes = [];
		}

		/**
		 * Method used to handle execution of routes and middlewares
		 *
		 * @throws RouteNotFoundException When the route was not found
		 * @throws CallbackNotFound When the callback for the route was not found
		 */
		public function handle()
		{
			$path = $this->getPath();
			$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

			$route = $this->routes[$method][$path] ?? false;

			$arguments = [];
			if ( $route === false ) {
				$dynamic_routes = array_filter($this->routes[$method], fn($route) => !is_null($route['regex'] ?? NULL));
				foreach ( $dynamic_routes as $dynamic_route ) {
					if ( preg_match("/{$dynamic_route['regex']}/", $path) ) {
						$route = $dynamic_route;
						preg_match_all("/{$dynamic_route['regex']}/", $path, $matches);
						if ( count($matches) > 1 ) array_shift($matches);
						$matches = array_map(fn($m) => $m[0], $matches);

						$args = $route['arguments'] ?? [];
						foreach ( $args as $index => $argumentName ) {
							$type = 'string';
							if ( str_contains($argumentName, ':') ) {
								$colonIndex = strpos($argumentName, ':');
								$type = substr($argumentName, 0, $colonIndex);
								$argumentName = substr($argumentName, $colonIndex + 1, strlen($argumentName));
							}

							$value = $matches[$index] ?? NULL;
							$value = match ( $type ) {
								'int' => intval($value),
								'float' => floatval($value),
								'double' => doubleval($value),
								'bool' => is_numeric($value) ? boolval($value) : ( $value === 'true' ),
								default => (string)$value,
							};

							$arguments[$argumentName] = $value;
						}
						break;
					}
				}
			}

			if ( $route === false ) throw new RouteNotFoundException("Route $path not found", 404);

			$middlewares = $route['middlewares'] ?? [];
			$this->execute_middleware($middlewares);

			$callback = $route['callback'] ?? false;
			if ( $callback === false ) throw new CallbackNotFound("No callback specified for $path", 404);

			if ( ( is_string($callback) && class_exists($callback) ) || is_array($callback) ) {
				$controller = is_string($callback) ? new $callback : new $callback[0]; // make a new instance of a controller class
				$fn = is_string($callback) ? 'index' : $callback[1] ?? 'index'; // get the method to be execute or fallback to index method
				$callback = [ $controller, $fn ];
			}

			if ( !is_callable($callback) ) throw new CallbackNotFound("Unable to execute callback for $path", 404);

			$parameters = $this->get_all_arguments($callback);

			$callbackArguments = [];
			foreach ( $parameters as $name => $type ) {
				if ( strtolower($type) === strtolower('Gac\Routing\Request') ) {
					$callbackArguments[$name] = $this->request;
					continue;
				}
				$callbackArguments[$name] = $arguments[$name] ?? NULL;
			}

			call_user_func($callback, ...$callbackArguments);
		}

		/**
		 * Private method used to fetch the arguments of the routs callback methods
		 *
		 * @param object|array|string $func
		 * @return array|null Returns a list of arguments for a method or null on error
		 */
		private function get_all_arguments(object|array|string $func): array|null
		{
			$func_get_args = array();
			try {
				if ( ( is_string($func) && function_exists($func) ) || $func instanceof Closure ) {
					$ref = new ReflectionFunction($func);
				} else if ( is_string($func) && !call_user_func_array('method_exists', explode('::', $func)) ) {
					return $func_get_args;
				} else {
					$ref = new ReflectionMethod($func[0], $func[1]);
				}

				foreach ( $ref->getParameters() as $param ) {
					if ( !isset($func_get_args[$param->name]) ) {
						$type = $param->getType();
						if ( is_null($type) ) {
							$func_get_args[$param->name] = 'nothing';
						} else {
							assert($type instanceof ReflectionNamedType);
							$func_get_args[$param->name] = $type->getName() ?? 'string';
						}
					}
				}
				return $func_get_args;
			} catch ( ReflectionException $ex ) {
				error_log($ex->getMessage());
				return NULL;
			}
		}

		/**
		 * Method which executes each specified middleware before the routes callback is executed
		 *
		 * @param array $data List of middlewares to be executed before accessing the endpoint
		 *
		 * @throws CallbackNotFound When the specified middleware method is not found
		 */
		private function execute_middleware(array $data)
		{
			foreach ( $data as $function ) {

				if ( is_array($function) ) {
					$function = [ new $function[0], $function[1] ];
				}

				if ( !is_callable($function) ) throw new CallbackNotFound("Middleware method $function not found", 404);

				call_user_func($function, $this->request);
			}
		}

		/**
		 * Method which returns the current path the user is trying to access
		 *
		 * @return string Returns the current path
		 */
		private function getPath(): string
		{
			$path = $_SERVER['REQUEST_URI'] ?? '/';
			$position = strpos($path, '?');

			$path = ( $path !== '/' ) ? rtrim($path, '/') : $path;
			return ( $position === false ) ? $path : substr($path, 0, $position);
		}

		/**
		 * Return the list of defined routed
		 *
		 * @return array
		 */
		public function getRoutes(): array
		{
			return $this->routes;
		}
	}