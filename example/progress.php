<?php

use Clue\Stdio\React\Stdio;
use Clue\Stdio\React\Helper\ProgressBar;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$stdio = new Stdio($loop);
$stdio->getInput()->close();

$stdio->writeln('Will print (fake) progress and then exit');

$progress = new ProgressBar($stdio);
$progress->setMaximum(mt_rand(20, 200));

$loop->addPeriodicTimer(0.2, function ($timer) use ($stdio, $progress) {
    $progress->advance();

    if ($progress->isComplete()) {
        $stdio->overwrite();
        $stdio->writeln("Finished processing nothing!");

        $stdio->end();
        $timer->cancel();
    }
});

$loop->run();
