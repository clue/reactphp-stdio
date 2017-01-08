<?php

use Clue\React\Stdio\Stdio;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$stdio = new Stdio($loop);

$stdio->getReadline()->setPrompt('Username: ');

$first = true;
$username = null;
$password = null;

$stdio->on('line', function ($line) use ($stdio, &$first, &$username, &$password) {
    if ($first) {
        $stdio->getReadline()->setPrompt('Password: ');
        $stdio->getReadline()->setEcho('*');
        $username = $line;
        $first = false;
    } else {
        $password = $line;
        $stdio->end();
    }
});

$loop->run();

echo <<<EOT
---------------------
Confirmation:
---------------------
Username: $username
Password: $password

EOT;
