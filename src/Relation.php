<?php
namespace Transactd;

use BizStation\Transactd\Query;

class Relation
{
    private $srcClassName;
    private $srcMethodName;
    private $srcKeyValueFieldNames;
    private $destClassName;
    private $destIndex;
    private $optimize;
    private $type = 0;
    private $destIndexFields;
    private $caching = true;
    private $parent = null;
    private $subRelation = null;
    private $morphClassMap = null;
    const TYPE_HAS_ONE = 0;
    const TYPE_HAS_MANY = 1;
    const TYPE_BELONGSTO = 2;
    const TYPE_BELONGSTO_MANY = 3;
    const TYPE_MORPHTO = 4;
    const TYPE_MORPHMANY = 5;
    const TYPE_MORPHEDBYMANY = 7;

    private function getFieldValue($obj, $propertyName)
    {
        if ($propertyName[0] !== '[') {
            return $obj->{$propertyName};
        }
        return substr($propertyName, 1, -1);
    }

    private function getFieldValues($obj)
    {
        $fieldNames = $this->srcKeyValueFieldNames;
        if (is_array($fieldNames)) {
            $values = array();
            foreach ($fieldNames as $field) {
                array_push($values, $this->getFieldValue($obj, $field));
            }
            return $values;
        }
        return $this->getFieldValue($obj, $fieldNames);
    }

    /**
     * 
     * @param string $srcClassName
     * @param string[] $srcKeyValueFieldNames Property name(s) of the owner class or fixed value. <br/>
     *                 Fixed values are enclosed in []<br/>
     *                 Ex:['[1]', 'id']
     * @param string $srcMethodName The owner class name of relationship.
     * @param string $destClassName The class name of relationship.
     * @param int $destIndex Index number of $destClassName table.
     * @param bool $optimize Whether to use the sort-merge or nested loop at the time of batch processing<br/>
     *  <ul>               
     *   <li> true : sort-merge</li>
     *   <li> false : nested loop</li>
     *  </ul>

     * @param int $type The type number of relationship.
     */
    public function __construct($srcClassName, $srcKeyValueFieldNames, $srcMethodName,
                $destClassName, $destIndex, $optimize, $type)
    {
        $this->srcClassName = $srcClassName;
        $this->srcKeyValueFieldNames = $srcKeyValueFieldNames;
        $this->srcMethodName = $srcMethodName;
        $this->destClassName = $destClassName;
        $this->destIndex = $destIndex;
        $this->optimize = $optimize;
        $this->type = $type;
        if ($destClassName !== null) {
            $this->destIndexFields = $destClassName::queryExecuter()->keyFieldNames($destIndex);
        }
    }
    
    /**
     * Set a class-map for TYPE_MORPHTO
     * 
     * @param array $valueClassMap [[fieldValue => class name], ...]
     */
    public function setMorphClassMap(array $valueClassMap)
    {
        $this->morphClassMap = $valueClassMap;
    }

