#!/usr/bin/php
<?php
@unlink('notes.db');

$dbh = new \PDO('sqlite:notes.db');

$tables = [
	'users'       => ['userId', 'data'],
	'email_users' => ['email', 'userId'],
	'notes'       => ['noteId', 'data'],
	'note_users'  => ['noteId', 'userId'],
	'user_notes'  => ['userId', 'notes'],
];

$dbh->beginTransaction();

foreach ($tables as $table => $fields) {
	$sql = "CREATE TABLE IF NOT EXISTS {$table} ({$fields[0]} VARCHAR (255) PRIMARY KEY, {$fields[1]} TEXT)";
	$dbh->exec($sql);
}

$dbh->commit();

print("\r\nNotes database purged and recreated with empty data!\r\n");
