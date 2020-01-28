<?php declare(strict_types=1);

namespace Notes\Module2;

use League\Route\Http\Exception\ForbiddenException;
use Notes\Util\Database;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuthMiddleware implements MiddlewareInterface
{
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
	{
		$authentication = $request->getHeader('authorization')[0];
		$base64Auth = trim(substr($authentication, 5));
		$decoded = explode(':', base64_decode($base64Auth));

		$db = new Database();
		try {
			$user = $db->getUserByEmail($decoded[0]);

			// @TODO Proper hash comparison
			if (hash_equals($user->password, $decoded[1])) {
				if (session_status() !== PHP_SESSION_ACTIVE) {
					session_start();
				}

				$_SESSION['userId'] = $user->userId;

				return $handler->handle($request);
			}
		} catch (\Exception $e) {}

		throw new ForbiddenException();
	}
}