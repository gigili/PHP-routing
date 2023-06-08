<?php

	namespace Gac\Routing\PSR;

	use Psr\Http\Message\MessageInterface;
	use Psr\Http\Message\RequestInterface;
	use Psr\Http\Message\ServerRequestInterface;
	use Psr\Http\Message\StreamInterface;
	use Psr\Http\Message\UriInterface;

	class ServerRequest implements ServerRequestInterface
	{
		private string $version;
		private array $headers;
		private StreamInterface $body;
		private string $method;

		private array $attributes = [];

		private array $files = [];

		private array $queryParams = [];

		private Uri $uri;

		public function __construct(StreamInterface $body, string $version = NULL)
		{
			$this->version = $version ?? $_SERVER["SERVER_PROTOCOL"] ?? "1.1";
			$this->body = $body;
			$this->method = $_SERVER["REQUEST_METHOD"];
			$this->getHeaders();
		}

		/**
		 * @inheritDoc
		 */
		public function getProtocolVersion(): string
		{
			return explode("/", $this->version)[1];
		}

		/**
		 * @inheritDoc
		 */
		public function withProtocolVersion(string $version): MessageInterface
		{
			$tmp = $this;
			$tmp->version = $version;
			return $tmp;
		}

		/**
		 * @inheritDoc
		 */
		public function getHeaders(): array
		{
			if (isset($this->headers)) return $this->headers;

			if (function_exists("apache_request_headers")) {
				$headers = apache_request_headers() ?? NULL;
				return $headers ?? [];
			}

			$headers = [];
			foreach ($_SERVER as $key => $value) {
				if (str_starts_with("HTTP_", $key)) {
					$k = str_replace("HTTP_", "", $key);
					$headers[$k] = $value;
				}
			}

			$this->headers = $headers;
			return $headers;
		}

		/**
		 * @inheritDoc
		 */
		public function hasHeader(string $name): bool
		{
			return isset($this->headers[$name]);
		}

		/**
		 * @inheritDoc
		 */
		public function getHeader(string $name): array
		{
			$value = $this->headers[$name] ?? [];

			if (!is_array($value)) {
				$value = [$value];
			}

			return $value;
		}

		/**
		 * @inheritDoc
		 */
		public function getHeaderLine(string $name): string
		{
			return implode(", ", $this->headers);
		}

		/**
		 * @inheritDoc
		 */
		public function withHeader(string $name, $value): MessageInterface
		{
			$tmp = $this;
			$tmp->headers[$name] = $value;
			return $tmp;
		}

		/**
		 * @inheritDoc
		 */
		public function withAddedHeader(string $name, $value): MessageInterface
		{
			$tmp = $this;
			return $tmp->withHeader($name, $value);
		}

		/**
		 * @inheritDoc
		 */
		public function withoutHeader(string $name): MessageInterface
		{
			$tmp = $this;
			if (isset($tmp->headers[$name])) {
				unset($tmp->headers[$name]);
			}

			return $tmp;
		}

		/**
		 * @inheritDoc
		 */
		public function getBody(): StreamInterface
		{
			return $this->body;
		}

		/**
		 * @inheritDoc
		 */
		public function withBody(StreamInterface $body): MessageInterface
		{
			$tmp = $this;
			$tmp->body = $body;
			return $tmp;
		}

		/**
		 * @inheritDoc
		 */
		public function getRequestTarget(): string
		{
			return $_SERVER["REQUEST_URI"] ?? "/";
		}

		/**
		 * @inheritDoc
		 */
		public function withRequestTarget(string $requestTarget): RequestInterface
		{
			return $this;
		}

		/**
		 * @inheritDoc
		 */
		public function getMethod(): string
		{
			return $this->method;
		}

		/**
		 * @inheritDoc
		 */
		public function withMethod(string $method): RequestInterface
		{
			$tmp = $this;
			$tmp->method = $method;
			return $tmp;
		}

		/**
		 * @inheritDoc
		 */
		public function getUri(): UriInterface
		{
			return $this->uri;
		}

		/**
		 * @inheritDoc
		 */
		public function withUri(UriInterface $uri, bool $preserveHost = false): RequestInterface
		{
			$tmp = $this;
			$tmp->uri = $uri;
			return $tmp;
		}

		/**
		 * @inheritDoc
		 */
		public function getServerParams(): array
		{
			return $_SERVER;
		}

		/**
		 * @inheritDoc
		 */
		public function getCookieParams(): array
		{
			return $_COOKIE;
		}

		/**
		 * @inheritDoc
		 */
		public function withCookieParams(array $cookies): ServerRequestInterface
		{
			return $this;
		}

		/**
		 * @inheritDoc
		 */
		public function getQueryParams(): array
		{
			return $this->queryParams;
		}

		/**
		 * @inheritDoc
		 */
		public function withQueryParams(array $query): ServerRequestInterface
		{
			$tmp = $this;
			$tmp->queryParams = $query;
			return $tmp;
		}

		/**
		 * @inheritDoc
		 */
		public function getUploadedFiles(): array
		{
			return $this->files;
		}

		/**
		 * @inheritDoc
		 */
		public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface
		{
			$tmp = $this;
			$tmp->files = $uploadedFiles;
			return $tmp;
		}

		/**
		 * @inheritDoc
		 */
		public function getParsedBody(): object|array|null
		{
			return $_REQUEST;
		}

		/**
		 * @inheritDoc
		 */
		public function withParsedBody($data): ServerRequestInterface
		{
			return $this;
		}

		/**
		 * @inheritDoc
		 */
		public function getAttributes(): array
		{
			return $this->attributes;
		}

		/**
		 * @inheritDoc
		 */
		public function getAttribute(string $name, $default = null)
		{
			return $this->attributes[$name] ?? $default;
		}

		/**
		 * @inheritDoc
		 */
		public function withAttribute(string $name, $value): ServerRequestInterface
		{
			$tmp = $this;
			$tmp->attributes[$name] = $value;
			return $tmp;
		}

		/**
		 * @inheritDoc
		 */
		public function withoutAttribute(string $name): ServerRequestInterface
		{
			$tmp = $this;
			if (isset($tmp->attributes[$name])) {
				unset($tmp->attributes[$name]);
			}

			return $tmp;
		}
	}
