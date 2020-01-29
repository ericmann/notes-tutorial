<?php declare(strict_types=1);

namespace Notes\Module3;

use League\Route\Http\Exception\ForbiddenException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuthMiddleware implements MiddlewareInterface
{
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
	{
		if (Server::authenticate($request)) {
			return $handler->handle($request);
		}

		// @TODO Check for a Bearer token and set up the session
		$authorization = $request->getHeader('authorization')[0];

		throw new ForbiddenException();
	}
}