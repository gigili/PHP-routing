<?php


	namespace Gac\Routing\sample;


	use Gac\Routing\Request;

	class Middleware
	{

		public function verify_token(Request $request) : void {
			//Do something
		}

		public static function test(Request $request) : void {
			//Do something
		}

		//The $request argument can be set at any position as an argument, it will be dynamically passed down
		public function has_role(string $adminRole, string $userRole, array $otherRoles, Request $request) : void {
			print_r([ $adminRole, $userRole, $otherRoles, $request->headers("Host") ]);
			echo "<br/>";
		}

		//The $request argument can also be emitted if it is not needed
		public function test_method() : void {
			echo 'RolesMiddleware test method' . PHP_EOL;
		}
	}