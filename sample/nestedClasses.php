<?php
	/**
	 * Author: Igor Ilić <github@igorilic.net>
	 * Date: 2022-06-05
	 * Project: PHP-routing
	 */
	declare( strict_types=1 );

	namespace Gac\Routing\sample;

	class A
	{
		public function __construct(protected B $b, protected C $c, protected bool $test) { }
	}

	class B
	{
		public function __construct(protected C $c) { }
	}

	class C
	{
		public function __construct() { }
	}