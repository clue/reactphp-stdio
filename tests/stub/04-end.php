<?php

use Clue\React\Stdio\Stdio;

require __DIR__ . '/../../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$stdio = new Stdio($loop);
$stdio->end();

$loop->run();
