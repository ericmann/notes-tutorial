#!/usr/bin/php
<?php

include __DIR__ . '/../../vendor/autoload.php';

use splitbrain\phpcli\CLI;
use splitbrain\phpcli\Colors;
use splitbrain\phpcli\Options;
use splitbrain\phpcli\TableFormatter;

class Module5 extends CLI
{
	/**
	 * @var GuzzleHttp\Client
	 */
	private $client;
	private $token;

	private $user;

	private $pass;

	const TABLE_WIDTH = ['9', '*'];
	const TABLE_STYLE = [Colors::C_CYAN, Colors::C_GREEN];

	protected function setup(Options $options)
	{
		$options->setHelp('This client is built to operate against the server defined in Module 5 only.');

		$options->registerCommand('create', 'Create a new note.');
		$options->registerCommand('get', 'Get a specific note.');
		$options->registerCommand('delete', 'Delete a specific note.');
		$options->registerCommand('all', 'Get all notes.');

		$options->registerCommand('register', 'Create a user account.');
		$options->registerCommand('changePassword', 'Change your user password');
		$options->registerCommand('login', 'Create a persistent authentication session');

		$options->registerArgument('note', 'Actual note to store.', true, 'create');
		$options->registerArgument('noteId', 'Identify the note to retrieve.', true, 'get');
		$options->registerArgument('noteId', 'Identify the note to delete.', true, 'delete');

		$options->registerOption('email', 'Email address', 'e', true, 'register');
		$options->registerOption('password', 'Login password', 'p', true, 'register');
		$options->registerOption('repeat-password', 'Login password (again)', 'r', true, 'register');
		$options->registerOption('greeting', 'User name or greeting', 'g', true, 'register');
		$options->registerOption('email', 'Email address', 'e', true, 'changePassword');
		$options->registerOption('old-password', 'Old login password', 'o', true, 'changePassword');
		$options->registerOption('new-password', 'New login password', 'p', true, 'changePassword');
		$options->registerOption('repeat-password', 'New login password (again)', 'r', true, 'changePassword');

		foreach (['create', 'get', 'delete', 'all', 'login'] as $command) {
			$options->registerOption('email', 'Email address', 'e', true, $command);
			$options->registerOption('password', 'Login password', 'p', true, $command);
		}

		$this->client = new GuzzleHttp\Client(['base_uri' => 'http://localhost:8888']);
		$this->token = @file_get_contents('.session');
	}

	protected function createNote($contents)
	{
		if (empty($this->token)) {
			$this->error('Please log in first!');
			return;
		}

		if (empty($contents)) {
			$this->error('Invalid/Empty note. Cannot send to server!');
			return;
		}

		$jsonBody = [
			'note' => $contents
		];

		$response = $this->client->request(
			'POST',
			'notes', [
			'body' => json_encode($jsonBody),
			'headers' => ['Authorization' => sprintf('Bearer %s', $this->token)]
		]);

		if ($response->getStatusCode() === 200) {
			$return = json_decode($response->getBody(), true);

			$this->info(sprintf('Created note ID %s', $return['noteId']));
		}
	}

	private function initTable(): TableFormatter
	{
		$tf = new TableFormatter($this->colors);
		$tf->setBorder(' | ');

		echo $tf->format(
			self::TABLE_WIDTH,
			['Field', 'Value']
		);

		echo str_pad('', $tf->getMaxWidth(), '-') . "\n";

		return $tf;
	}

	private function printNote(array $note, TableFormatter $tf)
	{
		echo $tf->format(
			self::TABLE_WIDTH,
			['Note ID', $note['noteId']],
			self::TABLE_STYLE
		);

		echo $tf->format(
			self::TABLE_WIDTH,
			['Created', $note['created']],
			self::TABLE_STYLE
		);

		echo $tf->format(
			self::TABLE_WIDTH,
			['Note', $note['note']],
			self::TABLE_STYLE
		);
	}

	protected function getNote($noteId)
	{
		if (empty($this->token)) {
			$this->error('Please log in first!');
			return;
		}

		$response = $this->client->request(
			'GET',
			sprintf('notes/%s', $noteId),
			['headers' => ['Authorization' => sprintf('Bearer %s', $this->token)]]
		);

		if ($response->getStatusCode() === 200) {
			$return = json_decode($response->getBody(), true);

			if ( ! empty($return)) {
				$tf = $this->initTable();
				$this->printNote($return, $tf);
				echo str_pad('', $tf->getMaxWidth(), '-') . "\n";
			} else {
				$this->error(sprintf('Note ID %s does not exist!', $noteId));
			}
		}
	}

