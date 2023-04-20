<?php
	declare( strict_types=1 );

	namespace Gac\Routing;

	use JsonException;

	class Response
	{
		/**
		 * @var int HTTP status code to be sent in the response header
		 */
		private static int $statusCode = 200;

		/**
		 * @var string  HTTP status message to be sent in the response header
		 */
		private static string $statusMessage = "";

		/**
		 * @var string HTTP version to be sent in the response header
		 */
		private static string $httpVersion = "HTTP/1.1";

		/**
		 * @var mixed|string HTTP response body content
		 */
		private static mixed $body = "";

		/**
		 * @var Response|null Instance of the Response class
		 */
		private static ?Response $instance = NULL;

		/**
		 * Use to send the output back to the client
		 *
		 * @param string $type Type of response to be returned (ex JSON)
		 *
		 * @throws JsonException If the response type == JSON and json_encode fails to encode the body data
		 *
		 * @return Response Returns an instance of itself to allow method chaining
		 */
		public static function send(array|object|string $data = NULL, string $type = "JSON") : self {
			if ( !is_null($data) ) {
				self::$body = $data;
			}

			echo match ( mb_strtoupper($type) ) {
				"JSON" => self::json(),
			};

			return self::getInstance();
		}

		/**
		 * Private method for turning response body into json encoded string
		 *
		 * @throws JsonException If the response type == JSON and json_encode fails to encode the body data
		 *
		 * @return string|bool Returns a JSON encoded string on success or FALSE on failure
		 */
		public static function json() : string|bool {
			return json_encode(self::$body, JSON_THROW_ON_ERROR);
		}

		/**
		 * Method used to set the response body data that will be returned with send method
		 *
		 * @param array|object|string $data Body data to be set
		 *
		 * @return Response Returns an instance of itself to allow method chaining
		 */
		public static function withBody(array|object|string $data) : self {
			self::$body = $data;
			return self::getInstance();
		}

		/**
		 * Private method used to set the HTTP status on the response header
		 *
		 * @param bool $replace Indicated if the similar existing header value should be replaced on second one appended
		 *
		 * @return void
		 */
		private static function setHTTPStatus(bool $replace = false) : void {
			header(self::$httpVersion . ' ' . self::$statusCode . ' ' . self::$statusMessage, $replace,
				self::$statusCode);
		}

		/**
		 * Method used to set the HTTP status code and message in the response header
		 *
		 * @param int $code HTTP status code to be sent back
		 * @param string $message HTTP status message to be sent back
		 *
		 * @return Response Returns an instance of itself to allow method chaining
		 */
		public static function withStatus(int $code, string $message) : self {
			self::$statusCode = $code;
			self::$statusMessage = $message;
			self::setHTTPStatus();
			return self::getInstance();
		}

		/**
		 * Method used for setting a key-value pair in the response header
		 *
		 * @param string|array|object $key Header key
		 * @param mixed $value Header value
		 *
		 * @return Response Returns an instance of itself to allow method chaining
		 */
		public static function withHeader(string|array|object $key, mixed $value) : self {
			if ( is_string($key) ) {
				header("$key: $value");
			} elseif ( is_array($key) || is_object($key) ) {
				$keys = $key;
				foreach ( $keys as $key => $value ) {
					header("$key: $value");
				}
			}

			return self::getInstance();
		}

		/**
		 * Method used for retrieving an instance of the Response class
		 *
		 * @return Response Returns an existing instance of itself or creates a new one
		 */
		public static function getInstance() : Response {
			if ( is_null(self::$instance) ) {
				self::$instance = new static();
			}

			return self::$instance;
		}

		/**
		 * Method used for setting HTTP status code in the response header
		 *
		 * @param int $statusCode HTTP status code
		 *
		 * @return Response Returns an instance of itself to allow method chaining
		 */
		public static function setStatusCode(int $statusCode) : self {
			self::$statusCode = $statusCode;
			self::setHTTPStatus(true);
			return self::getInstance();
		}

		/**
		 * Method used for setting HTTP status message in the resposne header
		 *
		 * @param string $statusMessage HTTP status message
		 *
		 * @return Response Returns an instance of itself to allow method chaining
		 */
		public static function setStatusMessage(string $statusMessage) : self {
			self::$statusMessage = $statusMessage;
			self::setHTTPStatus(true);
			return self::getInstance();
		}

		/**
		 * Method used for setting HTTP version in the response header
		 *
		 * @param string $httpVersion HTTP version (ex HTTP/1.0 or HTTP/1.1)
		 *
		 * @return Response Returns an instance of itself to allow method chaining
		 */
		public static function setHttpVersion(string $httpVersion) : self {
			self::$httpVersion = $httpVersion;
			return self::getInstance();
		}

		/**
		 * Method used for retrieving response body data
		 *
		 * @return mixed Returns body data to be or that was already sent back
		 */
		public static function getBody() : mixed {
			return self::$body;
		}

		/**
		 * Method used for retrieving HTTP status code for the response
		 *
		 * @return int Returns HTTP status code
		 */
		public static function getStatusCode() : int {
			return self::$statusCode;
		}

		/**
		 * Method used for retrieving HTTP status message for the response
		 *
		 * @return string Returns HTTP status message
		 */
		public static function getStatusMessage() : string {
			return self::$statusMessage;
		}
	}