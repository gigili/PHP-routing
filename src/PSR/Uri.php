<?php

	namespace Gac\Routing\PSR;

	use Psr\Http\Message\UriInterface;

	class Uri implements \Psr\Http\Message\UriInterface
	{

		/**
		 * @inheritDoc
		 */
		public function getScheme(): string
		{
			// TODO: Implement getScheme() method.
		}

		/**
		 * @inheritDoc
		 */
		public function getAuthority(): string
		{
			// TODO: Implement getAuthority() method.
		}

		/**
		 * @inheritDoc
		 */
		public function getUserInfo(): string
		{
			// TODO: Implement getUserInfo() method.
		}

		/**
		 * @inheritDoc
		 */
		public function getHost(): string
		{
			// TODO: Implement getHost() method.
		}

		/**
		 * @inheritDoc
		 */
		public function getPort(): ?int
		{
			// TODO: Implement getPort() method.
		}

		/**
		 * @inheritDoc
		 */
		public function getPath(): string
		{
			// TODO: Implement getPath() method.
		}

		/**
		 * @inheritDoc
		 */
		public function getQuery(): string
		{
			// TODO: Implement getQuery() method.
		}

		/**
		 * @inheritDoc
		 */
		public function getFragment(): string
		{
			// TODO: Implement getFragment() method.
		}

		/**
		 * @inheritDoc
		 */
		public function withScheme(string $scheme): UriInterface
		{
			// TODO: Implement withScheme() method.
		}

		/**
		 * @inheritDoc
		 */
		public function withUserInfo(string $user, ?string $password = null): UriInterface
		{
			// TODO: Implement withUserInfo() method.
		}

		/**
		 * @inheritDoc
		 */
		public function withHost(string $host): UriInterface
		{
			// TODO: Implement withHost() method.
		}

		/**
		 * @inheritDoc
		 */
		public function withPort(?int $port): UriInterface
		{
			// TODO: Implement withPort() method.
		}

		/**
		 * @inheritDoc
		 */
		public function withPath(string $path): UriInterface
		{
			// TODO: Implement withPath() method.
		}

		/**
		 * @inheritDoc
		 */
		public function withQuery(string $query): UriInterface
		{
			// TODO: Implement withQuery() method.
		}

		/**
		 * @inheritDoc
		 */
		public function withFragment(string $fragment): UriInterface
		{
			// TODO: Implement withFragment() method.
		}

		/**
		 * @inheritDoc
		 */
		public function __toString(): string
		{
			// TODO: Implement __toString() method.
		}
	}
