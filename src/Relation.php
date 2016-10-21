<?php
namespace Transactd;

use BizStation\Transactd\query;

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

    public function setMorphClassMap(array $valueClassMap)
    {
        $this->morphClassMap = $valueClassMap;
    }

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
        if ($r === null) {
            return false;
        }
        if ($l === null) {
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
                ++$index;
                $objs[$index] = array();
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
            $q = new query();
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
     Caching relational objects
     */
    private function doResolvRelations($srcMdls, $arrayResult = false)
    {
        if (count($srcMdls) > 0) {
            $propertyName = $this->srcKeyValueFieldNames;
            $q = new query();
            $segments = $this->getSegments($propertyName);

            $q->segmentsForInValue($segments);
            if ($this->optimize === false) {
                $indexes = $this->nonOptimizedIndexArray($q, $srcMdls, $propertyName, $segments);
            } else {
                $indexes = $this->optimizedIndexArray($q, $srcMdls, $propertyName, $segments);
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
        $r = $this->subRelation->doResolvRelations($flatArray, true);
        $n = 0;
        for ($i = 0; $i < count($mdls); ++$i) {
            $tmp = array();
            for ($j = 0; $j < count($mdls[$i]); ++$j) {
                array_push($tmp, $r[$n++]);
            }
            $srcMdls[$i]->{$this->srcMethodName} = $tmp;
        }
    }

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

    public function resolvRelations($srcMdls)
    {
        if (($this->type & self::TYPE_MORPHTO) === self::TYPE_MORPHTO) {
            throw new \BadMethodCallException('[with] method is not support in this relation type.');
        }
        $ret = $this->doResolvRelations($srcMdls);
        if ($this->subRelation !== null) {
            $this->resolveSubReleation($srcMdls, $ret);
        }
    }

    public function setParent($obj)
    {
        $this->parent = $obj;
    }

    /**
     user->ext()->create(['note' => 'A new comment.'])->save();
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

    public function saveIntermediateItem($parent, $child)
    {
        return $this->getIntermediateItem($parent, $child)->save();
    }

    public function deleteIntermediateItem($parent, $child)
    {
        return $this->getIntermediateItem($parent, $child)->delete();
    }

    /**
     $grp->users()->save($user);
     */
    public function save($child)
    {
        if ($this->subRelation !== null) {
            $this->saveIntermediateItem($this->parent, $child);
        } else {
            $this->copyParentValues($child);
            $child->save();
        }
        if (($this->type & self::TYPE_HAS_MANY) === self::TYPE_HAS_MANY) {
            array_push($this->parent->{$this->srcMethodName}, $child);
        }
        return $child;
    }

    public function copyParentValues($child)
    {
        if (removeNameSpace(get_class($child)) !== removeNameSpace($this->destClassName)) {
            if ($this->subRelation !== null) {
                if (removeNameSpace(get_class($child)) !== removeNameSpace($this->subRelation->destClassName)) {
                    throw new \InvalidArgumentException('arg1('.get_class($child).')is not class of '.$this->subRelation->destClassName);
                }

                return;
            } else {
                throw new \InvalidArgumentException('arg1('.get_class($child).')is not class of '.$this->destClassName);
            }
        }
        $child = $this->copyValues($child, $this->parent,
        $this->srcKeyValueFieldNames, $this->destIndexFields);
    }

    /**
     $user->save();
     */
    public function associate($child)
    {
        if (($this->type & self::TYPE_BELONGSTO) !== self::TYPE_BELONGSTO) {
            throw new \BadMethodCallException('[associate] method is not support in this relation type.');
        }
        return $this->copyValues($this->parent, $child, $this->destIndexFields,
                    $this->srcKeyValueFieldNames);
    }

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

    public function setSubRelation($rel)
    {
        if (($this->type & self::TYPE_HAS_MANY) !== self::TYPE_HAS_MANY) {
            throw new \BadMethodCallException('[addSubRelation] method is not support in this relation type.');
        }
        $this->subRelation = $rel;
        $rel->srcMethodName = $this->srcMethodName;
    }

    public function hasSubReleation()
    {
        return $this->subRelation !== null;
    }
}
