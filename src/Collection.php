<?php
namespace Transactd;

use Transactd\CollectionIterator;
use Transactd\Model;
use Transactd\Relation;

class Collection implements \ArrayAccess, \Countable, \IteratorAggregate
{
    use \Transactd\Serializer;
    
    private $array;
    private $rel = null;
    private $added = false;
    private $parent = null;

    const SAVE_WITHOUT_DELETE = 0;
     /**
     * At the time of the preservation of the collection acquired in HasMany,
     *  save after delete them from the database.
     */
    const SAVE_AFTER_DELETE_ITEMS = 2;
    /**
     * At the time of the preservation of the collection acquired in HasMany,
     * save after delete objects by relation conditions, from the database.
     */
    const DELETE_LOGICAL_ITEMS = 4;
    /**
     * Items are insert all, and use bulkInsert.
     * Can not be combined with other options.
     * @var int
     */
    const SAVE_ALL_BY_INSERT = 8;
    
    public $saveOprions = 6; //self::SAVE_BEFOERE_DELETE_ITEMS | self::DELETE_LOGICAL_ITEMS;
    /**
     *
     * @param null|object[] $array
     * @param null|\Transactd\Relation $rel
     * @param object $parent
     */
    public function __construct(array $array = null, Relation $rel = null, $parent = null)
    {
        $this->className = get_class() ;
        $this->array = $array === null ? array() : $array;
        $this->rel = $rel;
        $this->parent = $parent;
        if ($parent === null || $rel === null) {
            $this->saveOprions = self::SAVE_AFTER_DELETE_ITEMS;
        }
    }
    
    /**
     * Remove all items.
     */
    public function clear()
    {
        $this->array = array();   
    }
    
    private function checkObjectType($obj)
    {
        if ($this->array !== null && count($this->array) > 0) {
            if (get_class($this->array[0]) !== get_class($obj)) {
                throw new \InvalidArgumentException('Different types of objects were inserted.');
            }
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
        $this->checkObjectType($value);
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
    public function setNativeArray(array $a)
    {
        $this->array = $a;
    }
    /**
     *
     * @param int $index
     * @param object $child
     * @throws \InvalidArgumentException
     */
    public function insert($index, $child)
    {
        $this->checkObjectType($child);
        if ($this->rel !== null) {
            $this->rel->copyParentValues($child);
        }
        array_splice($this->array, $index, 0, [$child]);
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
     * @throws \InvalidArgumentException
     */
    public function add($childs)
    {
        $this->checkObjectType($childs);
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
     * Remove specified index item. Remove only from the collection, no database access.
     * @param int $index
     * @throws \OutOfRangeException
     */
    public function remove($index)
    {
        if (is_int($index) === false) {
            throw new \OutOfRangeException();
        }
        array_splice($this->array, $index, 1);
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
     * For models items only
     * @param null|int $options
     * @return bool
     * @throw IOException
     */
    public function delete($options = null)
    {
        $ret = $this->doDelete($options);
        $this->clear();
        return $ret;
    }
    
    private function doDelete($options = null)
    {
        if (count($this->array) !== 0) {
            if (!($this->array[0] instanceof \Transactd\Model)) {
                throw new \BadMethodCallException('Items are not Model.');
            }
            $options = $options === null ? $this->saveOprions : $options;
            if (($options & self::DELETE_LOGICAL_ITEMS) === self::DELETE_LOGICAL_ITEMS) {
                return $this->logicalDelete($this->parent);
            } else {
                $subRel = ($this->rel !== null) ? $this->rel->hasSubReleation() : false;
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
        }
        return true;
    }
    
    private function doSaveInternalItems($insert)
    {
        $tb = $this->array[0]->queryExecuter()->getWritableTable();
        try {
            if ($insert === true) {
                $tb->beginBulkInsert(64535);
            }
            foreach ($this->array as $obj) {
                if ($this->rel !== null && $this->added === true) {
                    $this->rel->copyParentValues($obj);
                }
                if ($obj->save(null, $insert) === false) {
                    return false;
                }
            }
            if ($insert === true) {
                $tb->commitBulkInsert();
            }
        } catch (Exception $e) {
            if ($insert === true) {
                $tb->abortBulkInsert();
            }
            throw $e;
        }
        return true;
    }
    
    private function doSaveIntermediateItem($insert)
    {
        $tb = $this->rel->getWritableTable();
        try {
            if ($insert === true) {
                $tb->beginBulkInsert(64535);
            }
            foreach ($this->array as $obj) {
                if ($this->rel->saveIntermediateItem($this->parent, $obj) === false) {
                    return false;
                }
            }
            if ($insert === true) {
                $tb->commitBulkInsert();
            }
        } catch (Exception $e) {
            if ($insert === true) {
                $tb->abortBulkInsert();
            }
            throw $e;
        }
        return true;
    }
   
    /**
     * For models items only
     * @param null|int $options
     * @return bool
     * @throw BadMethodCallException, IOException
     */
    public function save($options = null)
    {
        if (count($this->array) !== 0) {
            if (!($this->array[0] instanceof \Transactd\Model)) {
                throw new \BadMethodCallException('Items are not Model.');
            }
            $options = $options === null ? $this->saveOprions : $options;
            if (($options & self::SAVE_AFTER_DELETE_ITEMS) === self::SAVE_AFTER_DELETE_ITEMS) {
                if ($this->doDelete() === false) {
                    return false;
                }
            }
            $insert = ($options & self::SAVE_AFTER_DELETE_ITEMS) === self::SAVE_AFTER_DELETE_ITEMS ||
                     ($options == self::SAVE_ALL_BY_INSERT);
            $subRel = ($this->rel !== null) ? $this->rel->hasSubReleation() : false;
            if ($subRel === false) {
                return $this->doSaveInternalItems($insert);
            }
            return $this->doSaveIntermediateItem($insert);
        }
        return true;
    }
    /**
     *
     * @param \Transactd\Relation $rel
     */
    public function setRelation(Relation $rel)
    {
        $this->rel = $rel;
    }
    
    /**
     * 
     * @param Trnasactd\Model $parent
     */
    public function setParent($parent)
    {
        $this->parent = $parent;
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
        array_splice($this->array, $destIndex, 0, [$obj]);
    }
    
    /**
     * Serializes to JSON string.
     * Orverride trait Serializer
     *
     * @param object $obj
     * @return string
     */
    private static function serializeToJson($obj)
    {
        $s = '{';
        $s .= '"saveOprions":'.$obj->saveOprions.', "className":'.json_encode($obj->className).', "array":{';
       
        foreach ($obj->array as $key => $value) {
            $s .= '"'.$key.'":';
            if (is_object($value) === true) {
                if ($value instanceof Collection) {
                    $s .= Collection::serializeToJson($value);
                } else {
                    $s .= Model::serializeToJson($value);
                }
            } else {
                $s .= json_encode($value);
            }
            $s .= ',';
        }
        return substr($s, 0, -1).'}}';
    }
    
    /**
     * 
     * @param string|string[] $funcNames
     */
    public function loadRelations($funcNames)
    {
        Model::resolveRelations($this->array, $funcNames);
    }
}
