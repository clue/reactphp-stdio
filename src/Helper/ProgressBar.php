<?php

namespace Clue\React\Stdio\Helper;

use Clue\React\Stdio\Stdio;

class ProgressBar
{
    private $current = 0;
    private $maximum = 100;

    private $stdio;
    private $length = 50;

    public function __construct(Stdio $stdio)
    {
        $this->stdio = $stdio;
    }

    public function advance($by = 1)
    {
        $this->current += $by;
        if ($this->current > $this->maximum) {
            $this->current = $this->maximum;
        } elseif ($this->current < 0) {
            $this->current = 0;
        }

        $this->redraw();
    }

    public function complete()
    {
        $this->current = $this->maximum;

        $this->redraw();
    }

    public function isComplete()
    {
        return ($this->current >= $this->maximum);
    }

    public function setMaximum($maximum)
    {
        $this->maximum = $maximum;

        $this->redraw();
    }

    public function getCurrent()
    {
        return $this->current;
    }

    public function getMaximm()
    {
        return $this->maximum;
    }

    public function getPercent()
    {
        return ($this->current * 100 / $this->maximum);
    }

    public function redraw()
    {
        $this->clear();
        $this->write();
    }

    public function clear()
    {
        $this->stdio->write("\r");
    }

    public function write()
    {
        $bar = '[';

        $length  = round(($this->length - 3) * ($this->current / $this->maximum));

        $bar .= str_repeat('=', $length);
        $bar .= '>';

        $remaining = $this->length - 3 - $length;
        if ($remaining > 0) {
            $bar .= str_repeat(' ', $remaining);
        }

        $bar .= ']';

        if (true) {
            $bar .= sprintf(' %1.1f', $this->getPercent()) . '%';
        }

        $this->stdio->overwrite($bar);
    }
}
