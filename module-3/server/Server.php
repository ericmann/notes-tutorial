<?php declare(strict_types=1);

namespace Notes\Module3;

use GuzzleHttp\Exception\BadResponseException;
use League\Route\Http\Exception\BadRequestException;
use League\Route\Http\Exception\ForbiddenException;
use Notes\Util\Database;
use Notes\Util\Types\BaseNote;
use Notes\Util\Types\BaseUser;
use Notes\Util\Types\User;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Laminas\Diactoros\Response\EmptyResponse;

class Server
{
	private $db;

	private $user;

	public function __construct()
	{
		$this->db = new Database();
	}

	private function getParsedBody(ServerRequestInterface $request): array
	{
		$body = $request->getBody()->getContents();
		return json_decode($body, true);
	}

	public function getNotes(ServerRequestInterface $request): array
	{
		$notes = $this->db->getNotes($_SESSION['userId']);

		return $notes;
	}

	public function getNote(ServerRequestInterface $request, array $args): array
	{
		try {
			$note = $this->db->getNote($args['id']);
		} catch (\Exception $e) {
			// Squash the exception
			return [];
		}

		if ($note->owner === $_SESSION['userId']) {
			return (array) $note;
		}

		throw new ForbiddenException();
	}

	public function createNote(ServerRequestInterface $request): array
	{
		$parsed = $this->getParsedBody($request);
		$contents = $parsed['note'];

		if (empty($contents)) {
			throw new BadRequestException();
		}

		$note = new BaseNote();
		$note->owner = $_SESSION['userId'];
		$note->note = $contents;

		$noteId = $this->db->createNote($note);

		return [
			'noteId' => $noteId
		];
	}

	public function deleteNote(ServerRequestInterface $request, array $args): ResponseInterface
	{
		$success = false;
		try {
			$note = $this->db->getNote($args['id']);

			if ($note->owner === $_SESSION['userId']) {
				$success = $this->db->deleteNote($args['id']);
			}
		} catch (\Exception $e) {
			// Squash the exception
		}

		if ($success) {
			return new EmptyResponse();
		}

		throw new BadRequestException();
	}

	public function register(ServerRequestInterface $request): array
	{
		$body = $request->getBody()->getContents();
		$parsed = json_decode($body, true);

		if (empty($parsed)) {
			throw new BadRequestException();
		}

		if (!hash_equals($parsed['password'], $parsed['repeat_password'])) {
			throw new BadRequestException('Passwords must match!');
		}

		// Check for duplicate user
		try {
			$existing = $this->db->getUserByEmail($parsed['email']);
		} catch (\Exception $e) {
			// Create the user when we throw a not found exception!
			$user = new BaseUser();
			$user->email = $parsed['email'];
			$user->password = password_hash($parsed['password'], PASSWORD_BCRYPT, ['cost' => 11]);
			$user->greeting = isset($parsed['greeting']) ? $parsed['greeting'] : 'Friend';

			$userId = $this->db->createUser($user);

			return [
				'userId' => $userId
			];
		}

		throw new BadRequestException('User already exists!');
	}

	public function changePassword(ServerRequestInterface $request): ResponseInterface
	{
		$body = $request->getBody()->getContents();
		$parsed = json_decode($body, true);

		if (empty($parsed)) {
			throw new BadRequestException();
		}

		try {
			$user = $this->db->getUserByEmail($parsed['email']);
		} catch (\Exception $e) {
			throw new BadRequestException('No such user!');
		}

		if (!password_verify($parsed['old_password'], $user->password)) {
			throw new BadRequestException('Invalid password!');
		}

		if (!hash_equals($parsed['new_password'], $parsed['repeat_password'])) {
			throw new BadRequestException('Passwords must match!');
		}

		$user->password = password_hash($parsed['new_password'], PASSWORD_BCRYPT, ['cost' => 11]);

		if ($this->db->updateUserPassword($user)) {
			return new EmptyResponse();
		}

		throw new \Exception('Server error while updating password.');
	}

	public static function authenticate(ServerRequestInterface $request): bool
	{
		$authentication = $request->getHeader('authorization')[0];
		$base64Auth = trim(substr($authentication, 5));
		$decoded = explode(':', base64_decode($base64Auth));

		$db = new Database();
		try {
			$user = $db->getUserByEmail($decoded[0]);

			if (password_verify($decoded[1], $user->password)) {
				if (session_status() !== PHP_SESSION_ACTIVE) {
					session_start();
				}

				$_SESSION['userId'] = $user->userId;
				return true;
			}
		} catch (\Exception $e) {}

		return false;
	}

	public function login(ServerRequestInterface $request): array
	{
		if (self::authenticate($request)) {
			return [
				'userId' => $_SESSION['userId'],
				'token'  => session_id()
			];
		}

		throw new \Exception('There was a problem in your login attempt');
	}
}