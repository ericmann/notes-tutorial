<?php declare(strict_types=1);

namespace Notes\Module2;

use League\Route\Http\Exception\BadRequestException;
use League\Route\Http\Exception\ForbiddenException;
use Notes\Util\Database;
use Notes\Util\Types\BaseNote;
use Notes\Util\Types\BaseUser;
use Notes\Util\Types\User;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\EmptyResponse;

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
			$user->password = $parsed['password']; // @TODO This should be hashed!
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

		// @TODO Use better comparisons
		if (!hash_equals($user->password, $parsed['old_password'])) {
			throw new BadRequestException('Invalid password!');
		}

		if (!hash_equals($parsed['new_password'], $parsed['repeat_password'])) {
			throw new BadRequestException('Passwords must match!');
		}

		// @TODO This should be hashed!
		$user->password = $parsed['new_password'];

		if ($this->db->updateUserPassword($user)) {
			return new EmptyResponse();
		}

		throw new \Exception('Server error while updating password.');
	}
}