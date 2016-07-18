<?php

class DummyCurlMulti
{
    private $pool = [];
    private $execCount = 0;

    public function addHandle($ch)
    {
        if (isset($this->pool[(string)$ch])) {
            return 7;
        }
        $this->pool[(string)$ch] = $ch;
        return 0;
    }

    public function removeHandle($ch)
    {
        unset($this->pool[(string)$ch]);
        return 0;
    }

    public function exec(&$active)
    {
        $active = false;
        foreach ($this->pool as $ch) {
            if ($ch->prepared() || $ch->running()) {
                $ch->update($this->execCount);
                if ($ch->running()) {
                    $active = true;
                }
            }
        }
        ++$this->execCount;
    }

    public function infoRead()
    {
        foreach ($this->pool as $ch) {
            if ($ch->done() && !$ch->alreadyRead()) {
                $ch->read();
                return [
                    'handle' => $ch,
                    'result' => $ch->errno(),
                ];
            }
        }
        return false;
    }
}
