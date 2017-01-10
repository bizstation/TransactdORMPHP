<?php

namespace Transactd;

require_once(__DIR__ .'/Require.php');

use BizStation\Transactd\Transactd;
use Transactd\QueryExecuter;

/**
 * @ignore
 */
$pluralDictionary = null;

/**
 * @ignore
 */
function get_plural_dictionary()
{
    return array('child' => 'children',
                            'person' => 'people',
                            'man' => 'men',
                            'tooth' => 'teeth',
                            'foot' => 'feet',
                            'woman' => 'woman',
                            'gentleman' => 'gentlemen',
                            'mouse' => 'mice',
                            'ox' => 'oxen',
                            'datum' => 'data',
                            'index' => 'indeces',
                            'appendix' => 'appendices');
}

/**
 * @ignore
 */
function get_plural($tableName)
{
    global $pluralDictionary;
    if (function_exists('str_plural')) {
        return str_plural($tableName);
    }
    if ($pluralDictionary === null) {
        $pluralDictionary = get_plural_dictionary();
    }
    $s = mb_substr($tableName, -1);
    if ($s === 's' || $s === 'x' || $s === 'z') {
        return $tableName.'es';
    } elseif ($s === 'f') {
        return mb_substr($tableName, 0, -1).'ves';
    }

    $s2 = mb_substr($tableName, -2);
    $s2_1 = mb_substr($tableName, -2, -1);
    if ($s === 'y') {
        if ($s2_1 !== 'a' && $s2_1 !== 'e' && $s2_1 !== 'i' && $s2_1 === 'o' && $s2_1 !== 'u') {
            return mb_substr($tableName, 0, -1).'ies';
        }else {
            return $tableName.'s';
        }
    } elseif ($s2 === 'ch' || $s2 === 'sh') {
        return $tableName.'es';
    } elseif ($s2 === 'fe') {
        return mb_substr($tableName, 0, -2).'ves';
    } elseif (array_key_exists($tableName, $pluralDictionary)) {
        return $pluralDictionary[$tableName];
    }
    return $tableName.'s';
}

/**
 * @ignore
 */
function remove_namespace($className)
{
    if (mb_strrchr($className, '\\') !== false) {
        return mb_substr(mb_strrchr($className, '\\'), 1);
    }
    return $className;
}

class Model
{
    use JsonSerializable;
    
    private static $tables = array();
    private static $rerations = array();
    
    /**
     * @var bool  Just before the reading operation, it indicates whether it was returned from the cache.
     */
    public static $resolvByCache = false;
    
    /**
     * Also saves relational object(s).
     */
    const SAVE_WITH_RELATIONS = 1;
    
    /**
     * Constructer.
     * @param array $params (optional) Properties hash. ['id' => 0]
     */
    public function __construct(array $params = null)
    {
        $this->className = get_class($this);
        
        if ($params !== null) {
            $params = $this->filterCreateAttribute($params);
            foreach ($params as $key => $value) {
                $this->{$key} = $value;
            }
        }
    }

    private static function getTableName($className)
    {
        if (property_exists($className, 'table')) {
            return $className::$table;
        }
        $tableName = mb_strtolower(remove_namespace($className));
        return get_plural($tableName);
    }

    private static function getConnectionName($className)
    {
        if (property_exists($className, 'connection')) {
            return $className::$connection;
        }
        return 'default';
    }
    
    /**
     *  Clear the cache of this object instances.
     */
    public static function clearTableCache()
    {
        self::$tables = array();
    }
    
    /**
     * Prepare to table previously opened for the transaction.
     *
     */
    public static function prepareTable()
    {
        self::queryExecuterQuick();
    }
    
    private static function queryExecuterQuick($className = null)
    {
        if ($className === null) {
            $className = get_called_class();
        }
        if (array_key_exists($className, self::$tables)) {
            return self::$tables[$className];
        }
        return self::queryExecuter($className);
    }
    
