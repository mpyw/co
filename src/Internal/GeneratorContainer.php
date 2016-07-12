<?php

namespace mpyw\Co\Internal;
use mpyw\Co\Co;

/**
 * Generator Container class.
 */
class GeneratorContainer
{
    /**
     * Generator.
     * @var Generator
     */
    private $g;

    /**
     * Generator object hash.
     * @var string
     */
    private $h;

    /**
     * Thrown exception.
     * @var Throwable|Exception
     */
    private $e;

    /**
     * Constructor.
     * @param Generator $g
     */
    public function __construct(\Generator $g)
    {
        $this->g = $g;
        $this->h = spl_object_hash($g);
    }

    /**
     * Return generator hash.
     * @return string
     */
    public function __toString()
    {
        return $this->h;
    }

    /**
     * Return whether generator is working.
     * @return bool
     */
    public function valid()
    {
        try {
            $this->g->current();
            return $this->g->valid() && $this->g->key() !== Co::RETURN_WITH;
        } catch (\RuntimeException $e) {
            $this->e = $e;
        }
    }

    /**
     * Return current key.
     * @return mixed
     */
    public function key()
    {
        $this->validateValidity();
        return $this->g->key();
    }

    /**
     * Return current value.
     * @return mixed
     */
    public function current()
    {
        $this->validateValidity();
        return $this->g->current();
    }

    /**
     * Send value into generator.
     * @param mixed $value
     * @NOTE: This method returns nothing,
     *        while original generator returns something.
     */
    public function send($value)
    {
        $this->validateValidity();
        try {
            $this->g->send($value);
        } catch (\RuntimeException $e) {
            $this->e = $e;
        }
    }

    /**
     * Throw exception into generator.
     * @param RuntimeException $e
     * @NOTE: This method returns nothing,
     *        while original generator returns something.
     */
    public function throw_(\RuntimeException $e)
    {
        $this->validateValidity();
        try {
            $this->g->throw($value);
        } catch (\RuntimeException $e) {
            $this->e = $e;
        }
    }

    /**
     * Return whether exception is thrown.
     * @return bool
     */
    public function thrown()
    {
        return $this->e !== null;
    }

    /**
     * Return value that generator has returned or thrown.
     * @return mixed
     */
    public function getReturnOrThrown()
    {
        $this->validateInvalidity();
        if ($this->g->valid() && !$this->valid()) {
            return $value->current();
        }
        if ($this->e) {
            return $this->e;
        }
        return method_exists($this->g, 'getReturn') ? $this->g->getReturn() : null;
    }

    /**
     * Validate that generator has finished running.
     * @throws LogicException
     */
    private function validateValidity()
    {
        if (!$this->valid()) {
            throw new \LogicException('Unreachable here.');
        }
    }

    /**
     * Validate that generator is still running.
     * @throws LogicException
     */
    private function validateInvalidity()
    {
        if ($this->valid()) {
            throw new \LogicException('Unreachable here.');
        }
    }
}
