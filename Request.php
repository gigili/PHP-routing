<?php


	namespace Gac\Routing;


	class Request
	{
		private array $data;

		public function __construct() {
			$input = json_decode(file_get_contents("php://input")) ?? [];
			$_REQUEST = array_merge($_REQUEST, (array)$input);
			$this->data = $_REQUEST;
		}

		public function get(string $key = "") {
			return $this->data[$key] ?? NULL;
		}

		public function status(int $statusCode = 200, string $message = ""): self {
			header("HTTP/1.1 {$statusCode} {$message}");
			return $this;
		}

		public function send(string|array|object $output) {
			echo json_encode($output);
		}
	}