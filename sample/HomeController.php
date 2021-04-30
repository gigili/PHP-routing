<?php

	namespace Gac\Routing\sample;

	use Gac\Routing\Request;

	class HomeController
	{
		public function index()
		{
			echo "Hello from controller";
		}

		public function home(Request $request)
		{
			$request->send([ "message" => "Hello from controller::home" ]);
		}

		public function getUsers(Request $request)
		{
			$request->send([ "message" => "Hello from controller::home", "ses" => $_SESSION ]);
		}

		public function addUser(Request $request)
		{
			$request->send([ "message" => "Hello from controller::home" ]);
		}

		public function updateUser(Request $request)
		{
			$request->send([ "message" => "Hello from controller::home" ]);
		}

		public function replaceUser(Request $request)
		{
			$request->send([ "message" => "Hello from controller::home" ]);
		}

		public function deleteUser(Request $request)
		{
			$request->send([ "message" => "Hello from controller::home" ]);
		}

		public function test(Request $request, int $userID, string $username, float $amount, bool $valid)
		{
			echo "Dynamic route here";
		}
	}