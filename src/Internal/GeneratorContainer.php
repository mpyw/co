<?php

namespace mpyw\Co\Internal;
use mpyw\Co\Co;

class GeneratorContainer {

    private $g;
    private $e;

    public function __construct(\Generator $g)
    {
        $this->g = $g;
    }

    public function valid()
    {
        try {
            $this->g->current();
            return $this->g->valid() && $this->g->key() !== Co::RETURN_WITH;
        } catch (\RuntimeException $e) {
            $this->e = $e;
        }
    }

    public function key()
    {
        $this->valid();
        return $this->g->key();
    }

    public function current()
    {
        $this->valid();
        return $this->g->current();
    }

    public function send($value)
    {
        $this->valid();
        try {
            return $this->g->send($value);
        } catch (\RuntimeException $e) {
            $this->e = $e;
        }
    }

    public function getThrown()
    {
        return $this->e;
    }

    public function getReturn()
    {
        if ($this->g->valid() && !$this->valid()) {
            return $value->current();
        }
        return method_exists($this->g, 'getReturn') ? $this->g->getReturn() : null;
    }

}
