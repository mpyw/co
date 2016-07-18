<?php

class DummyCurl
{
    private $identifier;
    private $cost;
    private $reservedErrno;
    private $reservedErrstr;
    private $reservedResponse;

    private $counter = 0;
    private $read = false;
    private $response;
    private $errno;
    private $errstr;

    public function __construct(string $identifier, int $cost, bool $error = false)
    {
        assert($cost > 1);
        $this->identifier = "DummyCurl[$identifier]";
        $this->cost = $cost;
        if (!$error) {
            $this->reservedErrno = 0;
            $this->reservedErrstr = '';
            $this->reservedResponse = "Response[$identifier]";
        } else {
            $this->reservedErrno = 1;
            $this->reservedErrstr = "Error[$identifier]";
            $this->reservedResponse = '';
        }
        $this->reset();
    }

    public function reset()
    {
        $this->counter = $this->cost;
        $this->read = false;
        $this->response = '';
        $this->errno = 0;
        $this->errstr = '';
    }

    public function __toString()
    {
        return $this->identifier;
    }

    public function getContent()
    {
        return $this->response;
    }

    public function errno()
    {
        return $this->errno;
    }

    public function errstr()
    {
        return $this->errstr;
    }

    public function prepared()
    {
        return $this->counter === $this->cost;
    }

    public function running()
    {
        return $this->counter > 0 && $this->counter < $this->cost;
    }

    public function done()
    {
        return $this->counter === 0;
    }

    public function consumeCost()
    {
        assert($this->counter > 0);
        if (--$this->counter === 0) {
            $this->response = $this->reservedResponse;
            $this->errno = $this->reservedErrno;
            $this->errstr = $this->reservedErrstr;
        }
    }

    public function read()
    {
        $this->read = true;
    }

    public function alreadyRead()
    {
        return $this->read;
    }
}
