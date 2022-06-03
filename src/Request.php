<?php
	declare( strict_types=1 );

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
		public function __construct() {
			$requestMethod = mb_strtoupper(( $_SERVER['REQUEST_METHOD'] ?? "" ));
			if ( isset($_SERVER['REQUEST_METHOD']) && in_array($requestMethod, [ Routes::PATCH, Routes::PUT ]) ) {
				$input = $this->parse_patch_and_put_request_data();
			} else {
				$rawInput = file_get_contents('php://input');

				$input = json_decode($rawInput) ?? [];
				if ( is_array($input) && count($input) == 0 ) {
					mb_parse_str($rawInput, $input);
				}
			}

			$_REQUEST = array_merge($_REQUEST, (array) $input);
			$this->data = $_REQUEST;
		}

		/**
		 * Returns a value for a specified body argument
		 *
		 * @param string $key Which request body argument to be returned
		 *
		 * @return mixed Body argument value or NULL if the argument doesn't exist
		 */
		public function get(string $key = '') : mixed {
			return $this->data[$key] ?? NULL;
		}

		/**
		 * Returns list of all the header items or a value of a specific item
		 *
		 * @param string $key Name of a specific item in the header list to return the value for
		 *
		 * @return array|string|null List of header values or a value of a single item
		 */
		public function headers(string $key = '') : array|string|null {
			$headers = getallheaders();
			return empty($key) ? $headers : $headers[$key] ?? NULL;
		}

		/**
		 * Sets the header status code for the response
		 *
		 * @param int $statusCode Status code to be set for the response
		 * @param string $message Message to be sent int the header alongside the status code
		 *
		 * @return Request Returns an instance of the Request class so that it can be chained on
		 */
		public function status(int $statusCode = 200, string $message = '') : self {
			header("HTTP/1.1 $statusCode $message");
			return $this;
		}

		/**
		 * Method used for setting custom header properties
		 *
		 * @param string|array|object $key Header key value
		 * @param mixed $value Header value
		 *
		 * @return Request Returns an instance of the Request class so that it can be chained on
		 */
		public function header(string|array|object $key, mixed $value = NULL) : self {
			if ( is_string($key) ) {
				header("$key: $value");
			} elseif ( is_array($key) || is_object($key) ) {
				$keys = $key;
				foreach ( $keys as $key => $value ) {
					header("$key: $value");
				}
			}
			return $this;
		}

		/**
		 * Send response back
		 *
		 * @param string|array|object $output Value to be outputted as part of the response
		 * @param array|object|null $headers Optional list of custom header properties to be sent with the response
		 */
		public function send(string|array|object $output, array|object|null $headers = NULL) : void {
			if ( !is_null($headers) ) {
				$this->header($headers);
			}
			echo json_encode($output);
		}

		/**
		 * Private method used for parsing request body data for PUT and PATCH requests
		 *
		 * @return array Return an array of request body data
		 */
		private function parse_patch_and_put_request_data() : array {

			/* PUT data comes in on the stdin stream */
			$putData = fopen('php://input', 'r');

			$raw_data = '';

			/* Read the data 1 KB at a time and write to the file */
			while ( $chunk = fread($putData, 1024) )
				$raw_data .= $chunk;

			/* Close the streams */
			fclose($putData);

			// Fetch content and determine boundary
			$boundary = substr($raw_data, 0, strpos($raw_data, "\r\n"));

			if ( empty($boundary) ) {
				parse_str($raw_data, $data);
				return $data ?? [];
			}

			// Fetch each part
			$parts = array_slice(explode($boundary, $raw_data), 1);
			$data = [];

			foreach ( $parts as $part ) {
				// If this is the last part, break
				if ( $part == "--\r\n" ) break;

				// Separate content from headers
				$part = ltrim($part, "\r\n");
				[ $raw_headers, $body ] = explode("\r\n\r\n", $part, 2);

				// Parse the headers list
				$raw_headers = explode("\r\n", $raw_headers);
				$headers = [];
				foreach ( $raw_headers as $header ) {
					[ $name, $value ] = explode(':', $header);
					$headers[strtolower($name)] = ltrim($value, ' ');
				}

				// Parse the Content-Disposition to get the field name, etc.
				if ( isset($headers['content-disposition']) ) {
					$filename = NULL;
					preg_match(
						'/^(.+); *name="([^"]+)"(; *filename="([^"]+)")?/',
						$headers['content-disposition'],
						$matches
					);
					[ , $type, $name ] = $matches;

					//Parse File
					if ( isset($matches[4]) ) {
						//if labeled the same as previous, skip
						if ( isset($_FILES[$matches[2]]) ) {
							continue;
						}

						//get filename
						$filename = $matches[4];

						//get tmp name
						$filename_parts = pathinfo($filename);
						$tmp_name = tempnam(ini_get('upload_tmp_dir'), $filename_parts['filename']);

						//populate $_FILES with information, size may be off in multibyte situation
						$_FILES[$matches[2]] = [
							'error' => 0,
							'name' => $filename,
							'tmp_name' => $tmp_name,
							'size' => strlen($body),
							'type' => $type,
						];

						//place in temporary directory
						file_put_contents($tmp_name, $body);
					} else { //Parse Field
						$data[$name] = substr($body, 0, strlen($body) - 2);
					}
				}
			}
			return $data ?? [];
		}
	}
