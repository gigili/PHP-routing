<?php

	use Gac\Routing\Exceptions\RouteNotFoundException;
	use Gac\Routing\Routes;

	function verify_token(?string $token = "") {
		echo "Token: $token<br/>";
	}

	# include_once "../Routes.php"; IF NOT USING composer

	try {
		$routes = new Routes();

		$routes->add('/', function () {
			echo "Welcome";
		})->middleware([
			["verify_token", "test"]
		]);

		$routes->add('/test', function () {
			echo "Welcome to test route";
		});

		$routes->add('/test_middleware', function () {
			echo "This will call middleware function without passing the parameter";
		})->middleware(["verify_token"]);

		$routes->route();
	} catch (RouteNotFoundException $ex) {
		header("HTTP/1.1 404");
		echo "Route not found";
	} catch (Exception $ex) {
		die("API Error: {$ex->getMessage()}");
	}