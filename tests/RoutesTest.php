<?php

	use Gac\Routing\Routes;
	use PHPUnit\Framework\TestCase;

	class RoutesTest extends TestCase
	{
		private Routes $routes;

		public function setUp(): void
		{
			parent::setUp();
			$this->routes = new Routes();
		}

		public function testCanAddNewRoute()
		{
			$this->routes->add("/", []);
			$this->assertTrue(isset($this->routes->getRoutes()["GET"]["/"]), "Unable to add new route");
		}

		public function testCanAddMultipleRequestMethodRoutes()
		{
			$this->routes->add("/test", [], [ Routes::GET, Routes::POST ]);
			$routes = $this->routes->getRoutes();
			$this->assertTrue(isset($routes["GET"]["/test"]) && isset($routes["POST"]["/test"]), "Unable to add new route");
		}

		public function testCannAddMiddleware()
		{
			$this->routes->middleware([ "test" ])->add("/middleware", []);
			$this->assertTrue($this->routes->getRoutes()["GET"]["/middleware"]["middlewares"][0] == "test", "Unable to add middleware");
		}

		public function testCannAddPrefix()
		{
			$this->routes->prefix("/testing")->add("/test", []);
			$routes = $this->routes->getRoutes();

			$this->assertTrue(isset($routes["GET"]["/testing/test"]), "Unable to add prefix to routes");
		}
	}
