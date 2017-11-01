<?php

use Clue\React\Stdio\Stdio;

require __DIR__ . '/../../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

fclose(STDIN);
$stdio = new Stdio($loop);
$stdio->end($stdio->isReadable() ? 'YES' : 'NO');

$loop->run();
