<?php

require __DIR__ . '/../vendor/autoload.php';

$stdio = new Clue\React\Stdio\Stdio();
$stdio->setPrompt('Username: ');

$first = true;
$username = null;
$password = null;

$stdio->on('data', function ($line) use ($stdio, &$first, &$username, &$password) {
    $line = rtrim($line, "\r\n");
    if ($first) {
        $stdio->setPrompt('Password: ');
        $stdio->setEcho('*');
        $username = $line;
        $first = false;
    } else {
        $password = $line;
        $stdio->end(<<<EOT
---------------------
Confirmation:
---------------------
Username: $username
Password: $password

EOT
        );
    }
});