    /**
     * Get the QueryExecuter of this model.
     *
     * @param string $className (optional)
     * @return \Transactd\QueryExecuter
     */
    public static function queryExecuter($className = null)
    {
        // Only PHP 5.3 or later new static() can not be used
        if ($className === null) {
            $className = get_called_class();
        }

        if (array_key_exists($className, self::$tables) === true) {
            return self::$tables[$className]->reset();
        } else {
            $tableName = self::getTableName($className);
            $connectionName = self::getConnectionName($className);
            $q = DatabaseManager::connection($connectionName)->cachedQueryExecuter($tableName, $className);
            if (property_exists($className, 'aliases')) {
                $q->setAliases($className::$aliases);
            }
            if (method_exists($className, 'creating')) {
                $q->setEvent('creating', $className);
            }
            if (method_exists($className, 'created')) {
                $q->setEvent('created', $className);
            }
            if (method_exists($className, 'updating')) {
                $q->setEvent('updating', $className);
            }
            if (method_exists($className, 'updated')) {
                $q->setEvent('updated', $className);
            }
            if (method_exists($className, 'saving')) {
                $q->setEvent('saving', $className);
            }
            if (method_exists($className, 'saved')) {
                $q->setEvent('saved', $className);
            }
            if (method_exists($className, 'deleting')) {
                $q->setEvent('deleting', $className);
            }
            if (method_exists($className, 'deleted')) {
                $q->setEvent('deleted', $className);
            }
            self::$tables[$className] = $q;
            return $q;
        }
    }
    
    /**
     * Filtering the attributes for create model by the 'fillable' or 'guarded' property.
     *
     * @param array $attributes
     * @return array
     * @throws \LogicException
     */
    public function filterCreateAttribute($attributes)
    {
        // The gurded faster than the fillable.
        if (property_exists($this, 'fillable')) {
            $fillable = static::$fillable;
            $tmp = [];
            foreach ($attributes as $key => $value) {
                if (array_search($key, $fillable) !== false) {
                    $tmp[$key] = $value;
                }
            }
            $attributes = $tmp;
            /*$attributes = array_filter($attributes, function($key) use($fillable){
                return (array_search($key, $fillable)!==false);
                }, \ARRAY_FILTER_USE_KEY );*/
        } elseif (property_exists($this, 'guarded')) {
            $guarded = static::$guarded;
            for ($i = 0; $i < count($guarded); ++$i) {
                if (array_key_exists($guarded[$i], $attributes)) {
                    unset($attributes[$guarded[$i]]);
                }
            }
        } else {
            throw new \LogicException('To use the create, you must have the fillable or guarded property of the class definition.');
        }
        return $attributes;
    }
    
    /**
     * Implements of __get magic method.
     * Get the relation object by method name. And execute the releation object. And set result to the property of the name.
     *
     * @param string $name
     * @return mixed
     * @throws \Exception
     */
    public function __get($name)
    {
        if (method_exists($this->className, $name) === true) {
            $reflectionMethod = new \ReflectionMethod($this->className, $name);
            $rel = $reflectionMethod->invoke($this);
            /* get a object and set property */
            self::$resolvByCache = false;
            $ret = $rel->get($this);
            if ($ret !== null) {
                $this->{$name} = $ret;
            }
            return $ret;
        }
        throw new \Exception('Undefined property: '.$this->className.'::'.$name);
    }

    /**
     * Get the primary key field name(s) from $fieldNames. If $fieldNames is null return 'id'.
     *
     * @param string|string[]|null $fieldNames
     * @return string|string[]
     */
    public function getPrimaryKeyFieldName($fieldNames)
    {
        if ($fieldNames === null) {
            return $this->queryExecuterQuick()->primaryKeyFieldNames();
        }
        return $fieldNames;
    }

    private function getRelationDestKeyNum($className, $keyFields, $ignoreSegmentCount = false)
    {
        return $className::queryExecuterQuick($className)->getIndexByFieldNames($keyFields, $ignoreSegmentCount, $ignoreSegmentCount);
    }

    private function getCachedRelation($key)
    {
        if (array_key_exists($key, self::$rerations) === true) {
            $rel = self::$rerations[$key];
            $rel->setParent($this);
            return $rel;
        }
        return null;
    }
    
    private function getIntermediateClass($self, $name)
    {
        if (mb_strrchr($self, '\\') !== false) {
            return mb_substr(mb_strrchr($self, '\\', false), -1).ucfirst($name);
        }
        return ucfirst($name);
    }
    
