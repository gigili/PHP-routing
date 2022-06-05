<?php

	namespace Gac\Routing\sample;

	/**
	 * Author: Igor Ilić <github@igorilic.net>
	 * Date: 2022-06-04
	 * Project: PHP-routing
	 */
	class InjectController
	{
		public function __construct(protected InjectedClass $injectedClass) { }

		public function __invoke() : void {
			echo "Hello";
		}
	}