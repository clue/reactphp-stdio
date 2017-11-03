<?php

use Clue\React\Stdio\Stdio;

require __DIR__ . '/../../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

fclose(STDOUT);
$stdio = new Stdio($loop);
if ($stdio->isWritable()) {
    throw new \RuntimeException('Not writable');
}
$stdio->close();

$loop->run();
