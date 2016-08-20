<?php

class DummyCurl
{
    private $multiHandle;

    private $identifier;
    private $cost;
    private $reservedErrno;
    private $reservedErrstr;
    private $reservedResponse;

    private $counter = 0;
    private $read = false;
    private $response = '';
    private $errno = -1;
    private $errstr = '';
    private $startedAt = -1;
    private $stoppedAt = -1;

    public function setMultiHandle(DummyCurlMulti $mh)
    {
        $this->multiHandle = $mh;
    }

    public function getMultiHandle(DummyCurlMulti $mh)
    {
        return $this->multiHandle;
    }

    public function __construct(string $identifier, int $cost, bool $error = false)
    {
        assert($cost > 0);
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
        $this->startedAt = -1;
        $this->stoppedAt = -1;
    }

    public function __toString() : string
    {
        return $this->identifier;
    }

    public function getContent() : string
    {
        return $this->response;
    }

    public function errno() : int
    {
        return $this->errno;
    }

    public function errstr() : string
    {
        return $this->errstr;
    }

    public function prepared() : bool
    {
        return $this->counter === $this->cost;
    }

    public function running() : bool
    {
        return $this->counter > 0 && $this->counter < $this->cost;
    }

    public function done() : bool
    {
        return $this->counter === 0;
    }

    public function update(int $exec_count)
    {
        assert($this->counter > 0);
        if ($this->startedAt === -1) {
            $this->startedAt = $exec_count;
        } elseif (--$this->counter === 0) {
            $this->stoppedAt = $exec_count;
            $this->response = $this->reservedResponse;
            $this->errno = $this->reservedErrno;
            $this->errstr = $this->reservedErrstr;
        }
    }

    public function read()
    {
        $this->read = true;
    }

    public function alreadyRead() : bool
    {
        return $this->read;
    }

    public function startedAt() : int
    {
        return $this->startedAt;
    }

    public function stoppedAt() : int
    {
        return $this->stoppedAt;
    }
}
