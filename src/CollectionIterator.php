<?php

namespace Transactd;

use BizStation\Transactd\RangeIterator;

class CollectionIterator extends RangeIterator
{
    private $array;
    public function __construct($array, $start, $end)
    {
        $this->array = $array;
        parent::__construct($start, $end);
    }

    public function current()
    {
        return $this->array[$this->_position];
    }
}
