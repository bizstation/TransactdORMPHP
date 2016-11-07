<?php

namespace Transactd;

use Transactd\CollectionIterator;

class Collection implements \ArrayAccess, \Countable, \IteratorAggregate
{
    private $array;
    private $removes = array();
    private $rel = null;
    private $added = false;
    private $parent = null;
    const SAVE_BEFOERE_DELETE_ITEMS = 2;
    const DELETE_LOGICAL_ITEMS = 4;
    public $saveOprions = 6; //self::SAVE_BEFOERE_DELETE_ITEMS | self::DELETE_LOGICAL_ITEMS;
    /**
     * 
     * @param object[] $array
     * @param \Transactd\Relation $rel
     * @param object $parent
     */
    public function __construct($array, $rel = null, $parent = null)
    {
        $this->array = $array;
        $this->rel = $rel;
        $this->parent = $parent;
        if ($parent === null || $rel === null) {
            $this->saveOprions = self::SAVE_BEFOERE_DELETE_ITEMS;
        }
    }
    /**
     * 
     * @return CollectionIterator
     */
    public function getIterator()
    {
        return new CollectionIterator($this->array, 0, $this->count());
    }
    /**
     * 
     * @param int $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return $offset >= 0 && $offset < $this->count();
    }
    /**
     * 
     * @param int $offset
     * @return object
     */
    public function offsetGet($offset)
    {
        return $this->array[$offset];
    }
    /**
     * 
     * @param int $offset
     * @param object $value
     */
    public function offsetSet($offset, $value)
    {
        $this->array[$offset] = $value;
    }
    /**
     * 
     * @param int $offset
     */
    public function offsetUnset($offset)
    {
        unset($this->array[$offset]);
    }
    /**
     * 
     * @return int
     */
    public function count()
    {
        return count($this->array);
    }
    /**
     * 
     * @param int $start
     * @param int $end
     * @return CollectionIterator
     */
    public function range($start = null, $end = null)
    {
        return new CollectionIterator($this->array, $start, $end);
    }
    /**
     * 
     * @return object[]
     */
    public function getNativeArray()
    {
        return $this->array;
    }
    /**
     * 
     * @param object[] $a
     */
    public function setNativeArray($a)
    {
        $this->array = $a;
    }
    /**
     * 
     * @param int $index
     * @param object $child
     */
    public function insert($index, $child)
    {
        if ($this->rel !== null) {
            $this->rel->copyParentValues($child);
        }
        array_splice($this->array, $index, 0, $child);
        $this->added = true;
    }
    /**
     * 
     * @param \ArrayAccess $ar
     * @return bool
     */
    private function isArrayAccess($ar)
    {
        return ($ar instanceof \ArrayAccess) || (is_array($ar));
    }
    /**
     * 
     * @param object $childs
     */
    public function add($childs)
    {
        if ($this->isArrayAccess($childs) === false) {
            $childs = array($childs);
        }
        $size = count($childs);
        for ($i = 0; $i < $size; ++$i) {
            $child = $childs[$i];
            if ($this->rel !== null) {
                $this->rel->copyParentValues($child);
            }
            array_push($this->array, $child);
        }
        $this->added = true;
    }
    /**
     * 
     * @param int $index
     * @throws \OutOfRangeException
     */
    public function remove($index)
    {
        if (is_int($index) === false) {
            throw new \OutOfRangeException();
        }
        $this->removes[] = $this->array[$index];
        unset($this->array[$index]);
    }
    /**
     * 
     * @return int
     * @throws \BadMethodCallException
     */
    private function logicalDelete()
    {
        if ($this->rel === null) {
            throw new \BadMethodCallException('[logicalDelete] relation is null.');
        } elseif ($this->parent === null) {
            throw new \BadMethodCallException('[logicalDelete] parent is null.');
        }
        return $this->rel->deleteAll($this->parent);
    }
    /**
     * 
     * @return bool
     */
    public function delete()
    {
        if (($this->saveOprions & 
                self::DELETE_LOGICAL_ITEMS) === self::DELETE_LOGICAL_ITEMS) {
            return $this->logicalDelete($this->parent);
        } else {
            $subRel = ($this->rel !== null) ? 
                    $this->rel->hasSubReleation() : false;
            if ($subRel === false) {
                foreach ($this->array as $obj) {
                    if ($this->rel !== null && $this->added === true) {
                        $this->rel->copyParentValues($obj);
                    }
                    if ($obj->delete() === false) {
                        return false;
                    }
                }
            } else {
                foreach ($this->array as $obj) {
                    if ($this->rel->deleteIntermediateItem($this->parent, $obj) 
                            === false) {
                        return false;
                    }
                }
            }
        }
        return true;
    }

    private function doSaveItems()
    {
        $subRel = ($this->rel !== null) ? $this->rel->hasSubReleation() : false;
        if ($subRel === false) {
            foreach ($this->array as $obj) {
                if ($this->rel !== null && $this->added === true) {
                    $this->rel->copyParentValues($obj);
                }
                if ($obj->save() === false) {
                    return false;
                }
            }
        } else {
            foreach ($this->array as $obj) {
                if ($this->rel->saveIntermediateItem($this->parent, $obj) === false) {
                    return false;
                }
            }
        }
        return true;
    }
    /**
     * 
     * @return bool
     */
    public function save()
    {
        if (($this->saveOprions & self::SAVE_BEFOERE_DELETE_ITEMS) === self::SAVE_BEFOERE_DELETE_ITEMS) {
            if ($this->delete() === false) {
                return false;
            }
        }
        if ($this->doSaveItems() === false) {
            return false;
        }
        return true;
    }
    /**
     * 
     * @param \Transactd\Relation $rel
     */
    public function setRelation($rel)
    {
        $this->rel = $rel;
    }
    /**
     * 
     * @param string $fieldName
     */
    public function renumber($fieldName)
    {
        $size = count($this->array);
        for ($i = 0; $i < $size; ++$i) {
            $obj = $this->array[$i];
            $obj->{$fieldName} = $i + 1;
        }
    }
    /**
     * 
     * @param int $index
     * @param int $destIndex
     */
    public function move($index, $destIndex)
    {
        $obj = $this->array[$index];
        array_splice($this->array, $index, 1);
        array_splice($this->array, $destIndex, 0, $obj);
    }
   
}