    /**
     * Get a relationship object of polymorphic many-to-many.
     *
     * @param string $className A class name of relationship.
     * @param string $name A base name of intermediate.
     * @param string $IntermediateClass (optional) A class name of intermediate.
     * @param int|string|string[] $otherKey (optional) A key of $className
     * @return \Transactd\Relation
     */
    protected function morphToMany($className, $name, $IntermediateClass = null,  $otherKey = null)
    {   //Called by Customer::tags()
        $funcName = debug_backtrace()[1]['function'];
        $self = get_class($this);
        $key = $self.'_@_'.$funcName;
        $relCache = $this->getCachedRelation($key);
        if ($relCache !== null) {
            return $relCache;
        }

        // first relation
        $IntermediateClass = ($IntermediateClass === null)
                ? $this->getIntermediateClass($self, $name) : $IntermediateClass;

        $rel = $this->doMorph($funcName, $IntermediateClass, $name, null, null, null, false, true);
        // second relation
        $rel2 = $this->doBelongsTo(null, $className, null, $otherKey, true);
        $rel->setSubRelation($rel2);
        return $rel;
    }
    
    /**
     * Get a relationship object of polymorphic opposite direction many-to-many.
     *
     * @param string $className The class name of relationship.
     * @param string $name Intermediate name. This name is used for field name of intermediate table. Ex: $name.'_id' and $name.'_type'
     * @param string $IntermediateClass (optional) Intermediate class name.
     * @param int|string|string[] $foreignKey (optional) A key of $IntermediateClass.
     * @param string[] $keyValuePropertyNames (optional) Property name(s) of this or fixed value. \
     *                 Fixed values are enclosed in []<br/>
     *                 Ex:['[1]', 'id']
     * @return \Transactd\Relation
     */
    protected function morphedByMany($className, $name, $IntermediateClass = null, $foreignKey = null, $keyValuePropertyNames = null, $otherKey = null)
    {    //Called by Tag::customers() morphedByMany('Customer', 'taggable');
        $funcName = debug_backtrace()[1]['function'];
        $self = get_class($this);
        $key = $self.'_@_'.$funcName;
        $relCache = $this->getCachedRelation($key);
        if ($relCache !== null) {
            return $relCache;
        }

        // first relation
        $IntermediateClass = ($IntermediateClass === null)
                ? $this->getIntermediateClass($self, $name) : $IntermediateClass;
        if (!is_int($foreignKey)) {   // $foreignKey = ['tag_id', 'taggable_type']
            if ($foreignKey === null) {
                $foreignKey = [remove_namespace(mb_strtolower($self).'_id'), $name.'_type'];
            }
            $index = $this->getRelationDestKeyNum($IntermediateClass, $foreignKey, true);
        } else {
            $index = $foreignKey;
        }
        $optimize = false;
        if ($keyValuePropertyNames === null) {
            $keyValuePropertyNames = $this->getPrimaryKeyFieldName(null);
            array_splice($keyValuePropertyNames, 1, 0, ['['.$className.']']);
        }
        $rel = new Relation($self, $keyValuePropertyNames, $funcName, $IntermediateClass, $index, $optimize, Relation::TYPE_MORPHEDBYMANY);

        // second relation
        $rel2 = $this->doBelongsTo(null, $className, $name.'_id', $otherKey, true);
        $rel->setSubRelation($rel2);
        self::$rerations[$key] = $rel;
        $rel->setParent($this);
        return $rel;
    }
    
