<?php

	namespace Gac\Routing\sample;

	use Gac\Routing\Request;
	use Gac\Routing\Response;
	use JsonException;

	class HomeController
	{
		public function __construct(protected ?InjectedClass $injected = NULL) { }

		public function index() : void {
			echo "Hello from controller";
		}

		/**
		 * @throws JsonException
		 */
		public function home(Request $request) : void {
			//$request->send([ "message" => "Hello from controller::home" ]); // Old way of doing it

			$request
				->header("Access-Control-Allow-Origin", "https://demo.local")
				->header("Content-Type", "application/json")
				->status(401, 'Not Authorized')
				->send([]);

			// New way of doing it
			Response::
			withHeader("Access-Control-Allow-Origin", "https://demo.local")::
			withHeader("Content-Type", "application/json")::
			withStatus(401, 'Not authorized')::
			//withBody([])::
			send([]);
			//send();
		}

		public function getUsers(Request $request) : void {
			$request->send([ "message" => "Hello from controller::home", "ses" => $_SESSION ]);
		}

		public function addUser(Request $request) : void {
			$request->send([ "message" => "Hello from controller::home" ]);
		}

		public function updateUser(Request $request) : void {
			$request->send([ "message" => "Hello from controller::home" ]);
		}

		public function replaceUser(Request $request) : void {
			$request->send([ "message" => "Hello from controller::home" ]);
		}

		public function deleteUser(Request $request) : void {
			$request->send([ "message" => "Hello from controller::home" ]);
		}

		public function test(Request $request, int $userID, string $username, float $amount, bool $valid) : void {
			var_dump($this->injected);
			echo "Dynamic route here";
		}

		public function dependency_injection_test(Request $request) : string {
			//$request->send([ "isInstanceOf" => $this->injected instanceof InjectedClass ]);
			return json_encode([ 'isInstanceOf' => $this->injected instanceof InjectedClass ]);
		}
	}