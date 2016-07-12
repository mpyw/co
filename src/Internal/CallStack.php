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
    private $return;

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
        $this->root = [$gc];
        $this->return = [$gc];
        $this->gc = $gc;
        $this->prepareArray($this->root, [0]);
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

    private function processGeneratorContainer()
    {

    }

    private function continueOrRemoveYieldable($value)
    {
        $hash = (string)$value;
        unset($this->yieldables[(string)$value]);
        unset($this->keylist[(string)])
    }

}