    /**
     * Get a relationship object of polymorphic opposite direction one-to-one or many.
     *
     * @param string $name (optional) Intermediate name. This name is used for field name of intermediate table. Ex: $name.'_id' and $name.'_type'
     * @param string $type (optional) The name of type field.
     * @param string $id (optional) The name of id field.
     * @param array $typeToClassMap (optional) Type number map of class name.  Ex. [1 => Customer, 2 => Vendor]
     * @return \Transactd\Relation
     */
    protected function morphTo($name = null, $type = null, $id = null, array $typeToClassMap = null)
    {
        $funcName = debug_backtrace()[1]['function'];
        $self = get_class($this);
        $key = $self.'_@_'.$funcName;
        $relCache = $this->getCachedRelation($key);
        if ($relCache !== null) {
            return $relCache;
        }

        if ($name === null) {
            $name = $funcName;
        }

        // keyValuePropertyNames
        if ($type === null) {
            $type = $name.'_type';
        }
        if ($id === null) {
            $id = $name.'_id';
        }
        $keyValuePropertyNames = [$type, $id];

        //Now otherkey is unknown.
        $rel = new Relation($self, $keyValuePropertyNames, $funcName, null, -1, false, Relation::TYPE_MORPHTO);
        if ($typeToClassMap !== null) {
            $rel->setMorphClassMap($typeToClassMap);
        }
        self::$rerations[$key] = $rel;
        $rel->setParent($this);
        return $rel;
    }

    private function getMorphForeignKey($self, $className, $name, $type, $id, &$keyValuePropertyNames, $many)
    {
        // keyValuePropertyNames ["[$self]" , $this->{$localKey}]
        if ($keyValuePropertyNames === null) {
            $keyValuePropertyNames = $self::queryExecuterQuick()->primaryKeyFieldNames();
            array_splice($keyValuePropertyNames, 0, 0, "[$self]"); // insert first. Fixed value
        }

        //$foreignKey [$type,$id]
        if ($type === null) {
            $type = $name.'_type';
        }
        if ($id === null) {
            $id = $name.'_id';
        }

        try {
            $foreignKey = $this->getRelationDestKeyNum($className, [$type, $id], $many);
        } catch (\UnexpectedValueException $e) {
            $foreignKey = $this->getRelationDestKeyNum($className, [$id, $type], $many);
            if (is_array($keyValuePropertyNames) === true) {
                array_reverse($keyValuePropertyNames);
            }
        }
        return $foreignKey;
    }

    private function doMorph($funcName, $className, $name, $type, $id, $keyValuePropertyNames, $optimize, $many)
    {
        $self = get_class($this);
        if ($funcName !== null) {
            $key = $self.'_@_'.$funcName;
            $rel = $this->getCachedRelation($key);
            if ($rel !== null) {
                return $rel;
            }
        }
        $foreignKey = $this->getMorphForeignKey($self, $className, $name, $type, $id, $keyValuePropertyNames, $many);
        $rel = new Relation($self, $keyValuePropertyNames, $funcName, $className, $foreignKey, $optimize, $many === true ? Relation::TYPE_MORPHMANY : Relation::TYPE_MORPHONE);
        if ($funcName !== null) {
            self::$rerations[$key] = $rel;
        }

        $rel->setParent($this);
        return $rel;
    }

    /**
     * Get a relationship object of polymorphic one-to-many.
     *
     * @param string $className A class name of relationship.
     * @param string $name A base name of fieid of type and id.
     * @param string $type (optional) The name of type field.
     * @param string $id (optional) The name of id field.
     * @param string[] $keyValuePropertyNames (optional) Property name(s) of this or fixed value. \
     *                 Fixed values are enclosed in []<br/>
     *                 Ex:['[1]', 'id']
     * @param bool $optimize (optional) Whether to use the sort-merge or nested loop at the time of batch processing<br/>
     *  <ul>
     *   <li> true : sort-merge</li>
     *   <li> false : nested loop</li>
     *  </ul>
     * @return \Transactd\Relation
     */
    protected function morphMany($className, $name, $type = null, $id = null, $keyValuePropertyNames = null,  $optimize = false)
    {
        $funcName = debug_backtrace()[1]['function'];
        return $this->doMorph($funcName, $className, $name, $type, $id, $keyValuePropertyNames, $optimize, true);
    }
    
