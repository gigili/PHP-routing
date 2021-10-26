<?php


	namespace Gac\Routing\sample;


	use Gac\Routing\Request;

	class Middleware
	{

		public function verify_token(Request $request)
		{
			//Do something
		}

		public static function test(Request $request)
		{
			//Do something
		}

		//The $request argument can be set at any position as an argument, it will be dynamically passed down
		public function has_role(string $adminRole, string $userRole, array $otherRoles, Request $request) {
			print_r([ $adminRole, $userRole, $otherRoles, $request->headers("Host") ]);
			echo PHP_EOL;
		}

		//The $request argument can also be emitted if it is not needed
		public function test_method() {
			echo 'RolesMiddleware test method' . PHP_EOL;
		}
	}