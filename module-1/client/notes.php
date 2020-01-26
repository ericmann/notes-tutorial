#!/usr/bin/php
<?php

include __DIR__ . '/../../vendor/autoload.php';

use splitbrain\phpcli\CLI;
use splitbrain\phpcli\Colors;
use splitbrain\phpcli\Options;
use splitbrain\phpcli\TableFormatter;

class Module1 extends CLI
{
	/**
	 * @var GuzzleHttp\Client
	 */
	private $client;

	const TABLE_WIDTH = ['9', '*'];
	const TABLE_STYLE = [Colors::C_CYAN, Colors::C_GREEN];

	protected function setup(Options $options)
	{
		$options->setHelp('This client is built to operate against the server defined in Module 1 only.');

		$options->registerCommand('create', 'Create a new note.');
		$options->registerCommand('get', 'Get a specific note.');
		$options->registerCommand('delete', 'Delete a specific note.');
		$options->registerCommand('all', 'Get all notes.');

		$options->registerArgument('note', 'Actual note to store.', true, 'create');
		$options->registerArgument('noteId', 'Identify the note to retrieve.', true, 'get');
		$options->registerArgument('noteId', 'Identify the note to delete.', true, 'delete');

		$this->client = new GuzzleHttp\Client(['base_uri' => 'http://localhost:8888']);
	}

	protected function createNote($contents)
	{
		if (empty($contents)) {
			$this->error('Invalid/Empty note. Cannot send to server!');
			return;
		}

		$jsonBody = [
			'note' => $contents
		];

		$response = $this->client->request('POST', 'notes', [
			'body' => json_encode($jsonBody)
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
		$response = $this->client->request('GET', sprintf('notes/%s', $noteId));

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
		try {
			$this->client->request('DELETE', sprintf('notes/%s', $noteId));
			$this->info(sprintf('Note ID %s has been deleted.', $noteId));
		} catch (\GuzzleHttp\Exception\BadResponseException $e) {
			$this->error(sprintf('Unable to delete note %s. It might not exist!', $noteId));
		}
	}

	protected function getAllNotes()
	{
		$response = $this->client->request('GET', 'notes');

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

	protected function main(Options $options)
	{

		switch ($options->getCmd()) {
			case 'create':
				$args = $options->getArgs();
				$this->createNote($args[0]);
				break;
			case 'get':
				$args = $options->getArgs();
				$this->getNote($args[0]);
				break;
			case 'delete':
				$args = $options->getArgs();
				$this->deleteNote($args[0]);
				break;
			case 'all':
				$this->getAllNotes();
				break;
			default:
				$this->error('No known command was called, we show the default help instead:');
				echo $options->help();
				exit;
		}
	}
}

$cli = new Module1();
$cli->run();
