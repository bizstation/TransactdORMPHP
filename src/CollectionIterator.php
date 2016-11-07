<?php

namespace Transactd;

use BizStation\Transactd\RangeIterator;

class CollectionIterator extends RangeIterator
{
    private $array;
    /**
     * 
     * @param object[] $array
     * @param int $start
     * @param int $end
     */
    public function __construct($array, $start, $end)
    {
        $this->array = $array;
        parent::__construct($start, $end);
    }
    /**
     * 
     * @return object
     */
    public function current()
    {
        return $this->array[$this->curIndex];
    }
}
