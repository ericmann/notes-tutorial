<?php declare(strict_types=1);

namespace Notes\Module5;

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

		$authorization = $request->getHeader('authorization')[0];
		$authParts = explode(' ', $authorization);
		if ($authParts[0] === 'Bearer') {
			session_id($authParts[1]);
			session_start();

			if (!empty($_SESSION['userId'])) {
				return $handler->handle($request);
			}
		}

		throw new ForbiddenException();
	}
}