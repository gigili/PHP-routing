<?php

	/**
     * Custom routing library
     *
     * @author    Igor Ilić <github@igorilic.net>
     * @license   GNU General Public License v3.0
     * @copyright 2020-2023 Igor Ilić
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
    use ReflectionUnionType;

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
		 * @var string OPTIONS Constant representing a OPTIONS request method
		 */
		public const OPTIONS = 'OPTIONS';

		/**
		 * @var Request $request Instance of a Request class to be passed as an argument to routes callback
		 */
		public Request $request;
		/**
		 * @var string $prefix Routes prefix
		 */
		private string $prefix = '';
		/**
		 * @var array $middlewares List of middlewares to be executed before the routes
		 */
		private array $middlewares = [];
		/**
		 * @var array $routes List of available routes
		 */
        private array $routes = [];
        /**
         * @var array $routes Temporary holder of route information until it all gets stored in the primary $routes array
         */
        private array $tmpRoutes = [];

        /**
         * @var array|null Array of the current route being processed for eas of access in other methods
         */
        private ?array $currentRoute = NULL;

        private Response $response;

        /*** @var Response Instance of a Response class to be passed as an argument to routes callback
         */
        private Response $response;

        /**
         * Routes constructor
         */
        public function __construct()
        {
            $this->request = new Request;
            $this->response = Response::getInstance();
        }

        /**
		 * Method used for adding new routes
		 *
		 * @param string $path Path for the route
		 * @param callable|array|string|null $callback Callback method, an anonymous function or a class and method name to be executed
		 * @param string|array|null $methods Allowed request method(s) (GET, POST, PUT, PATCH, DELETE)
		 */
		public function add(
			string                     $path = '',
			callable|array|string|null $callback = NULL,
			string|array|null          $methods = self::GET
		) : void {
			$this->route($path, $callback, $methods);
			$this->save();
		}

		/**
		 * Method used for adding new routes into the temporary list when using chained method approach
		 *
		 * @param string $path Path for the route
		 * @param callable|array|string|null $callback Callback method, an anonymous function or a class and method name to be executed
		 * @param string|array $methods Allowed request method(s) (GET, POST, PUT, PATCH, DELETE)
		 *
		 * @return Routes Returns an instance of itself so that other methods could be chained onto it
		 */
		public function route(
			string                     $path,
			callable|array|string|null $callback,
			string|array               $methods = self::GET
		) : self {
			if ( is_string($methods) ) $methods = [ $methods ];

			if ( !empty($this->prefix) ) $path = $this->prefix . $path; // Prepend prefix to routes

			if ( $path !== '/' ) $path = rtrim($path, '/');

			$regex = NULL;
			$arguments = NULL;
			if ( str_contains($path, '{') ) {
				$regex = preg_replace('/{.+?}/', '(.+?)', $path);
				$regex = str_replace('/', '\/', $regex);
				$regex = "^$regex$";
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

			$this->save(false);
			return $this;
		}

		/**
		 * Method used for saving routing information into the global $routes array
		 *
		 * @param bool $cleanData should the data from the tmpRoutes be cleared or not when this method runs
		 */
		public function save(bool $cleanData = true) : self {
			foreach ( $this->tmpRoutes as $method => $route ) {
				if ( !isset($this->routes[$method]) ) $this->routes[$method] = [];
				$path = array_key_first($route);

				if ( count($this->middlewares) > 0 && count($route[$path]['middlewares']) === 0 ) {
					$route[$path]['middlewares'] = $this->middlewares;
				}

				$route[$path]["di"] = [];
				if ( is_array($route[$path]["callback"]) && count($route[$path]["callback"]) > 2 ) {
					//store manual entries for dependency injection
					while ( count($route[$path]["callback"]) > 2 ) {
						$route[$path]["di"][] = array_pop($route[$path]["callback"]);
					}
				}

				if ( !empty($this->prefix) && !str_starts_with($path, $this->prefix) ) {
					$newPath = rtrim("$this->prefix$path", '/');
					$route[$newPath] = $route[$path];
					unset($route[$path]);
				}

				$this->routes[$method] = array_merge($this->routes[$method], $route);
			}

			if ( $cleanData ) {
				$this->prefix = '';
				$this->middlewares = [];
			}

			$this->tmpRoutes = [];
			return $this;
		}

		/**
		 * Method used to handle execution of routes and middlewares
		 *
		 * @throws RouteNotFoundException When the route was not found
		 * @throws CallbackNotFound When the callback for the route was not found
		 */
		public function handle() : void {
			$path = $this->get_path();
			$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

			if ( !isset($this->routes[$method]) ) {
				throw new RouteNotFoundException("Route $path not found", 404);
			}

			$route = $this->routes[$method][$path] ?? false;

			$arguments = [];
			if ( $route === false ) {
				$dynamic_routes = array_filter($this->routes[$method], fn($route) => !is_null($route['regex'] ?? NULL));
				foreach ( $dynamic_routes as $routePath => $dynamic_route ) {
					$countRouteSlashes = count(explode("/", $routePath));
					$countPathSlashes = count(explode('/', $path));

					//TODO: Find a way to not check the number of / as it seems a bit hacky
					if ( $countPathSlashes !== $countRouteSlashes ) continue;

					if ( preg_match("/{$dynamic_route['regex']}/", $path) ) {
						$route = $dynamic_route;
						$arguments = $this->get_route_arguments($dynamic_route, $path);
						break;
					}
				}
			}

			if ( $route === false ) throw new RouteNotFoundException("Route $path not found", 404);
			$this->currentRoute = $route;

			$middlewares = $route['middlewares'] ?? [];
			$this->execute_middleware($middlewares);

			$callback = $route['callback'] ?? false;
			if ( $callback === false ) throw new CallbackNotFound("No callback specified for $path", 404);

			$callback = $this->setup_callback($callback);

			if ( !is_callable($callback) ) throw new CallbackNotFound("Unable to execute callback for $path", 404);

			$parameters = $this->get_all_arguments($callback);

			$callbackArguments = [];
			foreach ( $parameters as $name => $type ) {
                if (strtolower($type) === strtolower('Gac\Routing\Request')) {
                    $callbackArguments[$name] = $this->request;
                    continue;
                }

                if (strtolower($type) === strtolower('Gac\Routing\Response')) {
                    $callbackArguments[$name] = $this->response;
                    continue;
                }
                $callbackArguments[$name] = $arguments[$name] ?? NULL;
            }

			foreach ( $this->currentRoute["di"] as $argument ) {
				$name = array_key_first($argument);
				$value = $argument[$name];
				if ( !isset($callbackArguments[$name]) ) {
					$callbackArguments[$name] = $value;
				}
			}

			$this->currentRoute = NULL;
			call_user_func_array($callback, $callbackArguments);
		}

		/**
		 * Method used for getting a list of arguments for a route
		 *
		 * @param array $route
		 * @param string $path
		 *
		 * @return array
		 */
		private function get_route_arguments(array $route, string $path) : array {
			$arguments = [];
			if ( !isset($route["regex"]) ) return $arguments;

			preg_match_all("/{$route['regex']}/", $path, $matches);
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
					default => (string) $value,
				};

				$arguments[$argumentName] = $value;
			}

			return $arguments;
		}

		/**
		 * Method used to set up callback properties for routes
		 *
		 * @param Closure|string|array $callback Callback data of a route
		 *
		 * @return mixed Return data needed to execute callback
		 */
		private function setup_callback(Closure|string|array $callback) : mixed {
			if ( ( is_string($callback) && class_exists($callback) ) || is_array($callback) ) {
				if ( is_string($callback) ) {
					return new $callback;
				}

				if ( is_array($callback) ) {
					//There is no method provided so relay on __invoke to be used
					if ( isset($callback[1]) && is_array($callback[1]) ) {
						$callback[1] = DIContainer::get($callback[0], $callback[1]);
						return new $callback[0](...$callback[1]);
					}

					//There is a method provided but also any other arguments
					if ( isset($callback[1]) && is_string($callback[1]) ) {
						//There are dependencies that need to be injected
						if ( isset($callback[2]) ) {
							$callback[2] = DIContainer::get($callback[0], $callback[2]);
							return [ new $callback[0](...$callback[2]), $callback[1] ];
						}
						return [ new $callback[0], $callback[1] ];
					}

					$args = DIContainer::get($callback[0]);
					return [ new $callback[0](...$args), "__invoke" ];
				}
			}

			return $callback;
		}

		/**
		 * Method which returns the current path the user is trying to access
		 *
		 * @return string Returns the current path
		 */
		private function get_path() : string {
			$path = $_SERVER['REQUEST_URI'] ?? '/';
			$position = strpos($path, '?');

			$path = ( $path !== '/' ) ? rtrim($path, '/') : $path;
			return ( $position === false ) ? $path : substr($path, 0, $position);
		}

		/**
		 * Method which executes each specified middleware before the route's callback is executed
		 *
		 * @param array $data List of middlewares to be executed before accessing the endpoint
		 *
		 * @throws CallbackNotFound When the specified middleware method is not found
		 */
		private function execute_middleware(array $data) : void {
			$namedArguments = match ( is_null($this->currentRoute) ) {
				false => $this->get_route_arguments($this->currentRoute, $this->get_path()),
				default => []
			};

			foreach ( $data as $key => $function ) {
				$arguments = [];
				$tmpArguments = [];

				if ( is_integer($key) && is_array($function) ) {
					$class = $function[0];
					$method = $function[1];
					array_shift($function);
					array_shift($function);
					$tmpArguments = $function;
					$function = [ new $class, $method ];
				}

				if ( is_string($key) ) {
					$tmpArguments = [ $function ];
					$function = $key;
				}

				$parameters = $this->get_all_arguments($function);
				$requestClassIndex = array_search(Request::class, array_values($parameters));

				$paramNames = array_keys($parameters);
				for ( $index = 0; $index < count($parameters); $index++ ) {
					if ( $index === $requestClassIndex ) {
						$arguments[$index] = $this->request;
						continue;
					}
					$arguments[$index] = $tmpArguments[$index] ?? $namedArguments[$paramNames[$index]] ?? NULL;
				}

				if ( !is_callable($function) ) throw new CallbackNotFound("Middleware method $function not found", 404);
				call_user_func($function, ...$arguments);
			}
		}

		/**
		 * Private method used to fetch the arguments of the route's callback methods
		 *
		 * @param object|array|string $function
		 *
		 * @return array|null Returns a list of arguments for a method or null on error
		 */
		private function get_all_arguments(object|array|string $function) : array|null {
			$function_get_args = [];
			try {
				if ( ( is_string($function) && function_exists($function) ) || $function instanceof Closure ) {
					$ref = new ReflectionFunction($function);
				} elseif (
					is_string($function) &&
					( str_contains($function, "::") && !method_exists(...explode("::", $function)) )
				) {
					return $function_get_args;
				} elseif ( is_object($function) || is_array($function) ) {
					$class = ( (array) $function )[0];
					$method = ( (array) $function )[1];
					$ref = new ReflectionMethod($class, $method);
				} else {
					return $function_get_args;
				}

				foreach ( $ref->getParameters() as $param ) {
					if ( !isset($function_get_args[$param->name]) ) {
						$type = $param->getType();
						if ( is_null($type) ) {
							$function_get_args[$param->name] = 'nothing';
						} else {
							if ( $type instanceof ReflectionNamedType ) {
								$function_get_args[$param->name] = $type->getName() ?? 'string';
							} elseif ( $type instanceof ReflectionUnionType ) {
								# $function_get_args[$param->name] = implode("|", $type->getTypes());
								$function_get_args[$param->name] = "mixed";
							}
						}
					}
				}
				return $function_get_args;
			} catch ( ReflectionException $ex ) {
				error_log($ex->getMessage());
				return NULL;
			}
		}

		/**
		 * Method used for fetching a list of all the created routes
		 *
		 * @return array Return the list of defined routes
		 */
		public function get_routes() : array {
			return $this->routes;
		}

		/**
		 * Wrapper method used for adding new GET routes
		 *
		 * @param string $path Path for the route
		 * @param callable|array|string|null $callback Callback method, an anonymous function or a class and method name to be executed
		 *
		 * @return Routes Returns an instance of itself so that other methods could be chained onto it
		 */
		public function get(string $path, callable|array|string|null $callback = NULL) : self {
			$this->route($path, $callback, [ self::GET ]);
			return $this;
		}

		/**
		 * Wrapper method used for adding new POST routes
		 *
		 * @param string $path Path for the route
		 * @param callable|array|string|null $callback Callback method, an anonymous function or a class and method name to be executed
		 *
		 * @return Routes Returns an instance of itself so that other methods could be chained onto it
		 */
		public function post(string $path, callable|array|string|null $callback = NULL) : self {
			$this->route($path, $callback, [ self::POST ]);
			return $this;
		}

		/**
		 * Wrapper method used for adding new PUT routes
		 *
		 * @param string $path Path for the route
		 * @param callable|array|string|null $callback Callback method, an anonymous function or a class and method name to be executed
		 *
		 * @return Routes Returns an instance of itself so that other methods could be chained onto it
		 */
		public function put(string $path, callable|array|string|null $callback = NULL) : self {
			$this->route($path, $callback, [ self::PUT ]);
			return $this;
		}

		/**
		 * Wrapper method used for adding new PATCH routes
		 *
		 * @param string $path Path for the route
		 * @param callable|array|string|null $callback Callback method, an anonymous function or a class and method name to be executed
		 *
		 * @return Routes Returns an instance of itself so that other methods could be chained onto it
		 */
		public function patch(string $path, callable|array|string|null $callback = NULL) : self {
			$this->route($path, $callback, [ self::PATCH ]);
			return $this;
		}

		/**
		 * Wrapper method used for adding new DELETE routes
		 *
		 * @param string $path Path for the route
		 * @param callable|array|string|null $callback Callback method, an anonymous function or a class and method name to be executed
		 *
		 * @return Routes Returns an instance of itself so that other methods could be chained onto it
		 */
		public function delete(string $path, callable|array|string|null $callback = NULL) : self {
			$this->route($path, $callback, [ self::DELETE ]);
			return $this;
		}

		/**
		 * Wrapper method used for adding new OPTIONS routes
		 *
		 * @param string $path Path for the route
		 * @param callable|array|string|null $callback Callback method, an anonymous function or a class and method name to be executed
		 *
		 * @return Routes Returns an instance of itself so that other methods could be chained onto it
		 */
		public function options(string $path, callable|array|string|null $callback = NULL) : self {
			$this->route($path, $callback, [ self::OPTIONS ]);
			return $this;
		}

		/**
		 * Method used to set the prefix for routes
		 *
		 * @param string $prefix Prefix to be added to all the routes in the chain.
		 *
		 * @return Routes Returns an instance of itself so that other methods could be chained onto it
		 */
		public function prefix(string $prefix = '') : self {
			$this->prefix .= $prefix;
			return $this;
		}

		/**
		 * Method used to set the middlewares for routes
		 *
		 * @param array $data List of middlewares to be executed before the routes
		 *
		 * @return Routes Returns an instance of itself so that other methods could be chained onto it
		 */
		public function middleware(array $data) : self {
			$this->middlewares = array_merge($this->middlewares, $data);
			return $this;
		}

		/**
		 * Method used to append more routes to the main route handler
		 *
		 * @param array $routes List of routes from other route classes
		 *
		 * @return Routes Returns an instance of itself so that other methods could be chained onto it
		 */
		public function append(array $routes) : self {
			$this->routes = array_merge_recursive($routes, $this->routes);
			return $this;
		}
	}
