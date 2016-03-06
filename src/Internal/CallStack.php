<?php

namespace mpyw\Co\Internal;
use mpyw\Co\Co;
use mpyw\Co\GeneratorContainer;

class CallStack {


    private $generator;
    private $tree;
    private $parent;   // array<*Stack ID*|*cURL ID*, *Stack ID*>
    private $children; // array<*Stack ID*, array<*Stack ID*|*cURL ID*, true>>
    private $keylist;  // array<*Stack ID*|*cURL ID*, array<mixed>>

    public static function createFromWait($value)
    {
        $stack = new self;
        $stack->parent = 'wait';
        $stack->initialize($value);
        return $stack;
    }

    public static function createFromAsync($value)
    {
        $stack = new self;
        $stack->parent = 'async';
        $stack->initialize($value);
        return $stack;
    }

    public function createChild(GeneratorContainer $generator, array $keylist = array())
    {
        $stack = new self;
        $stack->parent = $this;
        $this->children->attach($generator);
        $this->keylist[$generator] = $keylist;
        return $stack;
    }

    public function __construct($parent)
    {
        $stack->children = new \SplObjectStorage;
        $stack->keylist = new \SplObjectStorage;
    }

    public function initialize($value, array $keylist = array())
    {
        if (Utils::isArrayLike($value)) {
            $this->setTree($value, $keylist);
            $enqueued = false;
            foreach ($value as $k => $v) {
                $tmp_keylist = $keylist;
                $tmp_keylist[] = $k;
                $enqueued = $this->initialize($v, $tmp_keylist) || $enqueued;
            }
            return $enqueued;
        }
        if (Utils::isGenerator($value)) {
            $value = new GeneratorContainer($value);
            if ($this->children->contains($value)) {
                throw new \InvalidArgumentException("The Genertor is already running: $value");
            }
            $child = $this->createChild($value, $keylist);
            while ($value->valid()) {
                $current = $value->current();
                if ($child->initialize($current)) {
                    return true;
                }
                $value->send($current);
            }
            $retval = $value->getReturnOrThrown();
            if ($value->thrown()) {
                $this->throwIfCan($retval);
            }
            return $this->initialize($value, $keylist);
        }
        
    }

    /**
     * Set or overwrite tree of return values.
     *
     * @access private
     * @param mixed $value mixed
     * @param string $parent_hash *Stack ID*
     * @param array $keylist Queue of keys for its hierarchy.
     */
    private function setTree($value, $parent_hash, array $keylist = array())
    {
        $current = &$this->tree[$parent_hash];
        while (null !== $key = array_shift($keylist)) {
            if (!is_array($current)) {
                $current = array();
            }
            $current = &$current[$key];
        }
        $current = $value;
    }

    public function __invoke($ch, $errno)
    {

    }

    public function getParent()
    {
        return $this->
    }

    public function getChildren()
    {

    }

    public function getBrothers()
    {

    }

    public function getTree()
    {
        return $this->tree;
    }

    public function isRoot()
    {

    }

    public function isWaiting()
    {

    }

    public function insert()
    {

    }

    public function update()
    {

    }

}
