# clue/stdio-react [![Build Status](https://travis-ci.org/clue/php-stdio-react.svg?branch=master)](https://travis-ci.org/clue/php-stdio-react)

Async, event-driven and UTF-8 aware standard console input & output (STDIN, STDOUT) for React PHP

**Table of Contents**

* [Quickstart example](#quickstart-example)
* [Usage](#usage)
  * [Stdio](#stdio)
    * [Output](#output)
    * [Input](#input)
  * [Readline](#readline)
    * [Prompt](#prompt)
    * [Echo](#echo)
    * [Input buffer](#input-buffer)
    * [Cursor](#cursor)
  * [Advanced](#advanced)
    * [Stdout](#stdout)
    * [Stdin](#stdin)
* [Install](#install)
* [License](#license)

> Note: This project is in beta stage! Feel free to report any issues you encounter.

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

## Usage

### Stdio

The `Stdio` is the main interface to this library.
It is responsible for orchestrating the input and output streams
by registering and forwarding the corresponding events.
It also registers everything with the main [EventLoop](https://github.com/reactphp/event-loop#usage).

```php
$loop = React\EventLoop\Factory::create();
$stdio = new Stdio($loop);
```

See below for waiting for user input and writing output.
Alternatively, the `Stdio` is also a well-behaving duplex stream
(implementing React's `DuplexStreamInterface`) that emits each complete
line as a `data` event (including the trailing newline). This is considered
advanced usage.

#### Output

The `Stdio` is a well-behaving writable stream
implementing React's `WritableStreamInterface`.

The `writeln($line)` method can be used to print a line to console output.
A trailing newline will be added automatically.

```php
$stdio->writeln('hello world');
```

The `write($text)` method can be used to print the given text characters to console output.
This is useful if you need more control or want to output individual bytes or binary output:

```php
$stdio->write('hello');
$stdio->write(" world\n");
```

The `overwrite($text)` method can be used to overwrite/replace the last
incomplete line with the given text:

```php
$stdio->write('Loading…');
$stdio->overwrite('Done!');
```

Alternatively, you can also use the `Stdio` as a writable stream.
You can `pipe()` any readable stream into this stream.

#### Input

The `Stdio` is a well-behaving readable stream
implementing React's `ReadableStreamInterface`.

It will emit a `line` event for every line read from console input.
The event will contain the input buffer as-is, without the trailing newline.
You can register any number of event handlers like this:

```php
$stdio->on('line', function ($line) {
    if ($line === 'start') {
        doSomething();
    }
});
```

You can control various aspects of the console input through the [`Readline`](#readline),
so read on..

Using the `line` event is the recommended way to wait for user input.
Alternatively, using the `Readline` as a readable stream is considered advanced
usage.

Alternatively, you can also use the `Stdio` as a readable stream, which emits
each complete line as a `data` event (including the trailing newline).
This can be used to `pipe()` this stream into other writable streams.

### Readline

The [`Readline`](#readline) class is responsible for reacting to user input and presenting a prompt to the user.
It does so by reading individual bytes from the input stream and writing the current *user input line* to the output stream.

The *user input line* consists of a *prompt*, following by the current *user input buffer*.
The `Readline` allows you to control various aspects of this *user input line*.

You can access the current instance through the [`Stdio`](#stdio):

```php
$readline = $stdio->getReadline();
```

See above for waiting for user input.
Alternatively, the `Readline` is also a well-behaving readable stream
(implementing React's `ReadableStreamInterface`) that emits each complete
line as a `data` event (without the trailing newline). This is considered
advanced usage.

#### Prompt

The *prompt* will be written at the beginning of the *user input line*, right before the *user input buffer*.

The `setPrompt($prompt)` method can be used to change the input prompt.
The prompt will be printed to the *user input line* as-is, so you will likely want to end this with a space:

```php
$readline->setPrompt('Input: ');
```

The default input prompt is empty, i.e. the *user input line* contains only the actual *user input buffer*.
You can restore this behavior by passing an empty prompt:

```php
$readline->setPrompt('');
```

The `getPrompt()` method can be used to get the current input prompt.
It will return an empty string unless you've set anything else:

```php
assert($readline->getPrompt() === '');
```

#### Echo

The *echo mode* controls how the actual *user input buffer* will be presented in the *user input line*.

The `setEcho($echo)` method can be used to control the echo mode.
The default is to print the *user input buffer* as-is.

You can disable printing the *user input buffer*, e.g. for password prompts.
The user will still be able to type, but will not receive any indication of the current *user input buffer*.
Please note that this often leads to a bad user experience as users will not even see their cursor position.
Simply pass a boolean `false` like this:

```php
$readline->setEcho(false);
```

Alternatively, you can also *hide* the *user input buffer* by using a replacement character.
One replacement character will be printed for each character in the *user input buffer*.
This is useful for password prompts to give users an indicatation that their key presses are registered.
This often provides a better user experience and allows users to still control their cursor position. 
Simply pass a string replacement character likes this:

```php
$readline->setEcho('*');
```

To restore the original behavior where every character appears as-is, simply pass a boolean `true`:

```php
$readline->setEcho(true);
```

#### Input buffer

Everything the user types will be buffered in the current *user input buffer*.
Once the user hits enter, the *user input buffer* will be processed and cleared.

The `setInput($buffer)` method can be used to control the *user input buffer*.
The user will be able to delete and/or rewrite the buffer at any time.
Changing the *user input buffer* can be useful for presenting a preset input to the user
(like the last password attempt).
Simple pass an input string like this:

```php
$readline->setInput('lastpass');
```

The `getInput()` method can be used to access the current *user input buffer*.
This can be useful if you want to append some input behind the current *user input buffer*.
You can simply access the buffer like this:

```php
$buffer = $readline->getInput();
```

#### Cursor

By default, users can control their (horizontal) cursor position by using their arrow keys on the keyboard.
Also, every character pressed on the keyboard advances the cursor position.

The `setMove($toggle)` method can be used to control whether users are allowed to use their arrow keys.
To disable the left and right arrow keys, simply pass a boolean `false` like this:

```php
$readline->setMove(false);
```

To restore the default behavior where the user can use the left and right arrow keys,
simply pass a boolean `true` like this:

```php
$readline->setMove(true);
```

The `getCursorPosition()` method can be used to access the current cursor position,
measured in number of characters.
This can be useful if you want to get a substring of the current *user input buffer*.
Simply invoke it like this:

```php
$position = $readline->getCursorPosition();
```

The `getCursorCell()` method can be used to get the current cursor position,
measured in number of monospace cells.
Most *normal* characters (plain ASCII and most multi-byte UTF-8 sequences) take a single monospace cell.
However, there are a number of characters that have no visual representation
(and do not take a cell at all) or characters that do not fit within a single
cell (like some asian glyphs).
This method is mostly useful for calculating the visual cursor position on screen,
but you may also invoke it like this:

```php
$cell = $readline->getCursorCell();
```

The `moveCursorTo($position)` method can be used to set the current cursor position to the given absolute character position.
For example, to move the cursor to the beginning of the *user input buffer*, simply call:

```php
$readline->moveCursorTo(0);
```

The `moveCursorBy($offset)` method can be used to change the cursor position
by the given number of characters relative to the current position.
A positive number will move the cursor to the right - a negative number will move the cursor to the left.
For example, to move the cursor one character to the left, simply call:

```php
$readline->moveCursorBy(-1);
```

### Advanced

#### Stdout

The `Stdout` represents a `WritableStream` and is responsible for handling console output.

Interfacing with it directly is *not recommended* and considered *advanced usage*.

If you want to print some text to console output, use the [`Stdio::write()`](#output) instead:

```php
$stdio->write('hello');
```

Should you need to interface with the `Stdout`, you can access the current instance through the [`Stdio`](#stdio):

```php
$stdout = $stdio->getOutput();
```

#### Stdin

The `Stdin` represents a `ReadableStream` and is responsible for handling console input.

Interfacing with it directly is *not recommended* and considered *advanced usage*.

If you want to read a line from console input, use the [`Stdio::on()`](#input) instead:

```php
$stdio->on('line', function ($line) use ($stdio) {
    $stdio->writeln('You said "' . $line . '"');
});
```

Should you need to interface with the `Stdin`, you can access the current instance through the [`Stdio`](#stdio):

You can access the current instance through the [`Stdio`](#stdio):

```php
$stdin = $stdio->getInput();
```

## Install

The recommended way to install this library is [through Composer](https://getcomposer.org).
[New to Composer?](https://getcomposer.org/doc/00-intro.md)

```bash
$ composer require clue/stdio-react:~0.3.0
```

More details and upgrade guides can be found in the [CHANGELOG](CHANGELOG.md).

## License

MIT
