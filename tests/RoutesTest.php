<?php

use Gac\Routing\Exceptions\CallbackNotFound;
use Gac\Routing\Exceptions\RouteNotFoundException;
use Gac\Routing\Request;
use Gac\Routing\Routes;

it("can add a new route", function () {
	$routes = new Routes;
	$routes->add('/', []);
	expect($routes->get_routes()["GET"]["/"])->toBeArray();
});

it("can add multiple request type routes", function () {
	$routes = new Routes;

	$routes->add('/test', [], [Routes::GET, Routes::POST]);

	$issetGet = isset($routes->get_routes()['GET']['/test']);
	$issetPost = isset($routes->get_routes()['POST']['/test']);
	expect($issetGet)
			->toBeTrue()
			->and($issetPost)
			->toBeTrue();
});

it("can add middleware", function () {
	$routes = new Routes;
	$routes->middleware(["test"])->add("/middleware", []);
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
			->get('/appended', function () {})
			->get('/appended_sample', function () {})
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
			[new \Gac\Routing\sample\InjectedClass],
	]);

	$callback = $route->get_routes()["GET"]["/demo"]["callback"];
	$di = $route->get_routes()["GET"]["/demo"]["di"];
	$setup = [new $callback[0](...$di[0]), $callback[1]];

	expect($callback)
			->toHaveCount(2)
			->and(call_user_func($setup, $route->request))
			->json()
			->isInstanceOf
			->toBe(true);
});


it('can add a GET route', function () {
	$routes = new Routes();
	$routes->get('/test', function () {
		return 'test';
	});

	$allRoutes = $routes->get_routes();

	expect($allRoutes)
			->toHaveKey('GET')
			->and($allRoutes['GET'])
			->toHaveKey('/test');
});

it('throws RouteNotFoundException for missing route', function () {
	$routes = new Routes();

	$this->expectException(RouteNotFoundException::class);

	$routes->handle(); // Assuming $_SERVER['REQUEST_URI'] is not set
});

it('can handle dynamic routes with parameters', function () {
	$routes = new Routes();
	$_SERVER['REQUEST_URI'] = '/user/123';
	$_SERVER['REQUEST_METHOD'] = 'GET';

	$routes->get('/user/{id}', function ($id) {
		echo $id;
	});

	ob_start();
	$routes->handle();
	$output = ob_get_clean();

	expect($output)->toBe('123');
});

it('can add and execute middleware', function () {
	$routes = new Routes();
	$_SERVER['REQUEST_URI'] = '/middleware-test';
	$_SERVER['REQUEST_METHOD'] = 'GET';

	$middlewareExecuted = false;
	$routes->middleware([function () use (&$middlewareExecuted) {
		$middlewareExecuted = true;
	}]);

	$routes->get('/middleware-test', function () {
		return 'test';
	});

	$routes->handle();

	expect($middlewareExecuted)->toBeTrue();
});

it('throws CallbackNotFound when callback is not callable', function () {
	$routes = new Routes();
	$_SERVER['REQUEST_URI'] = '/invalid-callback';
	$_SERVER['REQUEST_METHOD'] = 'GET';

	$routes->get('/invalid-callback', 'NonExistentClass@method');

	$this->expectException(CallbackNotFound::class);

	$routes->handle();
});

it('handles adding a route with an empty path', function () {
	$routes = new Routes();
	$routes->get('', function () {
		return 'empty path';
	});

	$allRoutes = $routes->get_routes();

	expect($allRoutes['GET'])->toHaveKey('');
});

it('handles overlapping routes', function () {
	$routes = new Routes();
	$routes->get('/user/{id}', function ($id) {
		echo $id;
	});
	$routes->get('/user/profile', function () {
		echo 'profile';
	});

	$_SERVER['REQUEST_URI'] = '/user/profile';
	$_SERVER['REQUEST_METHOD'] = 'GET';

	ob_start();
	$routes->handle();
	$output = ob_get_clean();

	expect($output)->toBe('profile');
});

it('throws RouteNotFoundException for an undefined HTTP method', function () {
	$routes = new Routes();

	$_SERVER['REQUEST_METHOD'] = 'PATCH';
	$_SERVER['REQUEST_URI'] = '/some-path';

	$this->expectException(RouteNotFoundException::class);

	$routes->handle();
});

it('middleware modifies request', function () {
	$routes = new Routes();

	$_SERVER['REQUEST_URI'] = '/modify-request';
	$_SERVER['REQUEST_METHOD'] = 'GET';

	$routes->middleware([function (\Gac\Routing\Request $request) {
		$request->someProperty = 'modified';
	}]);

	$routes->get('/modify-request', function (\Gac\Routing\Request $request) {
		echo $request->someProperty;
	});

	ob_start();
	$routes->handle();
	$output = ob_get_clean();

	expect($output)->toBe('modified');
});


it('handles optional parameters in routes', function () {
	$routes = new Routes();

	$_SERVER['REQUEST_URI'] = '/optional';
	$_SERVER['REQUEST_METHOD'] = 'GET';

	$routes->get('/optional/{id?}', function (Request $request, $id = 'default') {
		echo $id;
	});

	ob_start();
	$routes->handle();
	$output = ob_get_clean();

	expect($output)->toBe('default');
});

it('handles when optional parameters is set in routes', function () {
	$routes = new Routes();

	$_SERVER['REQUEST_URI'] = '/optional/100';
	$_SERVER['REQUEST_METHOD'] = 'GET';

	$routes->get('/optional/{id?}', function (Request $request, $id = 'default') {
		echo $id;
	});

	ob_start();
	$routes->handle();
	$output = ob_get_clean();

	expect($output)->toBe('100');
});

it('handles middleware that throws an exception', function () {
	$routes = new Routes();

	$_SERVER['REQUEST_URI'] = '/exception-middleware';
	$_SERVER['REQUEST_METHOD'] = 'GET';

	$routes->middleware([function () {
		throw new Exception('Middleware failed');
	}]);

	$routes->get('/exception-middleware', function () {
		echo 'This should not be reached';
	});

	$this->expectException(Exception::class);
	$this->expectExceptionMessage('Middleware failed');

	$routes->handle();
});

it('handles invalid regex in route path gracefully', function () {
	$routes = new Routes();

	$_SERVER['REQUEST_URI'] = '/invalid-regex';
	$_SERVER['REQUEST_METHOD'] = 'GET';

	$routes->get('/invalid-regex/{id}[', function ($id) {
		echo $id;
	});

	$this->expectException(RouteNotFoundException::class);

	$routes->handle();
});

it('executes multiple middleware stacks in the correct order', function () {
	$routes = new Routes();

	$_SERVER['REQUEST_URI'] = '/multiple-middleware';
	$_SERVER['REQUEST_METHOD'] = 'GET';

	$middlewareOrder = [];

	$routes->middleware([function () use (&$middlewareOrder) {
		$middlewareOrder[] = 'first';
	}]);

	$routes->middleware([function () use (&$middlewareOrder) {
		$middlewareOrder[] = 'second';
	}]);

	$routes->get('/multiple-middleware', function () use (&$middlewareOrder) {
		$middlewareOrder[] = 'callback';
	});

	$routes->handle();

	expect($middlewareOrder)->toBe(['first', 'second', 'callback']);
});