<?php

	namespace Gac\Routing\PSR;

	use Psr\Http\Message\ResponseInterface;
	use Psr\Http\Message\ServerRequestInterface;
	use Psr\Http\Server\RequestHandlerInterface;

	class RequestHandler implements RequestHandlerInterface
	{

		/**
		 * Representation of an outgoing, server-side response.
		 *
		 * Per the HTTP specification, this interface includes properties for
		 * each of the following:
		 *
		 * - Protocol version
		 * - Status code and reason phrase
		 * - Headers
		 * - Message body
		 *
		 * Responses are considered immutable; all methods that might change state MUST
		 * be implemented such that they retain the internal state of the current
		 * message and return an instance that contains the changed state.
		 */
		public function handle(ServerRequestInterface $request): ResponseInterface
		{
			// TODO: Implement handle() method.
		}
	}
