<?php

	use Gac\Routing\Routes;


	it("can add a new route", function(){
		$routes = new Routes;
		$routes->add('/', []);
		expect($routes->get_routes()["GET"]["/"])->toBeArray();
	});

	it("can add multiple request type routes", function(){
		$routes = new Routes;

		$routes->add('/test', [], [ Routes::GET, Routes::POST ]);

		expect(isset($routes->get_routes()["GET"]["/test"]))->toBeTrue();
		expect(isset($routes->get_routes()["POST"]["/test"]))->toBeTrue();
	});

	it("can add middleware", function(){
		$routes = new Routes;
		$routes->middleware(["test"])->add("/middleware", []);
		expect($routes->get_routes()['GET']['/middleware']['middlewares'][0])->toBe("test");
	});

	it("can add prefix to routes", function(){
		$routes = new Routes;
		$routes->prefix('/testing')->add('/test', []);
		expect(isset($routes->get_routes()['GET']['/testing/test']))->toBeTrue();
	});

	it("can use the save method to add a new route", function(){
		$routes = new Routes;
		$routes->prefix('/testing')->get('/test', [])->save();
		expect($routes->get_routes()["GET"])->toHaveCount(1);
	});

	it('can use the add method to add a new route', function () {
		$routes = new Routes;
		$routes->prefix('/testing')->add('/test', []);
		expect($routes->get_routes()['GET'])->toHaveCount(1);
	});

	it("can append new routes", function(){
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
