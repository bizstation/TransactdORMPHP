<?php

namespace Transactd;

require_once(__DIR__ .'/Require.php');

/**
 * @ignore
 */
$pluralDictionary = null;

/**
 * @ignore
 */
function getPluralDictionary()
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
function getPlural($tableName)
{
    global $pluralDictionary;
    if (function_exists('str_plural')) {
        return str_plural($tableName);
    }
    if ($pluralDictionary === null) {
        $pluralDictionary = getPluralDictionary();
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
function removeNamespace($className)
{
    if (mb_strrchr($className, '\\') !== false) {
        return mb_substr(mb_strrchr($className, '\\'), 1);
    }
    return $className;
}

class Model
{
    private static $tables = array();
    private static $rerations = array();
    /**
     *
     * @var array|null To public for test. Do not chenge this variable.
     */
    public static $saveHistory = null;

    /**
     *
     * @var bool  Just before the reading operation, it indicates whether it was returned from the cache.
     */
    public static $resolvByCache = false;
    
    /**
     *
     * @var bool  Just prior to the save operation, it indicates whether it found the re-entry.
     */
    public static $detectRecursiveSave = false;
    
    /**
     *
     * @var type A class name for serizlization.
     */
    public $className;
    
    /**
     *
     * @var int save option 
     * <ul> 
     * <li>SAVE_RELATIONS : Also saves relational object(s).</li>
     * <li>SAVE_BEFOERE_DELETE_ITEMS : At the time of the preservation of the 
     *    collection acquired in HasMany, save it 
     *    previously deleted them from the database.</li>
     * <li>DELETE_LOGICAL_ITEMS : At the time of the preservation of the 
     *    collection acquired in HasMany, save it 
     *    previously re-read objects and deleted from the database.</li>
     * </ul>
     */
    public $saveOprions = self::SAVE_RELATIONS;

    /**
     * Also saves relational object(s).
     */
    const SAVE_RELATIONS = 1;
    /**
     * At the time of the preservation of the 
     * collection acquired in HasMany, save it 
     * previously deleted them from the database.
     */
    const SAVE_BEFOERE_DELETE_ITEMS = 2;
    /**
     * At the time of the preservation of the 
     * collection acquired in HasMany, save it 
     * previously re-read objects and deleted from the database.
     */
    const DELETE_LOGICAL_ITEMS = 4;
    
    
    /**
     * Constructer. 
     * @param array $params (optional) Properties hash. ['id' => 0]
     */
    public function __construct(array $params = null)
    {
        $this->className = get_called_class();
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
        $tableName = mb_strtolower(removeNamespace($className));
        return getPlural($tableName);
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
        self::queryExecuter();
    }
    
    /**
     * Get the QueryExecuter of this model.
     * 
     * @param string $className (optional)
     * @return \Transactd\CachedQueryExecuter
     */
    public static function queryExecuter($className = null)
    {
        // Only PHP 5.3 or later new static() can not be used
        if ($className === null) {
            $className = get_called_class();
        }

        if (array_key_exists($className, self::$tables) === true) {
            return self::$tables[$className];
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
            $fieldNames = (property_exists($this, 'primaryKey') === true) ? $this->primaryKey : 'id';
        }
        return $fieldNames;
    }

    private function getRelationDestKeyNum($className, $keyFields, $ignoreSegmentCount = false)
    {
        return $className::queryExecuter($className)->getIndexByFieldNames($keyFields, $ignoreSegmentCount);
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
        if ($IntermediateClass === null) {
            $IntermediateClass = ucfirst($name);
        }
        $rel = $this->doMorphMany($funcName, $IntermediateClass, $name);
        // second relation
        $rel2 = $this->doBelongsTo(null, $className, null, $otherKey);
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
     * @param int|string|string[] $otherKey (optional) A key of $className.
     * @return \Transactd\Relation
     */
    protected function morphedByMany($className, $name, $IntermediateClass = null, $foreignKey = null, $otherKey = null)
    {    //Called by Tag::customers() morphedByMany('Customer', 'taggable');
        $funcName = debug_backtrace()[1]['function'];
        $self = get_class($this);
        $key = $self.'_@_'.$funcName;
        $relCache = $this->getCachedRelation($key);
        if ($relCache !== null) {
            return $relCache;
        }

        // first relation
        if ($IntermediateClass === null) {
            $IntermediateClass = ucfirst($name);
        }
        if (!is_int($foreignKey)) {   // $foreignKey = ['tag_id', 'taggable_type']
            if ($foreignKey === null) {
                $foreignKey = [removeNamespace(mb_strtolower($self).'_id'), $name.'_type'];
            }
            $index = $this->getRelationDestKeyNum($IntermediateClass, $foreignKey, true);
        }
        $optimize = true;
        $keyValuePropertyNames = $this->getPrimaryKeyFieldName(null);
        $rel = new Relation($self, $keyValuePropertyNames, $funcName, $IntermediateClass, $index, $optimize, Relation::TYPE_MORPHEDBYMANY);

        // second relation
        $rel2 = $this->doBelongsTo(null, $className, $name.'_id', $otherKey);
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

    private function getMorphManyForeignKey($self, $className, $name, $type, $id, &$keyValuePropertyNames)
    {
        // keyValuePropertyNames ["[$className]" , $this->{$localKey}]
        if ($keyValuePropertyNames === null) {
            $tmp = mb_strtolower(removeNamespace($self));
            $keyValuePropertyNames = $self::queryExecuter()->primaryKeyFieldNames();
            array_splice($keyValuePropertyNames, 0, 0, "[$tmp]"); // insert first
        }

        //$foreignKey [$type,$id]
        if ($type === null) {
            $type = $name.'_type';
        }
        if ($id === null) {
            $id = $name.'_id';
        }

        $tmp = mb_strtolower(removeNamespace($self));
        try {
            $foreignKey = $this->getRelationDestKeyNum($className, [$type, $id], true);
        } catch (\UnexpectedValueException $e) {
            $foreignKey = $this->getRelationDestKeyNum($className, [$id, $type], true);
            if ($keyValuePropertyNames === null) {
                $keyValuePropertyNames = $self::queryExecuter()->primaryKeyFieldNames();
                array_push($keyValuePropertyNames, "[$tmp]"); // add last
            }
        }
        return $foreignKey;
    }

    private function doMorphMany($funcName, $className, $name, $type = null, $id = null, $keyValuePropertyNames = null, $optimize = true)
    {
        $self = get_class($this);
        if ($funcName !== null) {
            $key = $self.'_@_'.$funcName;
            $rel = $this->getCachedRelation($key);
            if ($rel !== null) {
                return $rel;
            }
        }
        //$keyValuePropertyNames = $localKey;
        $foreignKey = $this->getMorphManyForeignKey($self, $className, $name, $type, $id, $keyValuePropertyNames);
        $rel = new Relation($self, $keyValuePropertyNames, $funcName, $className, $foreignKey, $optimize, Relation::TYPE_MORPHMANY);
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
     * @return \Transactd\Relation
     */
    protected function morphMany($className, $name, $type = null, $id = null, $keyValuePropertyNames = null )
    {
        $funcName = debug_backtrace()[1]['function'];
        $optimize = true;
        return $this->doMorphMany($funcName, $className, $name, $type, $id, $keyValuePropertyNames, $optimize);
    }
    /**
     @return A model of $className.
     */
    /**
     * Get a relationship object of one-to-one.
     * 
     * @param string $className A class name of relationship.
     * @param int|string|string[] $foreignKey (optional) A key of $IntermediateClass.
     * @param string[] $keyValuePropertyNames (optional) Property name(s) of this.
     * @param bool $optimize (optional) Whether to use the sort-merge or nested loop at the time of batch processing<br/>
     *  <ul>               
     *   <li> true : sort-merge</li>
     *   <li> false : nested loop</li>
     *  </ul>
     * @return \Transactd\Relation
     */
    protected function hasOne($className, $foreignKey = null, $keyValuePropertyNames = null, $optimize = true)
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

    private function doBelongsTo($funcName, $className, $keyValuePropertyNames = null, $otherKey = null, $optimize = true)
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
            if ($otherKey === null) {
                $otherKey = 'id';
            }
            $otherKey = $this->getRelationDestKeyNum($className, $otherKey);
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
     * @param int|string|string[] $otherKey (optional) A key of $className
     * @param bool $optimize (optional) Whether to use the sort-merge or nested loop at the time of batch processing<br/>
     *  <ul>               
     *   <li> true : sort-merge</li>
     *   <li> false : nested loop</li>
     *  </ul>
     * @return \Transactd\Relation
     */
    protected function belongsTo($className, $keyValuePropertyNames = null, $otherKey = null,  $optimize = true)
    {
        $funcName = debug_backtrace()[1]['function'];
        return $this->doBelongsTo($funcName, $className, $keyValuePropertyNames, $otherKey, $optimize);
    }

    private function doHasMany($type, $funcName, $className, $foreignKey = null, $keyValuePropertyNames = null, $optimize = true)
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
                $foreignKey = removeNamespace(mb_strtolower($className).'_id');
            }
            $index = $this->getRelationDestKeyNum($className, $foreignKey, true, true);
        }

        $keyValuePropertyNames = $this->getPrimaryKeyFieldName($keyValuePropertyNames);
        $rel = new Relation($self, $keyValuePropertyNames, $funcName, $className, $index, $optimize, $type);
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
     * @param int|string|string[] $foreignKey (optional) A key of $className.
     * @param string[] $keyValuePropertyNames (optional) Property name(s) of this.
     * @param bool $optimize (optional) Whether to use the sort-merge or nested loop at the time of batch processing<br/>
     *  <ul>               
     *   <li> true : sort-merge</li>
     *   <li> false : nested loop</li>
     *  </ul>
     * @return \Transactd\Relation
     */
    protected function hasMany($className, $foreignKey = null, $keyValuePropertyNames = null, $optimize = true)
    {
        $funcName = debug_backtrace()[1]['function'];
        return $this->doHasMany(Relation::TYPE_HAS_MANY, $funcName, $className, $foreignKey, $keyValuePropertyNames, $optimize);
    }

    /**
     * Get a relationship object of many-to-many.
     * 
     * @param string $className A class name of relationship.
     * @param string $IntermediateClass (optional) Intermediate class name.
     * @param int|string|string[] $foreignKey (optional) A key of $className.
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

        if ($IntermediateClass === null) {
            $IntermediateClass = $self.'_'.removeNamespace($className);
        }
        $rel = $this->doHasMany(Relation::TYPE_BELONGSTO_MANY, $funcName, $IntermediateClass, $foreignKey, null);
        $rel2 = $this->doBelongsTo(null, $className, $intermediatePropertyNames);
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
     * @param bool $optimize (optional) Whether to use the sort-merge or nested loop at the time of batch processing<br/>
     *  <ul>               
     *   <li> true : sort-merge</li>
     *   <li> false : nested loop</li>
     *  </ul>
     * @return \Transactd\Relation
     */
    public function relation($className, $index, $keyValuePropertyNames, $optimize = true)
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
        $hasMany = $className::queryExecuter()->isSeekHasMany($index, $segments);
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
        } elseif ($className === null) {
            $type = Relation::TYPE_MORPHTO;
        } elseif ($index === $className::queryExecuter()->primarykey()) {
            $idx = $this->getRelationDestKeyNum($self, $keyValuePropertyNames, true, true);
            if ($idx !== $self::queryExecuter()->primarykey()) {
                $type = Relation::TYPE_BELONGSTO;
            }
        }
        $rel = new Relation($self, $keyValuePropertyNames, $funcName, $className,
                    $index, $optimize, $type);
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
     * Get all objects.
     * 
     * @param bool $toArray (optional) false: Get by recordset.
     * @return \Transactd\Model[]|\BizStation\Transactd\Recordset
     */
    public static function all($toArray = true)
    {
        return self::queryExecuter()->all($toArray);
    }
    
    /**
     * Find a model by the primary key values.
     * 
     * @param mixed $keyValues The primary key values for find.
     * @return model
     */
    public static function find($keyValues)
    {
        return self::queryExecuter()->indexToPrimaryKey()->find($keyValues);
    }
    
    /**
     * Find multiple models by the primary key values.
     * 
     * @param array $keyValuesArray A araay of the primary key values. Ex:[1, 2] or [[1,1],[1,2]] 
     * @return \Transactd\Collection
     */
    public static function findMany($keyValuesArray)
    {
        return self::queryExecuter()->indexToPrimaryKey()->findMany($keyValuesArray);
    }

    /**
     *  Find a model by the specfied key.
     * 
     * @param int  $index Index number for find.
     * @param mixed $keyValues Key values for find.
     * @return \Transactd\Model
     */
    public static function findWithIndex($index, $keyValues)
    {
        return self::queryExecuter()->index($index)->find($keyValues);
    }
    
    /**
     * 
     * @return \Transactd\Model
     */
    public static function first()
    {
        return self::queryExecuter()->indexToPrimaryKey()->first();
    }
    /**
     * 
     * @param mixed $keyValues Key values for find.
     * @return \Transactd\Model
     * @throws ModelNotFoundException
     */
    public static function findOrFail($keyValues)
    {
        return self::queryExecuter()->indexToPrimaryKey()->find($keyValues, true);
    }

    /**
     * 
     * @return \Transactd\Model
     * @throws ModelNotFoundException
     */
    public static function firstOrFail()
    {
        return self::queryExecuter()->indexToPrimaryKey()->first(true);
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
    
    private function saveHistoryInit()
    {
        if (self::$saveHistory === null) {
            self::$saveHistory = array();
            return true;
        }
        return false;
    }

    private function duplicateSafeExecute($func)
    {
        if (($this->saveOprions & self::SAVE_RELATIONS) !== self::SAVE_RELATIONS) {
            return $this->{$func}();
        }
        $top = $this->saveHistoryInit();
        if (array_search($this, self::$saveHistory, true) !== false) {
            self::$detectRecursiveSave = true;
            return true;
        }
        try {
            array_push(self::$saveHistory, $this);
            $ret = $this->{$func}();
        } catch (\Exception $e) {
            if ($top === true) {
                self::$saveHistory = null;
            }
            throw $e;
        }
        if ($top === true) {
            self::$saveHistory = null;
        }
        return $ret;
    }
    
    private function doSave()
    {
        $q = self::queryExecuter();
        if (property_exists($this, 'timestamps')) {
            $q->setTimeStampMode($this->timestamps);
        }
        if ($q->save($this) === true) {
            // save hasMany childs
            if (($this->saveOprions & self::SAVE_RELATIONS) === self::SAVE_RELATIONS) {
                foreach ($this as $key => $obj) {
                    if (method_exists($this, $key) === true &&
                            is_object($obj) === true && method_exists($obj, 'save') === true) {
                        if ($obj->save() === false) {
                            return false;
                        }
                    }
                }
            }
            return true;
        }
        return false;
    }

    private function doDelete()
    {
        if (self::queryExecuter()->deleteByObj($this) === true) {
            if (($this->saveOprions & self::SAVE_RELATIONS) === self::SAVE_RELATIONS) {
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
     * 
     * @param int $options @see self::$saveOprions 
     * @return bool
     */
    public function save($options = null)
    {
        if ($options !== null) {
            $this->saveOprions = $options;
        }
        return $this->duplicateSafeExecute('doSave');
    }
    
    /**
     * To delete this object from the database.
     * 
     * @param int $options @see self::$saveOprions 
     * @return bool
     */
    public function delete($options = null)
    {
        if ($options !== null) {
            $this->saveOprions = $options;
        }
        return $this->duplicateSafeExecute('doDelete');
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

    /** 
     * Return a JSON text of this model
     * 
     * @return string
     */
    public function toString()
    {
        return Serializer::serialize($this);
    }
    
    /** 
     * Return a JSON text of this model
     * 
     * @return string
     */
    public function toJson()
    {
        return $this->toString();
    }
    /**
     * Copy contents of the original model.
     * 
     * @param \Transactd\Model $src model of original
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function assign($src)
    {
        if (is_object($src)) {
            foreach ($src as $key => $v) {
                $this->{$key} = Serializer::chengeObjectType($v);
            }
            return $this;
        }
        throw new \InvalidArgumentException('$src is not object.');
    }
    
    /**
     * Create a new instance or clone model
     *  
     * @param \Transactd\Model $src (optional) For clone
     * @return \Transactd\Model
     */
    public static function createInstance($src = null)
    {
        $obj = new static();
        if ($src !== null) {
            $obj->assign($src);
        }
        return $obj;
    }
}