    /**
     * Get a relationship object of polymorphic one-to-one.
     *
     * @param string $className A class name of relationship.
     * @param string $name A base name of fieid of type and id.
     * @param string $type (optional) The name of type field.
     * @param string $id (optional) The name of id field.
     * @param string[] $keyValuePropertyNames (optional) Property name(s) of this or fixed value. \
     *                 Fixed values are enclosed in []<br/>
     *                 Ex:['[1]', 'id']
     * @param bool $optimize (optional) Whether to use the sort-merge or nested loop at the time of batch processing<br/>
     *  <ul>
     *   <li> true : sort-merge</li>
     *   <li> false : nested loop</li>
     *  </ul>
     * @return \Transactd\Relation
     */
    protected function morphOne($className, $name,  $type = null,  $id = null,  $keyValuePropertyNames = null,  $optimize = false)
    {
        $funcName = debug_backtrace()[1]['function'];
        return $this->doMorph($funcName, $className, $name, $type, $id, $keyValuePropertyNames, $optimize, false);
    }

    /**
     * Get a relationship object of one-to-one.
     *
     * @param string $className A class name of relationship.
     * @param int|string|string[] $foreignKey (optional) Key fields or index number of $className.
     * @param string[] $keyValuePropertyNames (optional) Property name(s) of this.
     * @param bool $optimize (optional) Whether to use the sort-merge or nested loop at the time of batch processing<br/>
     *  <ul>
     *   <li> true : sort-merge</li>
     *   <li> false : nested loop</li>
     *  </ul>
     * @return \Transactd\Relation
     */
    protected function hasOne($className, $foreignKey = null, $keyValuePropertyNames = null, $optimize = false)
    {
        $funcName = debug_backtrace()[1]['function'];
        $self = get_class($this);
        $key = $self.'_@_'.$funcName;
        $relCache = $this->getCachedRelation($key);
        if ($relCache !== null) {
            return $relCache;
        }

        if (!is_int($foreignKey)) {
            if ($foreignKey === null) {
                $foreignKey = mb_strtolower($self).'_id';
            }
            $foreignKey = $this->getRelationDestKeyNum($className, $foreignKey);
        }
        $keyValuePropertyNames = $this->getPrimaryKeyFieldName($keyValuePropertyNames);
        $rel = new Relation($self, $keyValuePropertyNames, $funcName, $className, $foreignKey, $optimize, Relation::TYPE_HAS_ONE);
        self::$rerations[$key] = $rel;
        $rel->setParent($this);
        return $rel;
    }

    private function doBelongsTo($funcName, $className, $keyValuePropertyNames, $otherKey, $optimize)
    {
        $self = get_class($this);
        if ($funcName !== null) {
            $key = $self.'_@_'.$funcName;
            $relCache = $this->getCachedRelation($key);
            if ($relCache !== null) {
                return $relCache;
            }
        }
        if (!is_int($otherKey)) {
            $otherKey = $this->getRelationDestKeyNum($className, $otherKey === null ? 'id' : $otherKey);
        }
        $keyValuePropertyNames = ($keyValuePropertyNames === null) ?
                                mb_strtolower($className).'_id' : $keyValuePropertyNames;
        $rel = new Relation($self, $keyValuePropertyNames, $funcName, $className, $otherKey, $optimize, Relation::TYPE_BELONGSTO);
        if ($funcName !== null) {
            self::$rerations[$key] = $rel;
        }
        $rel->setParent($this);
        return $rel;
    }
    /**
     * Get a relationship object of opposite direction one-to-one or many.
     *
     * @param string $className A class name of relationship.
     * @param string[] $keyValuePropertyNames (optional) Property name(s) of this.
     * @param int|string|string[] $otherKey (optional) Key fields or index number of $className
     * @param bool $optimize (optional) Whether to use the sort-merge or nested loop at the time of batch processing<br/>
     *  <ul>
     *   <li> true : sort-merge</li>
     *   <li> false : nested loop</li>
     *  </ul>
     * @return \Transactd\Relation
     */
    protected function belongsTo($className, $keyValuePropertyNames = null, $otherKey = 'id',  $optimize = true)
    {
        $funcName = debug_backtrace()[1]['function'];
        return $this->doBelongsTo($funcName, $className, $keyValuePropertyNames, $otherKey, $optimize);
    }

