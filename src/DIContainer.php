<?php
	/**
	 * Author: Igor Ilić <github@igorilic.net>
	 * Date: 2022-06-05
	 * Project: PHP-routing
	 */

	namespace Gac\Routing;

	use ReflectionClass;
	use ReflectionException;
	use ReflectionUnionType;

	/**
	 * Class used for handling dependency injection for the library
	 */
	class DIContainer
	{

		/**
		 * Method used for handling dependency injection
		 *
		 * It receives the class name that will be checked if it has any parameters that need to be injected
		 * and a list of already defined/created arguments to be injected. It will than use the Reflection class API
		 * to check if the provided class has any injectable parameters and if those parameters also require
		 * any others to be injected as well using a recursion.
		 *
		 * @param string $class Name of the class for which to auto-inject arguments
		 * @param array $arguments List of arguments that will be passed alongside of auto-injected ones
		 *
		 * @return array Return a new list of arguments that holds manually provided and auto-injected arguments
		 */
		public static function get(string $class, array $arguments = []) : array {

			if ( !class_exists($class) ) {
				return $arguments;
			}

			try {
				$reflection = new ReflectionClass($class);

				if ( !$reflection->isInstantiable() ) {
					$arguments["err"][] = 1;
					return $arguments;
				}

				$constructor = $reflection->getConstructor();

				if ( !$constructor ) {
					return $arguments;
				}

				$parameters = $constructor->getParameters();

				if ( is_null($parameters) || count($parameters) === 0 ) {
					return $arguments;
				}

				foreach ( $parameters as $parameter ) {
					if ( isset($arguments[$parameter->name]) ) {
						continue;
					}

					if ( $parameter->isDefaultValueAvailable() ) {
						continue;
					}

					$type = $parameter->getType();

					if ( $type === NULL ) {
						continue;
					}

					if ( $type instanceof ReflectionUnionType ) {
						continue;
					}

					if ( !class_exists($type->getName()) ) {
						continue;
					}

					if ( $type->allowsNull() ) {
						continue;
					}

					if ( $type->isBuiltin() ) {
						continue;
					}

					if ( count($arguments) > 0 && in_array(new ( $type->getName() ), $arguments) ) {
						continue;
					}

					$injectedClass = new ReflectionClass($type->getName());
					if ( !$injectedClass->isInstantiable() ) {
						continue;
					}

					/**
					 * NOTE:
					 * In a scenario like this:
					 *    class A { public function __construct(protected B $b){} }
					 *    class B { public function __construct(protected A $a){} }
					 * this could lead to an infinite loop
					 */
					$injectedClassArguments = self::get($injectedClass->getName());
					if ( count($injectedClassArguments) > 0 ) {
						$arguments[$parameter->getName()] = $injectedClass->newInstance(...$injectedClassArguments);
					} else {
						$arguments[$parameter->getName()] = $injectedClass->newInstance();
					}
				}
			} catch ( ReflectionException ) {
			} finally {
				return $arguments;
			}
		}
	}