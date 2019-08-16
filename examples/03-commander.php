<?php

use Clue\React\Stdio\Stdio;
use Clue\Arguments;
use Clue\Commander\Router;
use Clue\Commander\NoRouteFoundException;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$stdio = new Stdio($loop);
$stdio->setPrompt('> ');

// limit history to HISTSIZE env
$limit = getenv('HISTSIZE');
if ($limit === '' || $limit < 0) {
    // empty string or negative value means unlimited
    $stdio->limitHistory(null);
} elseif ($limit !== false) {
    // apply any other value if given
    $stdio->limitHistory($limit);
}

// register all available commands and their arguments
$router = new Router();
$router->add('exit | quit', function() use ($stdio) {
    $stdio->end();
});
$router->add('help', function () use ($stdio) {
    $stdio->write('Use TAB-completion or use "exit"' . PHP_EOL);
});
$router->add('(echo | print) <words>...', function (array $args) use ($stdio) {
    $stdio->write(implode(' ', $args['words']) . PHP_EOL);
});
$router->add('printf <format> <args>...', function (array $args) use ($stdio) {
    $stdio->write(vsprintf($args['format'],$args['args']) . PHP_EOL);
});

// autocomplete the following commands (at offset=0/1 only)
$stdio->setAutocomplete(function ($_, $offset) {
    return $offset > 1 ? array() : array('exit', 'quit', 'help', 'echo', 'print', 'printf');
});

$stdio->write('Welcome to this interactive demo' . PHP_EOL);

// react to commands the user entered
$stdio->on('data', function ($line) use ($router, $stdio) {
    $line = rtrim($line, "\r\n");

    // add all lines from input to history
    // skip empty line and duplicate of previous line
    $all = $stdio->listHistory();
    if ($line !== '' && $line !== end($all)) {
        $stdio->addHistory($line);
    }

    try {
        $args = Arguments\split($line);
    } catch (Arguments\UnclosedQuotesException $e) {
        $stdio->write('Error: Invalid command syntax (unclosed quotes)' . PHP_EOL);
        return;
    }

    // skip empty lines
    if (!$args) {
        return;
    }

    try {
        $router->handleArgs($args);
    } catch (NoRouteFoundException $e) {
        $stdio->write('Error: Invalid command usage' . PHP_EOL);
    }
});

$loop->run();
