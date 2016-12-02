<?php
namespace Transactd;

use \Transactd\TableIterator;

/**
 * Implement the reverse server cursor.
 */
class TableReverseIterator extends TableIterator
{
    /**
     * Move to next record. (Previous of reverse direction)
     */
    public function prev()
    {
        //$this->table->seekNext($this->lockBias);
        nstable_seekNext($this->cPtr, $this->lockBias);
        ++$this->pos;
    }
    
    /**
     * Move to previous record. (Next of reverse direction)
     */
    public function next()
    {
        //$this->table->seekPrev($this->lockBias);
        nstable_seekPrev($this->cPtr, $this->lockBias);
        ++$this->pos;
    }
}
