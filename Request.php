<?php


	namespace Gac\Routing;


	class Request
	{
		/**
		 * @var array Request data
		 */
		private array $data;

		/**
		 * Request constructor.
		 */
		public function __construct()
		{
			$input = json_decode(file_get_contents('php://input')) ?? [];
			$_REQUEST = array_merge($_REQUEST, (array)$input);
			$this->data = $_REQUEST;
		}

		/**
		 * Returns a value for a specified body argument
		 *
		 * @param string $key Which request body argument to be returned
		 *
		 * @return mixed Body argument value or NULL if the argument doesn't exist
		 */
		public function get(string $key = ''): mixed
		{
			return $this->data[$key] ?? NULL;
		}

		/**
		 * Returns list of all the header items or a value of a specific item
		 *
		 * @param string $key Name of a specific item in the header list to return the value for
		 *
		 * @return array|string|null List of header values or a value of a single item
		 */
		public function headers(string $key = ''): array|string|null
		{
			$headers = getallheaders();
			return empty($key) ? $headers : $headers[$key] ?? NULL;
		}

		/**
		 * Sets the header status code for the response
		 *
		 * @param int $statusCode Status code to be set for the response
		 * @param string $message Message to be returned in the header alongside the status code
		 *
		 * @return Request Returns an instance of the Request class so that it can be chained on
		 */
		public function status(int $statusCode = 200, string $message = ''): self
		{
			header("HTTP/1.1 $statusCode $message");
			return $this;
		}

		/**
		 * Send response back
		 *
		 * @param string|array|object $output Value to be outputted as part of the response
		 */
		public function send(string|array|object $output)
		{
			echo json_encode($output);
		}
	}