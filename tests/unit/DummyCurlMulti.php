<?php

class DummyCurlMulti
{
    private $pool = [];
    private $execCount = 0;

    public function addHandle($ch) : int
    {
        if (isset($this->pool[(string)$ch])) {
            return 7;
        }
        $this->pool[(string)$ch] = $ch;
        return 0;
    }

    public function removeHandle($ch) : int
    {
        unset($this->pool[(string)$ch]);
        return 0;
    }

    public function exec(&$active) : int
    {
        $active = 0;
        foreach ($this->pool as $ch) {
            if ($ch->prepared() || $ch->running()) {
                $ch->update($this->execCount);
                if ($ch->running()) {
                    $active = 1;
                }
            }
        }
        ++$this->execCount;
        return 0;
    }

    public function infoRead(&$msgs_in_queue)
    {
        $i = 0;
        foreach ($this->pool as $ch) {
            ++$i;
            if ($ch->done() && !$ch->alreadyRead()) {
                $ch->read();
                $msgs_in_queue = count($this->pool) - $i;
                return [
                    'handle' => $ch,
                    'result' => $ch->errno(),
                ];
            }
        }
        return false;
    }
}
