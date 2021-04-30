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
	}