<?php

use Clue\React\Stdio\Stdio;
use React\EventLoop\LoopInterface;
use Clue\React\Stdio\Helper\Spinner;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$stdio = new Stdio($loop);

$stdio->getReadline()->setPrompt('> ');

$stdio->writeln('Will print a spinner until you enter something');

$stdio->write('  Processing...');

$spinner = new Spinner($loop, $stdio);

$stdio->on('line', function ($line) use ($stdio, &$tid, $loop, $spinner) {
    $stdio->overwrite('Processing... DONE');

    $stdio->end();
    $spinner->pause();
});

$loop->run();
