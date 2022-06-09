<?php
	/** @noinspection PhpUnused */

	/** @noinspection PhpUnusedParameterInspection */

	use Gac\Routing\Exceptions\CallbackNotFound;
	use Gac\Routing\Exceptions\RouteNotFoundException;
	use Gac\Routing\Request;
	use Gac\Routing\Routes;
	use Gac\Routing\sample\HomeController;
	use Gac\Routing\sample\InjectController;
	use Gac\Routing\sample\InjectedClass;
	use Gac\Routing\sample\Middleware;

	#include_once "../Routes.php"; # IF YOU'RE NOT USING composer
	#include_once "HomeController.php"; # IF YOU'RE NOT USING composer

	include_once '../vendor/autoload.php'; # IF YOU'RE USING composer

	if ( !class_exists('InjectedClass') ) {
		require_once './InjectedClass.php';
	}

	if ( !class_exists('InjectController') ) {
		require_once './InjectController.php';
	}

	if ( !class_exists('Middleware') ) {
		require_once './Middleware.php';
	}

	if ( !class_exists('HomeController') ) {
		require_once './HomeController.php';
	}

	$routes = new Routes();

	try {
		$routes->add('/', function (Request $request) {
			echo json_encode([ 'message' => 'Hello World' ]);
		});

		// When using chained method calls either use `save()` or `add()` method at the end to indicate an end of a chain
		// save() method can still be chained onto if needed, but add() can not
		$routes
			->prefix("/test")
			->middleware([ 'decode_token' ])
			->get("/t1", function () { })
			->get("/t2", function () { })
			->get("/t3", function () { })
			->save(false) // by passing the false argument here, we keep all the previous shared data from the chain (previous prefix(es) and middlewares)
			->prefix("/test2")
			->middleware([ "verify_token" ])
			->get("/t4", function () { })
			->get("/t5", function () { })
			->get("/t6", function () { })
			->save() // by not passing the false argument here, we are removing all shared data from the previous chains (previous prefix(es) and middlewares)
			->prefix("/test3")
			->middleware([ "verify_token" ])
			->get("/t7", function () { })
			->get("/t8", function () { })
			->get("/t9", function () { })
			->add(); //using save or add at the end makes the chaining stop and allows for other independent routes to be added

		$routes->add("/routes", function (Request $request) {
			global $routes;
			$request->send($routes->get_routes());
		});

		$routes->add('/test', function (Request $request) {
			$request
				->status(200, 'OK')
				->send([ 'message' => 'Welcome' ]);
		});

		$routes
			->prefix('/user')
			->middleware([ 'verify_token' ])
			->route('/', [ HomeController::class, 'getUsers' ], Routes::GET)
			->route('/', [ HomeController::class, 'addUser' ], Routes::POST)
			->route('/', [ HomeController::class, 'updateUser' ], Routes::PATCH)
			->route('/', [ HomeController::class, 'replaceUser' ], Routes::PUT)
			->add('/test', [ HomeController::class, 'deleteUser' ], Routes::DELETE);

		$routes->add('/test', function () {
		}, [ Routes::PATCH, Routes::POST ]);

		$routes->get("/test-get", function () {
			echo "Hello from test-get";
		});

		$routes->post("/test-post", function () {
			echo "Hello from test-post";
		});

		$routes->put("/test-put", function () {
			echo "Hello from test-put";
		});

		$routes->patch("/test-patch", function () {
			echo "Hello from test-patch";
		});

		$routes->delete("/test-delete", function () {
			echo "Hello from test-delete";
		});

		$routes->add('/test/{int:userID}-{username}/{float:amount}/{bool:valid}', function (
			Request $request,
			int     $userID,
			string  $username,
			float   $amount,
			bool    $valid
		) {
			echo 'Dynamic route here';
		});

		$routes->add(
			'/test/{int:userID}-{username}/{float:amount}/{bool:valid}',
			[ HomeController::class, 'test' ],
			[ Routes::PUT ]
		); # It works like this also

		$routes
			->middleware([
				[ Middleware::class, 'verify_token' ],
				[ Middleware::class, 'test' ],
				'verify_token',
			])
			->add('/test-hello', function (Request $request) {
				$request->send([ 'message' => 'Hello' ]);
			});


		$routes
			->middleware([
				'test_middleware',
				'has_roles' => 'admin,user',
				[ Middleware::class, 'test_method' ],
				[ Middleware::class, 'has_role', 'Admin', 'Moderator', [ 'User', 'Bot' ] ],
			])
			->add('/testing', function (Request $request) {
				$request->send([ 'msg' => 'testing' ]);
			});


		$routes->add('/', function (Request $request) {
			echo "<pre>";
			print_r([ $_REQUEST, $_FILES ]);
		}, [ Routes::PATCH ]);

		$routes->add('/', function (Request $request) {
			echo "<pre>";
			print_r([ $_REQUEST, $_FILES ]);
		}, [ Routes::PUT ]);


		//This $otherRoutes variable could be defined in a different file and included here only via require or include
		$otherRoutes = new Routes();
		$otherRoutes->prefix("/sample")
					->get("/", function (Request $request) { })
					->get("/sample1", function (Request $request) { })
					->get("/sample2", function (Request $request) { })
					->save();

		$routes->append($otherRoutes->get_routes());


		$routes->add("/headers", function (Request $request) {
			$request->header("Content-type", "text/plain")
					->header([ "foo" => "bar", "best" => "test" ])
					->header((object) [ "X-Auth" => "Token {token-123}" ])
					->status(201)
					->send([ "message" => "hello" ]);
		}, Routes::GET);

		$routes->add(
			'/demo',
			[ HomeController::class, 'dependency_injection_test', [ new InjectedClass() ] ],
			Routes::GET
		);

		$routes->add(
			"/inject",
			[ InjectController::class ]
		);

		$routes->handle();
	} catch ( RouteNotFoundException $ex ) {
		$routes->request->status(404, 'Route not found')->send([ 'error' => [ 'message' => $ex->getMessage() ] ]);
	} catch ( CallbackNotFound $ex ) {
		$routes->request->status(404, 'Callback method not found')
						->send([ 'error' => [ 'message' => $ex->getMessage() ] ]);
	} catch ( Exception $ex ) {
		$code = $ex->getCode() ?? 500;
		$routes->request->status($code)->send([ 'error' => [ 'message' => $ex->getMessage() ] ]);
	}

	function test_route_function() : void {
		echo json_encode([ 'message' => 'Welcome from test route' ]);
	}

	function verify_token() : void {
		//Do something
	}

	function has_roles(string $allowedRoles) : void {
		//Do something
	}

	function test_middleware() : void {

	}