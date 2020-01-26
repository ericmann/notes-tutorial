<?php declare(strict_types=1);

namespace Notes\Module1;

use League\Route\Http\Exception\BadRequestException;
use Notes\Util\Database;
use Notes\Util\Types\BaseNote;
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

		// @TODO Load this from the environment!
		$this->user = 'f6d38011-7d70-4852-914e-8406d12f08a1';
	}

	public function getNotes(ServerRequestInterface $request): array
	{
		$notes = $this->db->getNotes($this->user);

		return $notes;
	}

	public function getNote(ServerRequestInterface $request, array $args): array
	{
		try {
			$note = $this->db->getNote($args['id']);

			return (array) $note;
		} catch (\Exception $e) {
			// Squash the exception
			return [];
		}
	}

	public function createNote(ServerRequestInterface $request): array
	{
		$body = $request->getBody()->getContents();
		$parsed = json_decode($body, true);
		$contents = $parsed['note'];

		if (empty($contents)) {
			throw new BadRequestException();
		}

		$note = new BaseNote();
		$note->owner = $this->user;
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
			$success = $this->db->deleteNote($args['id']);
		} catch (\Exception $e) {
			// Squash the exception
		}

		if ($success) {
			return new EmptyResponse();
		}

		throw new BadRequestException();
	}
}