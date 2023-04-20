<?php


	use Gac\Routing\Response;

	it("can get an instance of a response class", function () {
		$response = Response::getInstance();
		expect($response)->toBeInstanceOf(Response::class);
	});

	it("can set response body data", function () {
		$response = Response::getInstance();
		$data = [ "message" => "OK" ];
		$response::withBody($data);

		expect($response::getBody())->toEqual($data);
	});

	it("can convert array into json", function () {
		$response = Response::getInstance();
		$array = [ "message" => "Hello World" ];
		$converted = json_encode($array);

		$result = $response::withBody($array)::json();

		expect($result)
			->toBeJson()
			->and($result)
			->toEqual($converted);
	});

	it("can set HTTP status code", function () {
		$response = Response::getInstance();
		try {
			$response::setStatusCode(401);
		} catch ( Exception ) {
			//silently ignore because of headers already sent error
		} finally {
			expect($response::getStatusCode())->toEqual(401);
		}
	});

	it("can set HTTP status message", function () {
		$response = Response::getInstance();
		try {
			$response::setStatusMessage('Not Authorized');
		} catch ( Exception ) {
			//silently ignore because of headers already sent error
		} finally {
			expect($response::getStatusMessage())->toEqual('Not Authorized');
		}
	});

	it("can set both HTTP status message and code", function () {
		$response = Response::getInstance();
		try {
			$response::withStatus(404, 'Not Found');
		} catch ( Exception ) {
			//silently ignore because of headers already sent error
		} finally {
			expect($response::getStatusCode())
				->toEqual(404)
				->and($response::getStatusMessage())
				->toEqual("Not Found");
		}
	});