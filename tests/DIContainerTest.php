<?php
	/**
	 * Author: Igor Ilić <github@igorilic.net>
	 * Date: 2022-06-05
	 * Project: PHP-routing
	 */

	use Gac\Routing\DIContainer;
	use Gac\Routing\sample\A;
	use Gac\Routing\sample\B;
	use Gac\Routing\sample\C;
	use Gac\Routing\sample\InjectController;
	use Gac\Routing\sample\InjectedClass;

	it("can resolve dependencies", function () {
		if ( !class_exists("InjectController") ) {
			include_once __DIR__ . "/../sample/InjectController.php";
		}

		if ( !class_exists("InjectedClass") ) {
			include_once __DIR__ . "/../sample/InjectedClass.php";
		}

		$data = DIContainer::get(InjectController::class, []);

		expect($data)
			->toBeArray()
			->toHaveCount(1)
			->and($data["injectedClass"])
			->toBeInstanceOf(InjectedClass::class);
	});

	it("can handle nested dependency injection", function () {
		include_once __DIR__ . '/../sample/nestedClasses.php';

		$data = DIContainer::get(A::class, [ "test" => true, new C ]);

		expect($data)
			->toBeArray()
			->toHaveCount(4)
			->and($data['b'])
			->toBeInstanceOf(B::class)
			->and($data['c'])
			->toBeInstanceOf(C::class)
			->and($data["test"])
			->toEqual(true);
	});