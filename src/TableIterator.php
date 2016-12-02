<?php
namespace Transactd;

use BizStation\Transactd\Transactd;
use BizStation\Transactd\Table;
use BizStation\Transactd\Nstable;
use Transactd\IOException;

/**
 * Abstract base class of server cursor
 */
abstract class TableIterator implements \Iterator
{
    protected $pos = 0;
    protected $lockBias;
    protected $cPtr;
    private $fetchMode;
    private $fetchClass;
    private $ctorArgs;

    public function __construct(Table $table, $lockBias)
    {
        $this->cPtr = $table->cPtr;
        $this->fetchMode = $table->fetchMode;
        $this->fetchClass = $table->fetchClass;
        $this->ctorArgs = $table->ctorArgs;
        $this->lockBias = $lockBias;
    }

    public function rewind()
    {
    }
    
    /**
     * Get current Record or Model.
     * @return \BizStation\Transactd\Record|object
     */
    public function current()
    {
        return table_fields($this->cPtr, $this->fetchMode, $this->fetchClass,  $this->ctorArgs);
    }
    
    /**
     * 
     * @return int
     */
    public function key()
    {
        return $this->pos;
    }
    
    /**
     * Returns the previous operation status.
     * @return bool
     */
    public function valid()
    {
        return (nstable_stat($this->cPtr) === 0);
    }
    
    /**
     * Checks the previous operation status and throw the IOException.
     * @throws \Transactd\IOException
     */
    public function validOrFail()
    {
        $stat = nstable_stat($this->cPtr);
        if ($stat !== 0) {
            throw new IOException(nstable_statMsg($this->cPtr), $stat);
        }
    }
        
    /**
     * Move to previous record.
     */ 
    abstract public function prev();
    
    /**
     * Move to next record.
     */ 
    abstract public function next();
   
    
    /**
     * Update current record with $model.
     * @param Transactd\Model $model
     * @throws IOException
     */
    public function update($model, $updateType = Nstable::changeCurrentNcc)
    {
        table_updateByObject($this->cPtr, $model, Nstable::changeCurrentNcc);
        $this->validOrFail();
    }
    
    /**
     * Insert the $model and move the current record.
     * @param Transactd\Model $model
     * @throws IOException
     */
    public function insert($model, $ncc = true)
    {
        table_insertByObject($this->cPtr, $model, $ncc);
        $this->validOrFail();
    }
    
    /**
     * Delete the current record.
     * @throws IOException
     */
    public function delete()
    {
        nstable_del($this->cPtr, false /*in_key*/);
        $this->validOrFail();
    }
}