    private function doHasMany($type, $funcName, $className, $foreignKey, $keyValuePropertyNames, $optimize)
    {
        $self = get_class($this);
        if ($funcName !== null) {
            $key = $self.'_@_'.$funcName;
            $relCache = $this->getCachedRelation($key);
            if ($relCache !== null) {
                return $relCache;
            }
        }
        if (!is_int($foreignKey)) {
            if ($foreignKey === null) {
                $foreignKey = mb_strtolower(remove_namespace($self)).'_id';
            }
            $foreignKey = $this->getRelationDestKeyNum($className, $foreignKey, true);
        }

        $keyValuePropertyNames = $this->getPrimaryKeyFieldName($keyValuePropertyNames);
        $rel = new Relation($self, $keyValuePropertyNames, $funcName, $className, $foreignKey, $optimize, $type);
        if ($funcName !== null) {
            self::$rerations[$key] = $rel;
        }
        $rel->setParent($this);
        return $rel;
    }
  
    /**
     * Get a relationship object of one-to-many.
     *
     * @param string $className A class name of relationship.
     * @param int|string|string[] $foreignKey (optional) Key fields or index number of $className.
     * @param string[] $keyValuePropertyNames (optional) Property name(s) of this.
     * @param bool $optimize (optional) Whether to use the sort-merge or nested loop at the time of batch processing<br/>
     *  <ul>
     *   <li> true : sort-merge</li>
     *   <li> false : nested loop</li>
     *  </ul>
     * @return \Transactd\Relation
     */
    protected function hasMany($className, $foreignKey = null, $keyValuePropertyNames = null, $optimize = false)
    {
        $funcName = debug_backtrace()[1]['function'];
        return $this->doHasMany(Relation::TYPE_HAS_MANY, $funcName, $className, $foreignKey, $keyValuePropertyNames, $optimize);
    }

    /**
     * Get a relationship object of many-to-many.
     *
     * @param string $className A class name of relationship.
     * @param string $IntermediateClass (optional) Intermediate class name.
     * @param int|string|string[] $foreignKey (optional) (optional) Key fields or index number of $IntermediateClass.
     * @param string[] (optional) $intermediatePropertyNames property name(s) of intermediate class.
     * @return \Transactd\Relation
     */
    protected function belongsToMany($className, $IntermediateClass = null, $foreignKey = null, $intermediatePropertyNames = null)
    {
        $funcName = debug_backtrace()[1]['function'];
        $self = get_class($this);
        $key = $self.'_@_'.$funcName;
        $relCache = $this->getCachedRelation($key);
        if ($relCache !== null) {
            return $relCache;
        }

        // Namespace is same as $this.
        if ($IntermediateClass === null) {
            $IntermediateClass = $self.remove_namespace($className);
            if (class_exists($IntermediateClass) === false) {
                $IntermediateClass = $className.remove_namespace($self);
            }
        }
  
        $rel = $this->doHasMany(Relation::TYPE_BELONGSTO_MANY, $funcName, $IntermediateClass, $foreignKey, null, false);
        $rel2 = $this->doBelongsTo(null, $className, $intermediatePropertyNames, 'id', true);
        $rel->setSubRelation($rel2);
        return $rel;
    }
    
