<?php

namespace mpyw\Co\Internal;
use mpyw\Co\Internal\Dispatcher;
use mpyw\Co\Internal\CURLPool;
use mpyw\Co\Internal\Utils;
use mpyw\Co\CoInterface;

class CallStack
{
    /**
     * [$dispatcher description]
     * @var [type]
     */
    private $dispatcher;

    /**
     * [$dispatcher description]
     * @var [type]
     */
    private $pool;

    /**
     * [$return description]
     * @var [type]
     */
    private $yield;

    /**
     * [$original description]
     * @var [type]
     */
    private $gc;

    /**
     * [$yieldables description]
     * @var array
     */
    private $yieldables = [];

    /**
     * [$keylists description]
     * @var array
     */
    private $keylists = [];

    /**
     * [__construct description]
     * @param Dispatcher         $dispacher  [description]
     * @param CURLPool           $pool       [description]
     * @param GeneratorContainer $gc         [description]
     */
    public function __construct(Dispatcher $dispacher, CURLPool $pool, GeneratorContainer $gc)
    {
        $this->dispacher = $dispacher;
        $this->pool = $pool;
        $this->gc = $gc;
        $this->processGeneratorContainer($gc);
    }

    private function processGeneratorContainer(GeneratorContainer $gc)
    {
        if (!$gc->valid()) {
            $resolved = Utils::normalize($gc->getReturnOrThrown());
            $this->dispatcher->notify('generator_completed-' . (string)$gc, $resolved);
            return;
        }
        if (!$gc->sendCalled()) {
            $value = Utils::normalize($gc->current());
            $yieldables = Utils::getYieldables($value);
            $max = count($yieldables);
            $callback = function () use (&$max, &$callback, $gc) {
                if (--$max < 1) {
                    $this->dispatcher->remove('generator_yield_prepared-' . (string)$gc);
                    $this->dispatcher->notify('generator_completed-' . (string)$gc);
                }
            };
            $this->dispatcher->dispatch('generator_yield_prepared-' . (string)$gc, $callback);
            
            foreach ($yieldables as $yieldable) {
                if (Utils::isCurl($yieldable['value'])) {
                    $callback = function ($resolved) {
                        if ($this->dispatcher)
                    };
                    $this->dispatcher->dispatchOnce('curl_completed-' . (string)$yieldable['value'], $callback);
                }
                $this->dispatcher->dispatch('')
            }
        }
    }

    private function assignNewValue($newvalue, array $keylist = [])
    {
        $current = &$this->yield;
        while (false !== $key = array_shift($keylist)) {
            $current = &$current[$key];
        }
        $current = $newvalue;
    }

    /**
     * [prepareArray description]
     * @param  [type] $value   [description]
     * @param  array  $keylist [description]
     * @return [type]          [description]
     */
    private function prepareArray($value, array $keylist)
    {
        foreach ($value as $k => $v) {
            if (is_array($v)) {
                $this->prepareArray($value, array_merge($keylist, array($k)));
                continue;
            }
        }
    }

    private function prepareGeneratorContainer(GeneratorContainer $gc, $keylist)
    {
        $this->dispacher->dispatchOnce('curl_completed-' . $value, function () {
            $this->
        });

    }

    private function prepareCurl()
    {

    }

    private function removeCurl()
    {

    }

    private function removeGeneratorContainer()
    {

    }

    private function continueOrRemoveYieldable($value)
    {
        $hash = (string)$value;
        unset($this->yieldables[(string)$value]);
        unset($this->keylist[(string)])
    }

}
