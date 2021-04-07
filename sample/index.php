<?php

	use Gac\Routing\Exceptions\CallbackNotFound;
	use Gac\Routing\Exceptions\RouteNotFoundException;
	use Gac\Routing\Request;
	use Gac\Routing\Routes;
	use Gac\Routing\sample\HomeController;

	#include_once "../Routes.php"; # IF YOU'RE NOT USING composer
	#include_once "HomeController.php"; # IF YOU'RE NOT USING composer

	include_once "../vendor/autoload.php"; # IF YOU'RE USING composer

	$routes = new Routes();
	try {
		$routes->add('/', function (Request $request) {
			$request
				->status(200, "OK")
				->send(["message" => "Welcome"]);
		});

		$routes->add('/test', "test_route_function", [Routes::GET, Routes::POST]);

		$routes->prefix("/user")
			->middleware(["verify_token"])
			->add('/', [HomeController::class, "getUsers"], Routes::GET)
			->add("/", [HomeController::class, "addUser"], Routes::POST)
			->add("/", [HomeController::class, "updateUser"], Routes::PATCH)
			->add("/", [HomeController::class, "replaceUser"], Routes::PUT)
			->add("/", [HomeController::class, "deleteUser"], Routes::DELETE);


		$routes->add("/test/{int:userID}-{username}/{float:amount}/{bool:valid}", function (
			Request $request,
			int $userID,
			string $username,
			float $amount,
			bool $valid
		) {
			echo "Dynamic route here";
		});

		$routes->route();
	} catch (RouteNotFoundException $ex) {
		$routes->request->status(404, "Route not found")->send(["error" => ["message" => $ex->getMessage()]]);
	} catch (CallbackNotFound $ex) {
		$routes->request->status(404, "Callback method not found")->send(["error" => ["message" => $ex->getMessage()]]);
	} catch (Exception $ex) {
		$code = $ex->getCode() ?? 500;
		$routes->request->status($code)->send(["error" => ["message" => $ex->getMessage()]]);
	}

	function test_route_function() {
		echo json_encode(["message" => "Welcome from test route"]);
	}

	function verify_token() {
		// verify tokens here
	}