    /**
     * Get a relationship object.
     *
     * @param string $className A class name of relationship.
     * @param int $index Index number of $className table for search.
     * @param string[] $keyValuePropertyNames (optional) Property name(s) of this or fixed value. <br/>
     *                 Fixed values are enclosed in []<br/>
     *                 Ex:['[1]', 'id']
     * @param null|bool $optimize (optional) Whether to use the sort-merge or nested loop at the time of batch processing<br/>
     *  <ul>
     *   <li> true : sort-merge</li>
     *   <li> false : nested loop</li>
     *  </ul>
     * @return \Transactd\Relation
     */
    public function relation($className, $index, $keyValuePropertyNames, $optimize = null)
    {
        $funcName = debug_backtrace()[1]['function'];
        $self = get_class($this);
        $key = $self.'_@_'.$funcName;
        $relCache = $this->getCachedRelation($key);
        if ($relCache !== null) {
            return $relCache;
        }
        if (is_array($keyValuePropertyNames)) {
            $segments = count($keyValuePropertyNames);
        } else {
            $segments = 1;
        }
        $type = Relation::TYPE_HAS_ONE;
        if ($className !== null) {
            $hasMany = $className::queryExecuterQuick()->isSeekHasMany($index, $segments);
            if ($hasMany === true) {
                $type = Relation::TYPE_HAS_MANY;
                if (is_array($keyValuePropertyNames) === true) {
                    foreach ($keyValuePropertyNames as $name) {
                        if (strpos($name, '[') === 0) {
                            $type = Relation::TYPE_MORPHMANY;
                            break;
                        }
                    }
                }
            } elseif ($index === $className::queryExecuterQuick()->primarykey()) {
                if (is_array($keyValuePropertyNames) && $keyValuePropertyNames[0][0] === '[') {
                    $type = Relation::TYPE_MORPHONE;
                    $optimize = $optimize === null ? false: $optimize;
                } else {
                    $idx = $this->getRelationDestKeyNum($self, $keyValuePropertyNames, true);
                    if ($idx !== $self::queryExecuterQuick()->primarykey()) {
                        $type = Relation::TYPE_BELONGSTO;
                        $optimize = $optimize === null ? true: $optimize;
                    }
                }
            }
        } else {
            $type = Relation::TYPE_MORPHTO;
        }
        $rel = new Relation($self, $keyValuePropertyNames, $funcName, $className,
                    $index, $optimize === null ? false : $optimize, $type);
        self::$rerations[$key] = $rel;
        $rel->setParent($this);
        return $rel;
    }
    
    /**
     * Clear the query paramators.
     */
    public static function resetQuery()
    {
        self::queryExecuter()->reset();
    }
    
    /**
     * Find a Model from the table by the current key.
     *
     * @param mixed|mixed[] $id The primary key values.
     * @param bool $throwException Whether to return an error when an exception.
     * @return object|null
     * @throws ModelNotFoundException
     */
    public static function find($id, $throwException = false)
    {
        return self::queryExecuter()->find($id, $throwException);
    }
    
    /**
     * Set the index number for search.
     *
     * @param int $index
     * @return \Transactd\QueryExecuter
     */
    public static function index($index)
    {
        return self::queryExecuter()->index($index);
    }
    
    /**
     * Set key value(s) of current key.
     *
     * @param mixed $v1 First segment value.
     * @param mixed $v2 (optional) Second segment value.
     * @param mixed $v3 (optional) Third segment value.
     * @param mixed $v4 (optional) Fourth segment value.
     * @param mixed $v5 (optional) Fifth segment value.
     * @param mixed $v6 (optional) Sixth segment value.
     * @param mixed $v7 (optional) Seventh segment value.
     * @param mixed $v8 (optional) Eighth segment value.
     * @return \Transactd\QueryExecuter
     */
    public static function keyValue($v1, $v2 = null, $v3 = null, $v4 = null, $v5 = null, $v6 = null, $v7 = null, $v8 = null)
    {
        return self::queryExecuter()->keyValue($v1, $v2, $v3, $v4, $v5, $v6, $v7, $v8);
    }
    
    /**
     * Reads all records from a table by the primary key.
     *
     * @param bool $toArray (optional) false: Get by recordset.
     * @return \Transactd\Collection|BizStation\Transactd\Recordset
     */
    public static function all($toArray = true)
    {
        return self::queryExecuter()->all($toArray);
    }
    
    /**
     *
     * @param string $func
     * @return \Transactd\QueryExecuter
     */
    public static function with($func)
    {
        return self::queryExecuter()->with($func);
    }
    
    
    /**
     * Implementation of __callStatic method.
     *
     * Fiest, if $name is present in the 'Transactd\CachedQueryExecuter' then redirect to a CachedQueryExecuter.<br/>
     * Second, If supper class has a 'scope' + $name method then invoke it.
     *
     * @param string $name
     * @param mixed[] $arguments
     * @return mixed
     * @throws \BadMethodCallException
     */
    public static function __callStatic($name, $arguments)
    {
        if (method_exists('Transactd\CachedQueryExecuter', $name)) {
            $reflectionMethod = new \ReflectionMethod('Transactd\CachedQueryExecuter', $name);
            if (count($arguments) > 0) {
                return $reflectionMethod->invokeArgs(self::queryExecuter(), $arguments);
            }
            return $reflectionMethod->invoke(self::queryExecuter());
        }
        
        if (method_exists(get_called_class(), 'scope'.$name)) {
            $obj = new static(null);
            $q = self::queryExecuter();
            $reflectionMethod = new \ReflectionMethod(get_called_class(), 'scope'.$name);
            array_unshift($arguments, $q);
            $reflectionMethod->invokeArgs($obj, $arguments);
            return $q;
        }
        throw new \BadMethodCallException($name);
    }
    
