<?php

namespace Clue\Stdio\React;

class Sequencer
{
    private $sequences = array();
    private $fallbacks = array();
    private $buffer = '';

    public function __construct()
    {
    }

    public function addSequence($sequence, $callback)
    {
        if (isset($this->sequences[$sequence])) {
            throw new \RuntimeException('Sequence already registered');
        }

        $this->sequences[$sequence] = $callback;

        $this->process();
    }

    public function addIgnoreSequence($sequence)
    {
        $this->addSequence($sequence, function () { });
    }

    public function addFallback($sequence, $callback)
    {
        $this->fallbacks[$sequence] = $callback;
    }

    protected function process()
    {
        // $this->escape($this->buffer);

        do {
            $sequence = '';
            $len = 0;
            $retry = false;

            while (isset($this->buffer[$len])) {
                $sequence .= $this->buffer[$len++];

                if (isset($this->sequences[$sequence])) {
                    $cb = $this->sequences[$sequence];

                    $cb($sequence);

                    $this->buffer = (string)substr($this->buffer, $len);
                    $retry = true;
                } elseif ($this->hasSequenceStartingWith($sequence)) {
                    // ignore, this may still be leading to a better sequence
                } else {
                    // not a sequence, search best fallback
                    $this->callFallbackForStart($sequence);
                    $retry = true;
                }
            }
        } while ($retry);
    }

    protected function callFallbackForStart($sequence)
    {
        $sequencePrefix = $sequence;

        while (true) {
            if (isset($this->fallbacks[$sequencePrefix])) {
                $cb = $this->fallbacks[$sequencePrefix];
                $len = strlen($sequencePrefix);
                $remainder = (string)substr($sequence, $len);

                $cb($remainder);

                $this->buffer = (string)substr($this->buffer, strlen($sequence));

                return;
            }

            if ($sequencePrefix === '') {
                break;
            } else {
                $sequencePrefix = substr($sequencePrefix, 0, -1);
            }
        }
    }

    protected function hasSequenceStartingWith($sequenceStart)
    {
        $len = strlen($sequenceStart);
        foreach ($this->sequences as $sequence => $unusedCallback) {
            if (substr($sequence, 0, $len) === $sequenceStart) {
                return true;
            }
        }

        return false;
    }

    public function push($char)
    {
        $this->buffer .= $char;

        $this->process();
    }
}
