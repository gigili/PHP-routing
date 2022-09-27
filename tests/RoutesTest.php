<?php

	use Gac\Routing\Routes;

	it("can add a new route", function () {
		$routes = new Routes;
		$routes->add('/', []);
		expect($routes->get_routes()["GET"]["/"])->toBeArray();
	});

	it("can add multiple request type routes", function () {
		$routes = new Routes;

		$routes->add('/test', [], [ Routes::GET, Routes::POST ]);

		$issetGet = isset($routes->get_routes()['GET']['/test']);
		$issetPost = isset($routes->get_routes()['POST']['/test']);
		expect($issetGet)
			->toBeTrue()
			->and($issetPost)
			->toBeTrue();
	});

	it("can add middleware", function () {
		$routes = new Routes;
		$routes->middleware([ "test" ])->add("/middleware", []);
		expect($routes->get_routes()['GET']['/middleware']['middlewares'][0])->toBe("test");
	});

	it("can add prefix to routes", function () {
		$routes = new Routes;
		$routes->prefix('/testing')->add('/test', []);
		expect(isset($routes->get_routes()['GET']['/testing/test']))->toBeTrue();
	});

	it("can use the save method to add a new route", function () {
		$routes = new Routes;
		$routes->prefix('/testing')->get('/test', [])->save();
		expect($routes->get_routes()["GET"])->toHaveCount(1);
	});

	it('can use the add method to add a new route', function () {
		$routes = new Routes;
		$routes->prefix('/testing')->add('/test', []);
		expect($routes->get_routes()['GET'])->toHaveCount(1);
	});

	it("can append new routes", function () {
		$routes = new Routes;
		$routes->add('/', []);

		$appendedRoutes = new Routes;
		$appendedRoutes->prefix('/test')
					   ->get('/appended', function () { })
					   ->get('/appended_sample', function () { })
					   ->save();

		$newRoutes = $appendedRoutes->get_routes();
		$routes->append($newRoutes);

		expect($routes->get_routes()["GET"])->toHaveCount(3);
	});

	it("can do dependency injection", function () {
		if ( !class_exists('InjectedClass') ) {
			require_once './sample/InjectedClass.php';
		}

		if ( !class_exists('HomeController') ) {
			require_once './sample/HomeController.php';
		}

		$route = new Routes;
		$route->add("/demo", [
			\Gac\Routing\sample\HomeController::class,
			"dependency_injection_test",
			[ new \Gac\Routing\sample\InjectedClass ],
		]);

		$callback = $route->get_routes()["GET"]["/demo"]["callback"];
		$setup = [ new $callback[0](...$callback[2]), $callback[1] ];

		expect($callback)
			->toHaveCount(3)
			->and(call_user_func($setup, $route->request))
			->json()
			->isInstanceOf
			->toBe(true);
	});
