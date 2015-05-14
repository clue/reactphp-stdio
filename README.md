# clue/stdio-react [![Build Status](https://travis-ci.org/clue/php-stdio-react.svg?branch=master)](https://travis-ci.org/clue/php-stdio-react)

Async standard console input & output (STDIN, STDOUT) for React PHP

> Note: This project is in early beta stage! Feel free to report any issues you encounter.

## Quickstart example

Once [installed](#install), you can use the following code to present a prompt in a CLI program:

```php
$loop = React\EventLoop\Factory::create();
$stdio = new Stdio($loop);

$stdio->getReadline()->setPrompt('Input > ');

$stdio->on('line', function ($line) use ($stdio) {
    var_dump($line);
    
    if ($line === 'quit') {
        $stdio->end();
    }
});

$loop->run();
```

See also the [examples](examples).

## Install

The recommended way to install this library is [through composer](https://getcomposer.org).
[New to composer?](https://getcomposer.org/doc/00-intro.md)

```JSON
{
    "require": {
        "clue/stdio-react": "~0.1.0"
    }
}
```

## License

MIT
