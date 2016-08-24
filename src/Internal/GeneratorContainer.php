<?php

namespace mpyw\Co\Internal;
use mpyw\Co\CoInterface;

class GeneratorContainer
{
    /**
     * Generator.
     * @var \Generator
     */
    private $g;

    /**
     * Generator object hash.
     * @var string
     */
    private $h;

    /**
     * Thrown exception.
     * @var \Throwable|\Exception
     */
    private $e;

    /**
     * Parent yield key.
     * @var mixed
     */
    private $yieldKey;

    /**
     * Constructor.
     * @param \Generator $g
     */
    public function __construct(\Generator $g, $yield_key = null)
    {
        $this->g = $g;
        $this->h = spl_object_hash($g);
        $this->yieldKey = $yield_key;
        $this->valid();
    }

    /**
     * Return parent yield key.
     * @return mixed
     */
    public function getYieldKey()
    {
        return $this->yieldKey;
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
     * Return whether generator is actually working.
     * @return bool
     */
    public function valid()
    {
        try {
            $this->g->current();
            return $this->e === null && $this->g->valid() && $this->g->key() !== CoInterface::RETURN_WITH;
        } catch (\Throwable $e) {} catch (\Exception $e) {}
        $this->e = $e;
        return false;
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
            return;
        } catch (\Throwable $e) {} catch (\Exception $e) {}
        $this->e = $e;
    }

    /**
     * Throw exception into generator.
     * @param \Throwable|\Exception $e
     * @NOTE: This method returns nothing,
     *        while original generator returns something.
     */
    public function throw_($e)
    {
        $this->validateValidity();
        try {
            $this->g->throw($e);
            return;
        } catch (\Throwable $e) {} catch (\Exception $e) {}
        $this->e = $e;
    }

    /**
     * Return whether Throwable is thrown.
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
        if ($this->e === null && $this->g->valid() && !$this->valid()) {
            return $this->g->current();
        }
        if ($this->e) {
            return $this->e;
        }
        return method_exists($this->g, 'getReturn') ? $this->g->getReturn() : null;
    }

    /**
     * Validate that generator has finished running.
     * @throws \BadMethodCallException
     */
    private function validateValidity()
    {
        if (!$this->valid()) {
            throw new \BadMethodCallException('Unreachable here.');
        }
    }

    /**
     * Validate that generator is still running.
     * @throws \BadMethodCallException
     */
    private function validateInvalidity()
    {
        if ($this->valid()) {
            throw new \BadMethodCallException('Unreachable here.');
        }
    }
}
