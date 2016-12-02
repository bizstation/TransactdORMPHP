<?php
namespace Transactd;

use Transactd\TableIterator;

/**
 * Implement the forward server cursor.
 */
class TableForwordIterator extends TableIterator
{
    /**
     * Move to previous record.
     */
    public function prev()
    {
        nstable_seekPrev($this->cPtr, $this->lockBias);
        ++$this->pos;
    }
    
    /**
     * Move to next record.
     */ 
    public function next()
    {
        nstable_seekNext($this->cPtr, $this->lockBias);
        ++$this->pos;
    }
}

