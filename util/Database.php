<?php declare(strict_types=1);

namespace Notes\Util;

use Notes\Util\Types\BaseNote;
use Notes\Util\Types\BaseUser;
use Notes\Util\Types\Note;
use Notes\Util\Types\User;
use Ramsey\Uuid\Uuid;

class Database
{
	private $handle;

	public function __construct()
	{
		$this->handle = new \PDO('sqlite:../../util/notes.db');
	}

	public function getUserByEmail(string $email): User
	{
		$statement = $this->handle->prepare('SELECT userId FROM email_users WHERE email = :email');
		$statement->execute([':email' => $email]);

		$users = $statement->fetchAll();

		if (empty($users)) {
			throw new \Exception(sprintf('No user with email %s found!', $email));
		}

		return $this->getUserById($users[0]['userId']);
	}

	public function getUserById(string $userId): User
	{
		$statement = $this->handle->prepare('SELECT data FROM users WHERE userId = :userId');
		$statement->execute([':userId' => $userId]);

		$users = $statement->fetchAll();

		if (empty($users)) {
			throw new \Exception(sprintf('No user with ID %s found!', $userId));
		}

		$user = new User;
		$user->userId = $userId;
		$userData = json_decode($users[0]['data'], true);
		$user->email = $userData['email'];
		$user->password = $userData['password'];
		$user->greeting = $userData['greeting'];

		return $user;
	}

	public function createUser(BaseUser $userData): string
	{
		$userId = Uuid::uuid4()->toString();

		$this->handle->beginTransaction();

		$statement = $this->handle->prepare('INSERT INTO users (userId, data) VALUES (:userId, :jsonData)');
		$statement->execute([':userId' => $userId, ':jsonData' => json_encode($userData)]);

		$statement = $this->handle->prepare('INSERT INTO email_users (email, userId) VALUES (:email, :userId)');
		$statement->execute([':email' => $userData->email, ':userId' => $userId]);

		$this->handle->commit();

		return $userId;
	}

	public function updateUserPassword(User $user): bool
	{
		$userData =  new BaseUser();
		$userData->greeting = $user->greeting;
		$userData->email = $user->email;
		$userData->password = $user->password;

		$statement = $this->handle->prepare('UPDATE users SET data = :data WHERE userId = :userId');
		$statement->execute([':data' => json_encode($userData), 'userId' => $user->userId]);

		return $statement->rowCount() > 0;
	}

	public function createNote(BaseNote $noteData): string
	{
		$noteId = Uuid::uuid4()->toString();

		$this->handle->beginTransaction();

		$parsedData = [
			'created' => (string) time(),
			'note'    => $noteData->note,
			'owner'   => $noteData->owner,
		];

		// Get existing notes
		$notes = [];
		$statement = $this->handle->prepare('SELECT notes FROM user_notes WHERE userId = :userId');
		$statement->execute([':userId' => $noteData->owner]);

		$sqlNotes = $statement->fetchAll();
		if (!empty($sqlNotes)) {
			$sqlData = $sqlNotes[0]['notes'];
			$notes = json_decode($sqlData, true);
		}
		$notes[] = $noteId;

		$statement = $this->handle->prepare('INSERT INTO notes (noteId, data) VALUES (:noteId, :jsonData)');
		$statement->execute([':noteId' => $noteId, ':jsonData' => json_encode($parsedData)]);

		$statement = $this->handle->prepare('INSERT INTO note_users (noteId, userId) VALUES (:noteId, :userId)');
		$statement->execute([':noteId' => $noteId, ':userId' => $noteData->owner]);

		$statement = $this->handle->prepare('INSERT INTO user_notes (userId, notes) VALUES (:userId, :notes) ON CONFLICT(userId) DO UPDATE SET notes=:notes');
		$statement->execute([':userId' => $noteData->owner, ':notes' => json_encode($notes)]);

		$this->handle->commit();

		return $noteId;
	}

	public function getNote(string $noteId): Note
	{
		$statement = $this->handle->prepare('SELECT data FROM notes WHERE noteId = :noteId');
		$statement->execute([':noteId' => $noteId]);

		$notes = $statement->fetchAll();

		if (empty($notes)) {
			throw new \Exception(sprintf('No note with ID %s foune!', $noteId));
		}

		$note = new Note;
		$note->noteId = $noteId;
		$noteData = json_decode($notes[0]['data'], true);
		$note->owner = $noteData['owner'];
		$note->created = $noteData['created'];
		$note->note = $noteData['note'];

		return $note;
	}

	public function getNotes($userId): array
	{
		$statement = $this->handle->prepare('SELECT notes FROM user_notes WHERE userId = :userId');
		$statement->execute([':userId' => $userId]);

		$data = $statement->fetchAll();

		$notes = [];

		if (!empty($data)) {
			$noteIds = json_decode($data[0]['notes'], true);
			foreach ($noteIds as $noteId) {
				try{
					$notes[] = $this->getNote($noteId);
				} catch (\Exception $e) {
					// Squash exception and skip this ID.
				}
			}
		}

		return $notes;
	}

	public function deleteNote(string $noteId): bool
	{
		// Get note owner
		$ownerId = '';
		$notes = [];
		$statement = $this->handle->prepare('SELECT userId FROM note_users WHERE noteId = :noteId');
		$statement->execute([':noteId' => $noteId]);

		$users = $statement->fetchAll();

		if (!empty($users)) {
			$ownerId = $users[0]['userId'];

			// Get existing notes
			$statement = $this->handle->prepare('SELECT notes FROM user_notes WHERE userId = :userId');
			$statement->execute([':userId' => $ownerId]);

			$sqlNotes = $statement->fetchAll();
			$storedNotes = [];
			if (!empty($sqlNotes)) {
				$sqlData = $sqlNotes[0]['notes'];
				$storedNotes = json_decode($sqlData, true);
			}
			$notes = array_diff($storedNotes, [$noteId]);
		}

		$this->handle->beginTransaction();

		$statement = $this->handle->prepare('DELETE FROM notes WHERE noteId = :noteId');
		$statement->bindParam(':noteId', $noteId);
		$statement->execute();
		$deleted = $statement->rowCount();

		$statement = $this->handle->prepare('DELETE FROM note_users WHERE noteId = :noteId');
		$statement->bindParam(':noteId', $noteId);
		$statement->execute();

		if (! empty($ownerId)) {
			$statement = $this->handle->prepare('INSERT INTO user_notes (userId, notes) VALUES (:userId, :notes) ON CONFLICT(userId) DO UPDATE SET notes=:notes');
			$statement->execute([':userId' => $ownerId, ':notes' => json_encode($notes)]);
		}

		$this->handle->commit();

		// If we didn't delete anything, flag an error
		return $deleted !== 0;
	}
}