	protected function deleteNote($noteId)
	{
		if (empty($this->token)) {
			$this->error('Please log in first!');
			return;
		}

		try {
			$this->client->request(
				'DELETE',
				sprintf('notes/%s', $noteId),
				['headers' => ['Authorization' => sprintf('Bearer %s', $this->token)]]
			);
			$this->info(sprintf('Note ID %s has been deleted.', $noteId));
		} catch (\GuzzleHttp\Exception\BadResponseException $e) {
			$this->error(sprintf('Unable to delete note %s. It might not exist!', $noteId));
		}
	}

	protected function getAllNotes()
	{
		if (empty($this->token)) {
			$this->error('Please log in first!');
			return;
		}

		$response = $this->client->request(
			'GET',
			'notes',
			['headers' => ['Authorization' => sprintf('Bearer %s', $this->token)]]
		);

		if ($response->getStatusCode() === 200) {
			$notes = json_decode($response->getBody(), true);

			if (empty($notes)) {
				$this->warning('System contains no notes.');
			} else {
				$tf = $this->initTable();
				foreach ($notes as $note) {
					$this->printNote($note, $tf);
					echo str_pad('', $tf->getMaxWidth(), '-') . "\n";
				}
			}
		}
	}

	protected function register(Options $options)
	{
		$email = $options->getOpt('email');
		$password = $options->getOpt('password');
		$repeat = $options->getOpt('repeat-password');
		$greeting = $options->getOpt('greeting');

		$error = false;
		if (empty($email)) {
			$this->error('Email is required.');
			$error = true;
		}
		if (empty($password)) {
			$this->error('Password is required.');
			$error = true;
		}
		if (empty($repeat)) {
			$this->error('You must repeat your password.');
			$error = true;
		}
		if ($error) return;

		$jsonBody = [
			'email'           => $email,
			'greeting'        => $greeting,
			'password'        => $password,
			'repeat_password' => $repeat,
		];

		try {
			$response = $this->client->request('POST', 'account', [
				'body' => json_encode($jsonBody)
			]);
		} catch (\GuzzleHttp\Exception\BadResponseException $e) {
			$this->error('Unable to create user account.');
			return;
		}

		if ($response->getStatusCode() === 200) {
			$return = json_decode($response->getBody(), true);

			$this->info(sprintf('Registered user with ID %s', $return['userId']));
		}
	}

	protected function changePassword(Options $options)
	{
		$email = $options->getOpt('email');
		$oldPassword = $options->getOpt('old-password');
		$newPassword = $options->getOpt('new-password');
		$repeat = $options->getOpt('repeat-password');

		$error = false;
		if (empty($email)) {
			$this->error('Email is required.');
			$error = true;
		}
		if (empty($oldPassword)) {
			$this->error('Old password is required.');
			$error = true;
		}
		if (empty($newPassword)) {
			$this->error('New password is required.');
			$error = true;
		}
		if (empty($repeat)) {
			$this->error('You must repeat your new password.');
			$error = true;
		}
		if ($error) return;

		$jsonBody = [
			'email'           => $email,
			'old_password'    => $oldPassword,
			'new_password'    => $newPassword,
			'repeat_password' => $repeat,
		];

		$response = $this->client->request('PUT', 'account', [
			'body' => json_encode($jsonBody)
		]);

		if ($response->getStatusCode() === 204) {
			$this->info('Successfully updated your user password.');
		}
	}

	protected function login(Options $options)
	{
		try {
			$response = $this->client->request('GET', 'login', ['auth' => [$this->user, $this->pass]]);

			if ($response->getStatusCode() === 200) {
				$auth = json_decode($response->getBody(), true);

				if (empty($auth)) {
					$this->error('Could not establish a session!');
				} else {
					$token = $auth['token'];

					try {
						$fp = fopen('.session', 'w');
						fwrite($fp, $token);
						fclose($fp);

						$this->info('Established a persistent session. Commence note taking!');
					} catch (\Exception $e) {
						$this->error('Could not establish a session!');
					}
				}
			}
		} catch(\GuzzleHttp\Exception\BadResponseException $e) {
			$this->error('Could not establish a session!');
		}
	}

	protected function main(Options $options)
	{
		$args = $options->getArgs();
		$this->user = $options->getOpt('email');
		$this->pass = $options->getOpt('password');

		switch ($options->getCmd()) {
			case 'create':
				$this->createNote($args[0]);
				break;
			case 'get':
				$this->getNote($args[0]);
				break;
			case 'delete':
				$this->deleteNote($args[0]);
				break;
			case 'all':
				$this->getAllNotes();
				break;
			case 'register':
				$this->register($options);
				break;
			case 'changePassword':
				$this->changePassword($options);
				break;
			case 'login':
				$this->login($options);
				break;
			default:
				$this->error('No known command was called, we show the default help instead:');
				echo $options->help();
				exit;
		}
	}
}

$cli = new Module5();
$cli->run();