    /**
     * Get the relation type.
     * 
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    private function getMany($values, $toCollection = true)
    {
        if (!is_array($values)) {
            $values = array($values);
        }
        $tmp = $this->destClassName;
        $q = $tmp::index($this->destIndex);
        $q->whereInKey($values, count($values));
        $rs = $q->get(false);
        if ($toCollection === false) {
            return $rs;
        }
        $rs = $rs->toArray();
        $ar = array();
        foreach ($rs as $obj) {
            if ($obj !== null) {
                if ($this->caching === true) {
                    $q->pushToCache($obj);
                }
                array_push($ar, $obj);
            }
        }
        return $ar;
    }

    private function makeUniqueKey($obj, $propertyNames, $segments)
    {
        if ($segments > 1) {
            $key = '';
            foreach ($propertyNames as $name) {
                $key .= (string) $this->getFieldValue($obj, $name).'$\t';
            }

            return $key;
        } elseif (is_array($propertyNames)) {
            return $this->getFieldValue($obj, $propertyNames[0]);
        }
        return $this->getFieldValue($obj, $propertyNames);
    }

    private function isSameObj($r, $l, $fields, $segments)
    {
        if ($r === null ||  $l === null) {
            return false;
        }
        if ($segments > 1) {
            for ($i = 0; $i < $segments; ++$i) {
                if ($r->{$fields[$i]} !== $l->{$fields[$i]}) {
                    return false;
                }
            }
        } elseif (is_array($fields) === true) {
            return $r->{$fields[0]} === $l->{$fields[0]};
        }
        return $r->{$fields} === $l->{$fields};
    }

    private function grouping($rs /* sorted array */, $fields, $segments)
    {
        $size = count($rs);
        $objs = array();
        $objb = null;
        $index = -1;
        for ($i = 0; $i < $size; ++$i) {
            $obj = $rs[$i];
            if ($this->isSameObj($obj, $objb, $fields, $segments) === false) {
                $objs[++$index] = array();
                $objb = $obj;
            }
            if ($obj !== null) {
                array_push($objs[$index], $obj);
            }
        }
        return $objs;
    }

    private function addSeekValue($obj, $q, $propertyName, $segments)
    {
        if ($segments > 1) {
            foreach ($propertyName as $name) {
                $q->addSeekKeyValue($this->getFieldValue($obj, $name));
            }
        } elseif (is_array($propertyName) === true) {
            $q->addSeekKeyValue($this->getFieldValue($obj, $propertyName[0]));
        } else {
            $q->addSeekKeyValue($this->getFieldValue($obj, $propertyName));
        }
    }

    private function doReadRelations($qe, $q, $index)
    {
        $tb = $qe->getReadbleTable();
        $tb->clearBuffer();
        try {
            $tb->setKeyNum($index);
            $tb->setQuery($q);
            $rs = $tb->findAll();
            $qe->indexToPrimaryKey();
            return $rs;
        } catch (\Exception $e) {
            $qe->indexToPrimaryKey();
            throw $e;
        }
    }

    /**
     * $indexes = [[0,[1,2]],[1,[3,4]]]
     * @return array 
     */
    private function optimizedIndexArray($q, $srcMdls, $propertyName, $segments)
    {
        $indexes = array();
        $ids = array();
        for ($i = 0; $i < count($srcMdls); ++$i) {
            $obj = $srcMdls[$i];
            $key = $this->makeUniqueKey($obj, $propertyName, $segments);
            $ar_index = array_search($key, $ids);
            if ($ar_index === false) {
                $n = count($ids);
                $indexes[$n] = array();
                array_push($ids, $key);
                array_push($indexes[$n], $i);
                $this->addSeekValue($obj, $q, $propertyName, $segments);
            } else {
                array_push($indexes[$ar_index], $i);
            }
        }
        return $indexes;
    }

    private function nonOptimizedIndexArray($q, $srcMdls, $propertyName, $segments)
    {
        $indexes = array();
        for ($i = 0; $i < count($srcMdls); ++$i) {
            $indexes[$i] = array();
            array_push($indexes[$i], $i);
            $this->addSeekValue($srcMdls[$i], $q, $propertyName, $segments);
        }
        return $indexes;
    }

    private function getSegments($propertyName)
    {
        if (is_array($propertyName) === true) {
            $segments = count($propertyName);
            if ($segments === 1) {
                $propertyName = $propertyName[0];
            }
            return $segments;
        }
        return  1;
    }

    private function doResolvManyToArray($srcMdls)
    {
        // one to one for primarykey
        if (count($srcMdls) > 0) {
            $propertyName = $this->srcKeyValueFieldNames;
            $q = new Query();
            $segments = $this->getSegments($propertyName);
            $q->segmentsForInValue($segments);
            $this->nonOptimizedIndexArray($q, $srcMdls, $propertyName, $segments);
            $qe = Model::queryExecuter($this->destClassName);
            $rs = $this->doReadRelations($qe, $q, $this->destIndex);
            $size = count($rs);
            $ret = array();
            for ($i = 0; $i < $size; ++$i) {
                $obj = $rs[$i];
                if ($obj !== null) {
                    $qe->pushToCache($obj);
                }
                array_push($ret, $obj);
            }
            return $ret;
        }
        return array();
    }

    /**
     * Caching relational models
     */
    private function doResolveRelations($srcMdls, $arrayResult = false)
    {
        if (count($srcMdls) > 0) {
            $propertyNames = $this->srcKeyValueFieldNames;
            $q = new Query();
            $segments = $this->getSegments($propertyNames);

            $q->segmentsForInValue($segments);
            if ($this->optimize === false) {
                $indexes = $this->nonOptimizedIndexArray($q, $srcMdls, $propertyNames, $segments);
            } else {
                $indexes = $this->optimizedIndexArray($q, $srcMdls, $propertyNames, $segments);
            }
            $qe = Model::queryExecuter($this->destClassName);
            $rs = $this->doReadRelations($qe, $q, $this->destIndex);
            $ret = array();
            if (($this->type & self::TYPE_HAS_MANY) === self::TYPE_HAS_MANY) {
                $grps = $this->grouping($rs, $this->destIndexFields, $segments);
                //set relation value
                $size = count($indexes);
                for ($i = 0; $i < $size; ++$i) {
                    $tmp = $indexes[$i];
                    foreach ($tmp as $index) {
                        if ($this->subRelation !== null) {
                            array_push($ret, $grps[$i]);
                        } else {
                            $srcMdls[$index]->{$this->srcMethodName} = $qe->arrayToCollection($grps[$i], $this);
                        }
                    }
                }
            } else {
                $size = count($rs);
                for ($i = 0; $i < $size; ++$i) {
                    $obj = $rs[$i];
                    if ($obj !== null) {
                        $qe->pushToCache($obj);
                    }
                    $tmp = $indexes[$i];
                    foreach ($tmp as $index) {
                        if ($arrayResult === true) {
                            array_push($ret, $obj);
                        } else {
                            $srcMdls[$index]->{$this->srcMethodName} = $obj;
                        }
                    }
                }
            }
            return $ret;
        }
        return array();
    }

    private function paramCount($fieldNames, $destFields)
    {
        return count($fieldNames) < count($destFields) ?
                count($fieldNames) : count($destFields);
    }

    private function copyValues($obj, $src, $fieldNames, $destFields)
    {
        if (!is_array($fieldNames)) {
            $fieldNames = array($fieldNames);
        }
        if (!is_array($destFields)) {
            $destFields = array($destFields);
        }

        $n = $this->paramCount($fieldNames, $destFields);
        for ($i = 0; $i < $n; ++$i) {
            $obj->{$destFields[$i]} = $this->getFieldValue($src, $fieldNames[$i]);
        }
        return $obj;
    }

    private function resolveSubReleation($srcMdls, $mdls)
    {
        $flatArray = array();
        foreach ($mdls as $many) {
            foreach ($many as $obj) {
                array_push($flatArray, $obj);
            }
        }
        $r = $this->subRelation->doResolveRelations($flatArray, true);
        $n = 0;
        for ($i = 0; $i < count($mdls); ++$i) {
            $tmp = array();
            for ($j = 0; $j < count($mdls[$i]); ++$j) {
                array_push($tmp, $r[$n++]);
            }
            $srcMdls[$i]->{$this->srcMethodName} = $tmp;
        }
    }

    /**
     * Get the relation results from the database.
     * 
     * @param \Transactd\Model $src The source model.
     * @return \Transactd\Model|\Transactd\Collection
     */
    public function get($src)
    {
        $values = $this->getFieldValues($src);
        if (($this->type & self::TYPE_HAS_MANY) === self::TYPE_HAS_MANY) {
            $ret = $this->getMany($values);
            if ($this->subRelation !== null) {
                $ret = $this->subRelation->doResolvManyToArray($ret);
            }
            $tmp = $this->destClassName;
            return $tmp::queryExecuter()->arrayToCollection($ret, $this, $src);
        } elseif (($this->type & self::TYPE_MORPHTO) === self::TYPE_MORPHTO) {
            $class = $values[0];
            if ($this->morphClassMap !== null) {
                $class = $this->morphClassMap[$values[0]];
            }
            return $class::find($values[1]);
        }
        $tmp = $this->destClassName;
        return $tmp::findWithIndex($this->destIndex, $values);
    }

    /**
     * Delete matched records by relationship conditions.
     * 
     * @param string  $src The source model.
     * @return int  Count of effects.
     */
    public function deleteAll($src)
    {
        $n = 0;
        $values = $this->getFieldValues($src);
        if (($this->type & self::TYPE_HAS_MANY) !== self::TYPE_HAS_MANY) {
            return 0;
        }
        $rs = $this->getMany( $values, false);
        foreach ($rs as $obj) {
            if ($obj !== null) {
                $obj->delete();
                ++$n;
            }
        }
        return $n;
    }

    /**
     * The relation of the specified collection, and then batch acquisition.
     * 
     * @param \Transactd\Collection|\Transactd\Model[] $srcMdls Collection of model
     * @throws \BadMethodCallException
     */
    public function resolveRelations($srcMdls)
    {
        if (($this->type & self::TYPE_MORPHTO) === self::TYPE_MORPHTO) {
            throw new \BadMethodCallException('[with] method is not support in this relation type.');
        }
        $ret = $this->doResolveRelations($srcMdls);
        if ($this->subRelation !== null) {
            $this->resolveSubReleation($srcMdls, $ret);
        }
    }

    /**
     * Set a parent model for relationship.
     * @param \Transactd\Model $obj
     */
    public function setParent($obj)
    {
        $this->parent = $obj;
    }

    /**
     * Create a new model by this relationship condition and attributes.<br/>
     * This operation do not save to database.
     * 
     * @param array $attributes
     * @return \Transactd\Model
     * @throws \BadMethodCallException
     */
    public function create($attributes)
    {
        if ((($this->type & self::TYPE_BELONGSTO) === self::TYPE_BELONGSTO) ||
            (($this->type & self::TYPE_MORPHTO) === self::TYPE_MORPHTO)) {
            throw new \BadMethodCallException('[create] method is not support in this relation type.');
        }
        $obj = Model::queryExecuter($this->destClassName)->create($attributes, true);
        $fieldNames = $this->srcKeyValueFieldNames;
        $destFields = $this->destIndexFields;
        $obj = $this->copyValues($obj, $this->parent, $fieldNames, $destFields);
        if (($this->type & self::TYPE_HAS_MANY) === self::TYPE_HAS_MANY) {
            array_push($this->parent->{$this->srcMethodName}, $obj);
        }
        return $obj;
    }

    private function getIntermediateItem($parent, $child)
    {
        if ($this->subRelation === null) {
            throw new \BadMethodCallException('[getIntermediateItem] method is not support in this relation type.');
        }
        $c = new $this->destClassName();
        $this->parent = $parent;
        $this->copyParentValues($c);
        $rel = $this->subRelation;
        $rel->parent = $c;
        $rel->associate($child);
        return $c;
    }
    
    /**
     * Save the intermediate model of many-to-many, to the database.
     * 
     * @param \Transactd\Model $parent
     * @param \Transactd\Model $child
     * @return bool
     */
    public function saveIntermediateItem($parent, $child)
    {
        return $this->getIntermediateItem($parent, $child)->save();
    }

    /**
     * Delete the intermediate object of many-to-many, from the database.
     * 
     * @param \Transactd\Model $parent
     * @param \Transactd\Model $child
     * @return bool
     */
    public function deleteIntermediateItem($parent, $child)
    {
        return $this->getIntermediateItem($parent, $child)->delete();
    }

    /**
     * Save the relationship result model to the database.
     * 
     * @param \Transactd\Model $model
     * @return \Transactd\Model
     */
    public function save($model)
    {
        if ($this->subRelation !== null) {
            $this->saveIntermediateItem($this->parent, $model);
        } else {
            $this->copyParentValues($model);
            $model->save();
        }
        if (($this->type & self::TYPE_HAS_MANY) === self::TYPE_HAS_MANY) {
            array_push($this->parent->{$this->srcMethodName}, $model);
        }
        return $model;
    }

    /**
     * Copy the parent model values of relationship.
     * 
     * @param \Transactd\Model $child
     * @return void
     * @throws \InvalidArgumentException
     */
    public function copyParentValues($child)
    {
        if (removeNameSpace(get_class($child)) !== removeNameSpace($this->destClassName)) {
            if ($this->subRelation !== null) {
                if (removeNameSpace(get_class($child)) !== removeNameSpace($this->subRelation->destClassName)) {
                    $msg = 'arg1('.get_class($child).')is not class of '.$this->subRelation->destClassName;
                    throw new \InvalidArgumentException($msg);
                }
                return;
            } else {
                $msg = 'arg1('.get_class($child).')is not class of '.$this->destClassName;
                throw new \InvalidArgumentException($msg);
            }
        }
        $child = $this->copyValues($child, $this->parent,
        $this->srcKeyValueFieldNames, $this->destIndexFields);
    }


    /**
     * Associate the model instance to the this parent.
     * 
     * @param \Transactd\Model $model
     * @return \Transactd\Model
     * @throws \BadMethodCallException
     */
    public function associate($model)
    {
        if (($this->type & self::TYPE_BELONGSTO) !== self::TYPE_BELONGSTO) {
            throw new \BadMethodCallException('[associate] method is not support in this relation type.');
        }
        return $this->copyValues($this->parent, $model, $this->destIndexFields,
                    $this->srcKeyValueFieldNames);
    }
    
    /**
     * Dissociate the model from the this parent.
     * 
     * @param \Transactd\Model $model
     * @return \Transactd\Model
     * @throws \BadMethodCallException
     */
    public function dissociate()
    {
        if (($this->type & self::TYPE_BELONGSTO) !== self::TYPE_BELONGSTO) {
            throw new \BadMethodCallException('[dissociate] method is not support in this relation type. ');
        }
        $src = $this->parent;
        $fieldNames = $this->srcKeyValueFieldNames;
        if (is_array($fieldNames)) {
            $count = count($fieldNames);
            for ($i = 0; $i < $count; ++$i) {
                $src->{$fieldNames[$i]} = null;
            }
        } else {
            $src->{$fieldNames} = null;
        }
        return $src;
    }

    /**
     * Add a sub relationship model for mny-to-many.
     * 
     * @param string $className A class name of relationship.
     * @param int $index Index number of $className table for search.
     * @param string[] $keyValueFieldNames Property name(s) of this or fixed value. <br/>
     *                 Fixed values are enclosed in []<br/>
     *                 Ex:['[1]', 'id']
     * @param int $type (optional) TThe type of relationship.
     * @return \Transactd\Relation
     * @throws \BadMethodCallException
     */
    public function addSubRelation($className, $index, $keyValueFieldNames, $type = self::TYPE_BELONGSTO)
    {
        if (($this->type & self::TYPE_HAS_MANY) !== self::TYPE_HAS_MANY) {
            throw new \BadMethodCallException('[addSubRelation] method is not support in this relation type.');
        }
        $rel = new self($this->destClassName, $keyValueFieldNames, 'subRelation', $className,
                        $index, true, $type);
        $this->setSubRelation($rel);
        return $this;
    }

    /**
     * Add a sub relationship object for mny-to-many.
     * 
     * @param \Transactd\Relation $rel A relationship object
     * @throws \BadMethodCallException
     */
    public function setSubRelation($rel)
    {
        if (($this->type & self::TYPE_HAS_MANY) !== self::TYPE_HAS_MANY) {
            throw new \BadMethodCallException('[addSubRelation] method is not support in this relation type.');
        }
        $this->subRelation = $rel;
        $rel->srcMethodName = $this->srcMethodName;
    }
    
    /**
     * It returns whether there is a sub-relation.
     * 
     * @return bool
     */
    public function hasSubReleation()
    {
        return $this->subRelation !== null;
    }
}
