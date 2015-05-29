<?php

namespace Clue\React\Stdio;

use React\Stream\Stream;
use React\EventLoop\LoopInterface;

// TODO: only implement ReadableStream
class Stdin extends Stream
{
    private $oldMode = null;

    public function __construct(LoopInterface $loop)
    {
        // STDIN not defined ("php -a") or already closed (`fclose(STDIN)`)
        if (!defined('STDIN') || !is_resource(STDIN)) {
            parent::__construct(fopen('php://memory', 'r'), $loop);
            return $this->close();
        }

        parent::__construct(STDIN, $loop);

        // support starting program with closed STDIN ("example.php 0<&-")
        // the stream is a valid resource and is not EOF, but fstat fails
        if (fstat(STDIN) === false) {
            return $this->close();
        }

        if ($this->isTty()) {
            $this->oldMode = shell_exec('stty -g');

            // Disable icanon (so we can fread each keypress) and echo (we'll do echoing here instead)
            shell_exec('stty -icanon -echo');
        }

        // register shutdown function to restore TTY mode in case of unclean shutdown (uncaught exception)
        // this will not trigger on SIGKILL etc., but the terminal should take care of this
        register_shutdown_function(array($this, 'close'));
    }

    public function close()
    {
        $this->restore();
        parent::close();
    }

    public function __destruct()
    {
        $this->restore();
    }

    private function restore()
    {
        if ($this->oldMode !== null && $this->isTty()) {
            // Reset stty so it behaves normally again
            shell_exec(sprintf('stty %s', $this->oldMode));
            $this->oldMode = null;
        }
    }

    /**
     * @return bool
     * @codeCoverageIgnore
     */
    private function isTty()
    {
        if (PHP_VERSION_ID >= 70200) {
            // Prefer `stream_isatty()` (available as of PHP 7.2 only)
            return stream_isatty(STDIN);
        } elseif (function_exists('posix_isatty')) {
            // Otherwise use `posix_isatty` if available (requires `ext-posix`)
            return posix_isatty(STDIN);
        }

        // otherwise try to guess based on stat file mode and device major number
        // Must be special character device: ($mode & S_IFMT) === S_IFCHR
        // And device major number must be allocated to TTYs (2-5 and 128-143)
        // For what it's worth, checking for device gid 5 (tty) is less reliable.
        // @link http://man7.org/linux/man-pages/man7/inode.7.html
        // @link https://www.kernel.org/doc/html/v4.11/admin-guide/devices.html#terminal-devices
        if (is_resource(STDIN)) {
            $stat = fstat(STDIN);
            $mode = isset($stat['mode']) ? ($stat['mode'] & 0170000) : 0;
            $major = isset($stat['dev']) ? (($stat['dev'] >> 8) & 0xff) : 0;

            if ($mode === 0020000 && $major >= 2 && $major <= 143 && ($major <=5 || $major >= 128)) {
                return true;
            }
        }
        return false;
    }
}
