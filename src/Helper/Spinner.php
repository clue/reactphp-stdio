<?php

namespace Clue\Stdio\React\Helper;

use React\EventLoop\LoopInterface;
use Clue\Stdio\React\Stdio;
use InvalidArgumentException;

/**
 *
 * @author me
 * @link http://stackoverflow.com/questions/2685435/cooler-ascii-spinners
 */
class Spinner
{
    private $loop;
    private $interval = 0.15;

    private $sequence = array();
    private $position = 0;
    private $length = 0;

    private $tid = null;

    const SEQUENCE_ASCII = '|/-\\';
    const SEQUENCE_ARROWS = '←↖↑↗→↘↓↙';

    public function __construct(LoopInterface $loop, Stdio $stdio)
    {
        $this->loop = $loop;
        $this->stdio = $stdio;

        $this->setSequence(self::SEQUENCE_ASCII);
        $this->resume();
    }

    public function setInterval($interval)
    {
        $this->interval = $interval;

        if ($this->tid !== null) {
            $this->pause();
            $this->resume();
        }
    }

    public function setSequence($sequence)
    {
        if (!is_array($sequence)) {
            $sequence = $this->split($sequence);
        }

        $this->length  = count($sequence);

        if ($this->length === 0) {
            throw new InvalidArgumentException('Empty sequence passed');
        }

        $this->sequence = $sequence;
        $this->position = 0;

        if ($this->tid !== null) {
            $this->pause();
            $this->resume();
        }
    }

    public function pause()
    {
        if ($this->tid !== null) {
            $this->loop->cancelTimer($this->tid);
            $this->tid = null;
        }
    }

    public function resume()
    {
        if ($this->tid === null) {
            $this->tid = $this->loop->addPeriodicTimer($this->interval, array($this, 'tick'));
        }
    }

    public function tick()
    {
        $this->clear();
        $this->write();

    }

    public function clear()
    {
        $this->stdio->write("\x08 \x08");
    }

    public function write()
    {
        $this->stdio->write($this->sequence[$this->position]);
        $this->position = ($this->position + 1) % $this->length;
    }

    private function split($sequence)
    {
        $ret = array();
        for ($i = 0, $l = mb_strlen($sequence, 'UTF-8'); $i < $l; ++$i) {
            $ret[] = mb_substr($sequence, $i, 1, 'UTF-8');
        }

        return $ret;
    }
}
