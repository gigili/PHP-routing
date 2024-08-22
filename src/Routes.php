<?php

/**
 * Custom routing library
 *
 * This library provides a routing mechanism for managing HTTP requests.
 * It supports route definition, middleware management, and request handling.
 *
 * @author    Igor Ilić <github@igorilic.net>
 * @license   GNU General Public License v3.0
 * @copyright 2020-2024 Igor Ilić
 */

declare(strict_types=1);

namespace Gac\Routing;

use Closure;
use Gac\Routing\Exceptions\CallbackNotFound;
use Gac\Routing\Exceptions\RouteNotFoundException;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use Whoops\Exception\ErrorException;

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
	 * @var string OPTIONS Constant representing an OPTIONS request method
	 */
	public const OPTIONS = 'OPTIONS';

	/**
	 * @var Request $request Instance of the Request class to handle incoming HTTP requests.
	 */
	public Request $request;

	/**
	 * @var string $prefix Prefix to be applied to all defined routes.
	 */
	private string $prefix = '';

	/**
	 * @var array $middlewares List of middleware to be executed before the routes.
	 */
	private array $middlewares = [];

	/**
	 * @var array $routes List of all registered routes.
	 */
	private array $routes = [];

	/**
	 * @var array $tmpRoutes Temporary holder of route information before it's committed to $routes.
	 */
	private array $tmpRoutes = [];

	/**
	 * @var array|null $currentRoute Current route being processed, used for easy access in other methods.
	 */
	private ?array $currentRoute = NULL;

	/**
	 * @var Response $response Instance of the Response class to manage HTTP responses.
	 */
	private Response $response;

	/**
	 * Routes constructor.
	 *
	 * Initializes the request and response instances.
	 */
	public function __construct()
	{
		$this->request = new Request;
		$this->response = Response::getInstance();
	}

	/**
	 * Adds a new route to the routing system.
	 *
	 * @param string $path The URL path for the route.
	 * @param callable|array|string|null $callback The callback function, class method, or callable for the route.
	 * @param string|array|null $methods The HTTP methods the route responds to (e.g., GET, POST).
	 */
	public function add(
			string                     $path = '',
			callable|array|string|null $callback = NULL,
			string|array|null          $methods = self::GET
	): void
	{
		$this->route($path, $callback, $methods);
		$this->save();
	}

	/**
	 * Adds a new route to the temporary route list for chained method calls.
	 *
	 * @param string $path The URL path for the route.
	 * @param callable|array|string|null $callback The callback function, class method, or callable for the route.
	 * @param string|array $methods The HTTP methods the route responds to (e.g., GET, POST).
	 *
	 * @return Routes Returns the current Routes instance to allow method chaining.
	 */
	public function route(
			string       $path,
			callable|array|string|null $callback,
			string|array $methods = self::GET
	): self
	{
		if ( is_string($methods) ) $methods = [$methods];

		if ( !empty($this->prefix) ) $path = $this->prefix . $path;

		if ( $path !== '/' ) $path = rtrim($path, '/');

		$arguments = NULL;

		// Handle standard and optional parameters in the route path.
		if ( str_contains($path, '{') ) {
			if ( !str_contains($path, '?}') ) {
				$regex = preg_replace('/{[^\/]+}/', '([^/]+)', $path);
			} else {
				$regex = preg_replace('/\/{[^\/]+}/', '(/.+)?', $path);
			}
			$regex = str_replace('/', '\/', $regex);
			$regex = "^$regex$";
			preg_match_all('/{([^\/\?]+)\??}/', $path, $matches);
			if ( isset($matches[1]) && count($matches) > 0 ) {
				$arguments = $matches[1];
			}
		} else {
			$regex = "^" . str_replace('/', '\/', $path) . "$";
		}

		foreach ( $methods as $method ) {
			$this->tmpRoutes[$method][$path] = [
					'callback' => $callback,
					'middlewares' => $this->middlewares,
					'regex' => $regex,
					'arguments' => $arguments,
			];
		}

		$this->save(false);
		return $this;
	}

	/**
	 * Commits temporary routes to the main route list.
	 *
	 * @param bool $cleanData If true, clears the temporary data after saving.
	 *
	 * @return Routes Returns the current Routes instance to allow method chaining.
	 */
	public function save(bool $cleanData = true): self
	{
		foreach ( $this->tmpRoutes as $method => $route ) {
			if ( !isset($this->routes[$method]) ) $this->routes[$method] = [];
			$path = array_key_first($route);

			if ( count($this->middlewares) > 0 && count($route[$path]['middlewares']) === 0 ) {
				$route[$path]['middlewares'] = $this->middlewares;
			}

			$route[$path]["di"] = [];
			if ( is_array($route[$path]["callback"]) && count($route[$path]["callback"]) > 2 ) {
				// Store manual entries for dependency injection.
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
	 * Handles the execution of the routes and their associated middlewares.
	 *
	 * @throws RouteNotFoundException If the route cannot be found.
	 * @throws CallbackNotFound If the callback for the route cannot be found.
	 */
	public function handle(): void
	{
		$path = $this->get_path();
		$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

		if ( !isset($this->routes[$method]) ) {
			throw new RouteNotFoundException("Route $path not found", 404);
		}

		$route = false;
		$arguments = [];

		try {
			// Try to find an exact match first.
			if ( isset($this->routes[$method][$path]) ) {
				$route = $this->routes[$method][$path];
			} else {
				// Check against all regex routes.
				foreach ( $this->routes[$method] as $routePath => $routeData ) {
					if ( isset($routeData['regex']) && preg_match("/{$routeData['regex']}/", $path, $matches) ) {
						array_shift($matches); // Remove the full match
						$matches = array_map(fn($m) => ltrim($m, "/"), $matches);
						$route = $routeData;
						$arguments = array_combine($route['arguments'], array_pad($matches, count($route['arguments']), NULL));
						break;
					}
				}
			}
		} catch ( ErrorException ) {
			$route = false;
		}

		if ( $route === false ) {
			throw new RouteNotFoundException("Route $path not found", 404);
		}

		$this->currentRoute = $route;

		$middlewares = $route['middlewares'] ?? [];
		$this->execute_middleware($middlewares);

		$callback = $route['callback'] ?? false;
		if ( $callback === false ) {
			throw new CallbackNotFound("No callback specified for $path", 404);
		}

		$callback = $this->setup_callback($callback);

		if ( !is_callable($callback) ) {
			throw new CallbackNotFound("Unable to execute callback for $path", 404);
		}

		$parameters = $this->get_all_arguments($callback);

		$callbackArguments = [];
		foreach ( $parameters as $name => $type ) {
			if ( strtolower($type) === strtolower('Gac\Routing\Request') ) {
				$callbackArguments[$name] = $this->request;
			} elseif ( strtolower($type) === strtolower('Gac\Routing\Response') ) {
				$callbackArguments[$name] = $this->response;
			} else if ( isset($arguments[$name]) ) {
				$callbackArguments[$name] = $arguments[$name];
			}
		}

		$this->currentRoute = NULL;
		call_user_func_array($callback, $callbackArguments);
	}

	/**
	 * Retrieves the arguments for a given route based on the URL path.
	 *
	 * @param array $route The route configuration.
	 * @param string $path The current URL path.
	 *
	 * @return array An associative array of arguments extracted from the path.
	 */
	private function get_route_arguments(array $route, string $path): array
	{
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
			$value = match ($type) {
				'int' => intval($value),
				'float' => floatval($value),
				'double' => doubleval($value),
				'bool' => is_numeric($value) ? boolval($value) : ($value === 'true'),
				default => (string)$value,
			};

			$arguments[$argumentName] = $value;
		}

		return $arguments;
	}

	/**
	 * Sets up the callback properties for a route.
	 *
	 * @param Closure|string|array $callback The callback configuration.
	 *
	 * @return mixed Returns the callable data needed to execute the route callback.
	 */
	private function setup_callback(Closure|string|array $callback): mixed
	{
		if ( (is_string($callback) && class_exists($callback)) || is_array($callback) ) {
			if ( is_string($callback) ) {
				return new $callback;
			}

			if ( is_array($callback) ) {
				// There is no method provided, so rely on __invoke.
				if ( isset($callback[1]) && is_array($callback[1]) ) {
					$callback[1] = DIContainer::get($callback[0], $callback[1]);
					return new $callback[0](...$callback[1]);
				}

				// There is a method provided with additional arguments.
				if ( isset($callback[1]) && is_string($callback[1]) ) {
					// Inject dependencies if provided.
					if ( isset($callback[2]) ) {
						$callback[2] = DIContainer::get($callback[0], $callback[2]);
						return [new $callback[0](...$callback[2]), $callback[1]];
					}
					return [new $callback[0], $callback[1]];
				}

				$args = DIContainer::get($callback[0]);
				return [new $callback[0](...$args), "__invoke"];
			}
		}

		return $callback;
	}

	/**
	 * Returns the current URL path.
	 *
	 * @return string The current path being accessed.
	 */
	private function get_path(): string
	{
		$path = $_SERVER['REQUEST_URI'] ?? '/';
		$position = strpos($path, '?');

		$path = ($path !== '/') ? rtrim($path, '/') : $path;
		return ($position === false) ? $path : substr($path, 0, $position);
	}

	/**
	 * Executes all specified middleware before the route's callback.
	 *
	 * @param array $data List of middleware to be executed.
	 *
	 * @throws CallbackNotFound If the specified middleware is not found.
	 */
	private function execute_middleware(array $data): void
	{
		$namedArguments = match (is_null($this->currentRoute)) {
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
				$function = [new $class, $method];
			}

			if ( is_string($key) ) {
				$tmpArguments = [$function];
				$function = $key;
			}

			$parameters = $this->get_all_arguments($function) ?? [];
			$requestClassIndex = array_search(Request::class, array_values($parameters));
			$responseClassIndex = array_search(Response::class, array_values($parameters));

			$paramNames = array_keys($parameters);
			for ( $index = 0; $index < count($parameters); $index++ ) {
				if ( $index === $requestClassIndex ) {
					$arguments[$index] = $this->request;
					continue;
				}

				if ( $index === $responseClassIndex ) {
					$arguments[$index] = $this->response;
					continue;
				}

				$arguments[$index] = $tmpArguments[$index] ?? $namedArguments[$paramNames[$index]] ?? NULL;
			}

			if ( !is_callable($function) ) throw new CallbackNotFound("Middleware method $function not found", 404);
			call_user_func($function, ...$arguments);
		}
	}

	/**
	 * Retrieves the arguments for the route's callback method.
	 *
	 * @param object|array|string $function The callback function or method.
	 *
	 * @return array|null Returns an associative array of arguments for the callback, or null on error.
	 */
	private function get_all_arguments(object|array|string $function): array|null
	{
		$function_get_args = [];
		try {
			if ( (is_string($function) && function_exists($function)) || $function instanceof Closure ) {
				$ref = new ReflectionFunction($function);
			} elseif (
					is_string($function) &&
					(str_contains($function, "::") && !method_exists(...explode("::", $function)))
			) {
				return $function_get_args;
			} elseif ( is_object($function) || is_array($function) ) {
				$class = ((array)$function)[0];
				$method = ((array)$function)[1];
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
	 * Retrieves a list of all defined routes.
	 *
	 * @return array The list of registered routes.
	 */
	public function get_routes(): array
	{
		return $this->routes;
	}

	/**
	 * Wrapper method for adding a new GET route.
	 *
	 * @param string $path The URL path for the route.
	 * @param callable|array|string|null $callback The callback function, class method, or callable for the route.
	 *
	 * @return Routes Returns the current Routes instance to allow method chaining.
	 */
	public function get(string $path, callable|array|string|null $callback = NULL): self
	{
		$this->route($path, $callback, [self::GET]);
		return $this;
	}

	/**
	 * Wrapper method for adding a new POST route.
	 *
	 * @param string $path The URL path for the route.
	 * @param callable|array|string|null $callback The callback function, class method, or callable for the route.
	 *
	 * @return Routes Returns the current Routes instance to allow method chaining.
	 */
	public function post(string $path, callable|array|string|null $callback = NULL): self
	{
		$this->route($path, $callback, [self::POST]);
		return $this;
	}

	/**
	 * Wrapper method for adding a new PUT route.
	 *
	 * @param string $path The URL path for the route.
	 * @param callable|array|string|null $callback The callback function, class method, or callable for the route.
	 *
	 * @return Routes Returns the current Routes instance to allow method chaining.
	 */
	public function put(string $path, callable|array|string|null $callback = NULL): self
	{
		$this->route($path, $callback, [self::PUT]);
		return $this;
	}

	/**
	 * Wrapper method for adding a new PATCH route.
	 *
	 * @param string $path The URL path for the route.
	 * @param callable|array|string|null $callback The callback function, class method, or callable for the route.
	 *
	 * @return Routes Returns the current Routes instance to allow method chaining.
	 */
	public function patch(string $path, callable|array|string|null $callback = NULL): self
	{
		$this->route($path, $callback, [self::PATCH]);
		return $this;
	}

	/**
	 * Wrapper method for adding a new DELETE route.
	 *
	 * @param string $path The URL path for the route.
	 * @param callable|array|string|null $callback The callback function, class method, or callable for the route.
	 *
	 * @return Routes Returns the current Routes instance to allow method chaining.
	 */
	public function delete(string $path, callable|array|string|null $callback = NULL): self
	{
		$this->route($path, $callback, [self::DELETE]);
		return $this;
	}

	/**
	 * Wrapper method for adding a new OPTIONS route.
	 *
	 * @param string $path The URL path for the route.
	 * @param callable|array|string|null $callback The callback function, class method, or callable for the route.
	 *
	 * @return Routes Returns the current Routes instance to allow method chaining.
	 */
	public function options(string $path, callable|array|string|null $callback = NULL): self
	{
		$this->route($path, $callback, [self::OPTIONS]);
		return $this;
	}

	/**
	 * Sets a prefix to be applied to all routes defined within the chain.
	 *
	 * @param string $prefix The prefix to be added to all routes.
	 *
	 * @return Routes Returns the current Routes instance to allow method chaining.
	 */
	public function prefix(string $prefix = ''): self
	{
		$this->prefix .= $prefix;
		return $this;
	}

	/**
	 * Sets the middlewares to be applied to routes.
	 *
	 * @param array $data List of middlewares to be executed before the routes.
	 *
	 * @return Routes Returns the current Routes instance to allow method chaining.
	 */
	public function middleware(array $data): self
	{
		$this->middlewares = array_merge($this->middlewares, $data);
		return $this;
	}

	/**
	 * Appends additional routes to the main route handler.
	 *
	 * @param array $routes List of routes from other route classes.
	 *
	 * @return Routes Returns the current Routes instance to allow method chaining.
	 */
	public function append(array $routes): self
	{
		$this->routes = array_merge_recursive($routes, $this->routes);
		return $this;
	}
}