    private function doSave($options, $forceInsert)
    {
        $q = self::queryExecuter();
        if (property_exists($this, 'timestamps')) {
            $q->setTimeStampMode($this->timestamps);
        }
        if ($q->save($this) === true) {
            // save hasMany childs
            if (($options & self::SAVE_WITH_RELATIONS) === self::SAVE_WITH_RELATIONS) {
                foreach ($this as $key => $obj) {
                    if (method_exists($this, $key) === true &&
                            is_object($obj) === true && method_exists($obj, 'save') === true) {
                        if ($obj->save($forceInsert) === false) {
                            return false;
                        }
                    }
                }
            }
            return true;
        }
        return false;
    }

    private function doDelete($options)
    {
        if (self::queryExecuter()->deleteObject($this) === true) {
            if (($options & self::SAVE_WITH_RELATIONS) === self::SAVE_WITH_RELATIONS) {
                foreach ($this as $key => $obj) {
                    if (method_exists($this, $key) === true &&
                            is_object($obj) === true && method_exists($obj, 'delete') === true) {
                        if ($obj->delete() === false) {
                            return false;
                        }
                    }
                }
            }
            return true;
        }
        return false;
    }
    
    /**
     * To save this object to the database.
     * @param bool $forceInsert
     * @param int $options
     * @return bool
     * @throw IOException
     */
    public function save($options = null, $forceInsert = false)
    {
        return $this->doSave($options !== null ? $options : Model::SAVE_WITH_RELATIONS, $forceInsert);
    }
    
    /**
     * To delete this object from the database.
     *
     * @param int $options
     * @return bool
     * @throw IOException
     */
    public function delete($options = null)
    {
        return $this->doDelete($options !== null ? $options : Model::SAVE_WITH_RELATIONS);
    }
    
    /**
     * Re-read from database and re-cache.
     *
     * @return bool
     */
    public function refresh()
    {
        return self::queryExecuter()->refresh($this);
    }
    
    /**
     * Update this cache.
     *
     * @param type $clear
     */
    public function updateCache($clear = false)
    {
        self::queryExecuterQuick()->updateCache($this, $clear);
    }
    
    /**
     * Get a server cursor. The keyValue uses this object properties.
     * Finally call reset() to restore index and keyValue.
     * @param int $index
     * @param int $op
     * @param int $lockBias
     * @param bool $forword Choicing ForwordIterator or ReverseIterator.
     * @return \Transactd\TableIterator
     * @throw IOException
     */
    public function serverCursor($index = null, $op = QueryExecuter::SEEK_EQUAL, $lockBias = Transactd::LOCK_BIAS_DEFAULT, $forword = true)
    {
        $qe = self::queryExecuterQuick();
        $tb = $qe->getWritableTable();
        if ($index === null) {
            $tb->setKeyNum($qe->primaryKey);
        } else {
            $tb->setKeyNum($index);
        }
        if ($op > QueryExecuter::SEEK_LAST) {
            $cPtr = table_fields($tb->cPtr, transactd::FETCH_RECORD_INTO, null,  null);
            Record_setValueByObject($cPtr, $this);
        }
        return QueryExecuter::getIterator($tb, $op, $lockBias, $forword);
    }
    
    /**
     * Relation models to batch acquisition.
     *
     * @param \Transactd\Model[] $array Model(s)
     * @param string|string[] $functionNames Relation function name(s) of Model(s)
     */
    public static function resolveRelations($array, $functionNames)
    {
        if (count($array) > 0) {
            if (is_array($functionNames) === false) {
                $functionNames = array($functionNames);
            }

            foreach ($functionNames as $functionName) {
                $array[0]->{$functionName}()->resolveRelations($array);
            }
        }
    }
}
