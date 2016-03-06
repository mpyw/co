<?php

namespace mpyw\Co\Internal;
use mpyw\Co\Co;

class GeneratorContainer {

    private $g;
    private $h;
    private $e;

    public function __construct(\Generator $g)
    {
        $this->g = $g;
        ob_start();
        var_dump($this->g);
        preg_match('/\Aobject\(Generator\)(#\d++)/', ob_get_clean(), $m);
        $this->h = "Generator id $m[1]";
    }

    public function __toString()
    {
        return $this->h;
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
            $this->g->send($value);
        } catch (\RuntimeException $e) {
            $this->e = $e;
        }
    }

    public function throw_(\RuntimeException $e)
    {
        $this->valid();
        try {
            $this->g->throw($value);
        } catch (\RuntimeException $e) {
            $this->e = $e;
        }
    }

    public function thrown()
    {
        return $this->e !== null;
    }

    public function getReturnOrThrown()
    {
        if ($this->g->valid() && !$this->valid()) {
            return $value->current();
        }
        if ($this->e) {
            return $this->e;
        }
        return method_exists($this->g, 'getReturn') ? $this->g->getReturn() : null;
    }

}
