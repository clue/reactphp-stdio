<?php

use Clue\React\Stdio\Stdio;
use Clue\React\Stdio\Helper\ProgressBar;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$stdio = new Stdio($loop);
$stdio->writeln('Will print (fake) progress and then exit');

$progress = new ProgressBar($stdio);
$progress->setMaximum(mt_rand(20, 200));

$loop->addPeriodicTimer(0.1, function ($timer) use ($stdio, $progress) {
    $progress->advance();

    if ($progress->isComplete()) {
        $stdio->overwrite("Finished processing nothing!" . PHP_EOL);

        $stdio->end();
        $timer->cancel();
    }
});

$loop->run();
