<?php

namespace Transactd;

$pluralDictionary = array('child' => 'children',
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
                            'appendix' => 'appendices', );

function getPlural($tableName)
{
    global $pluralDictionary;
    if (function_exists('str_plural')) {
        return str_plural($tableName);
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
    public static $saveHistory = null;
    const SAVE_RELATIONS = 1;
    const SAVE_BEFOERE_DELETE_ITEMS = 2;
    const DELETE_LOGICAL_ITEMS = 4;

    public static $resolvByCache = false;
    public static $detectRecursiveSave = false;
    public $className;
    public $saveOprions = self::SAVE_RELATIONS;

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

    private static function getTableName($className, $obj)
    {
        if (property_exists($className, 'table')) {
            return $obj->table;
        }
        $tableName = mb_strtolower(removeNamespace($className));
        return getPlural($tableName);
    }

    private static function getConnectionName($className, $obj)
    {
        if (property_exists($className, 'connection')) {
            return $obj->connection;
        }
        return 'default';
    }

    public static function clearTableCache()
    {
        self::$tables = array();
    }

    public static function prepareTable()
    {
        //open table for transctions
        self::queryExecuter();
    }

    public static function queryExecuter($className = null)
    {
        // PHP 5.3以降しか new static() は使えない
        if ($className === null) {
            $className = get_called_class();
        }

        if (array_key_exists($className, self::$tables) === true) {
            return self::$tables[$className];
        } else {
            $obj = new $className();
            $tableName = self::getTableName($className, $obj);
            $connectionName = self::getConnectionName($className, $obj);
            $q = DatabaseManager::connection($connectionName)->cachedQueryExecuter($tableName, $className);
            if (property_exists($className, 'aliases')) {
                $q->setAliases($obj->aliases);
            }
            if (property_exists($className, 'aliases')) {
                $q->setAliases($obj->aliases);
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

    public function filterCreateAttribute($attributes)
    {
        // The gurded faster than the fillable.
        if (property_exists($this, 'fillable')) {
            $fillable = $this->fillable;
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
            $guarded = $this->guarded;
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

    /*function __set($name, $value)
    {
        //if ($name === 'fillable' || $name === 'guarded' || $name === 'table' ||
        //	$name === 'primaryKey' || $name === 'timestamps' || $name === 'aliases')
        //	;

    }*/

    /**
     $fieldNamesが空なら$primaryKeyを探す。なければ'id'とする
     */
    public function getKeyFieldName($fieldNames)
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

    protected function morphToMany($className, $name, $IntermediateClass = null, $foreignKey = null, $otherKey = null, bool $inverse = null)
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
        $keyValueFieldNames = $this->getKeyFieldName(null);
        $rel = new Relation($self, $keyValueFieldNames, $funcName, $IntermediateClass, $index, $optimize, Relation::TYPE_MORPHEDBYMANY);

        // second relation
        $rel2 = $this->doBelongsTo(null, $className, $name.'_id', $otherKey);
        $rel->setSubRelation($rel2);
        self::$rerations[$key] = $rel;
        $rel->setParent($this);
        return $rel;
    }
    /**
     @$classMap value to class name for $type field type is integer or no class name.  [1 => Customer, 2 => Vendor]
     */
    protected function morphTo($name = null, $type = null, $id = null, array $classMap = null)
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

        // keyValueFieldNames
        if ($type === null) {
            $type = $name.'_type';
        }
        if ($id === null) {
            $id = $name.'_id';
        }
        $keyValueFieldNames = [$type, $id];

        //Now otherkey is unknown.
        $rel = new Relation($self, $keyValueFieldNames, $funcName, null, -1, false, Relation::TYPE_MORPHTO);
        if ($classMap !== null) {
            $rel->setMorphClassMap($classMap);
        }
        self::$rerations[$key] = $rel;
        $rel->setParent($this);
        return $rel;
    }

    protected function morphMany2($className, $index, $keyValueFieldNames, $optimize = true)
    {
        $funcName = debug_backtrace()[1]['function'];
        $self = get_class($this);
        $key = $self.'_@_'.$funcName;
        $relCache = $this->getCachedRelation($key);
        if ($relCache !== null) {
            return $relCache;
        }
        $rel = new Relation($self, $keyValueFieldNames, $funcName, $className, $index, $optimize, Relation::TYPE_MORPHMANY);
        self::$rerations[$key] = $rel;
        $rel->setParent($this);
        return $rel;
    }

    private function getMorphManyForeignKey($self, $className, $name, $type, $id, $localKey, &$keyValueFieldNames)
    {
        // keyValueFieldNames ["[$className]" , $this->{$localKey}]
        if ($localKey === null) {
            $tmp = mb_strtolower(removeNamespace($self));
            $keyValueFieldNames = $self::queryExecuter()->primaryKeyFieldNames();
            array_splice($keyValueFieldNames, 0, 0, "[$tmp]"); // insert first
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
            if ($localKey === null) {
                $keyValueFieldNames = $self::queryExecuter()->primaryKeyFieldNames();
                array_push($keyValueFieldNames, "[$tmp]"); // add last
            }
        }
        return $foreignKey;
    }

    private function doMorphMany($funcName, $className, $name, $type = null, $id = null, $localKey = null, $optimize = true)
    {
        $self = get_class($this);
        if ($funcName !== null) {
            $key = $self.'_@_'.$funcName;
            $rel = $this->getCachedRelation($key);
            if ($rel !== null) {
                return $rel;
            }
        }
        $keyValueFieldNames = $localKey;
        $foreignKey = $this->getMorphManyForeignKey($self, $className, $name, $type, $id, $localKey, $keyValueFieldNames);
        $rel = new Relation($self, $keyValueFieldNames, $funcName, $className, $foreignKey, $optimize, Relation::TYPE_MORPHMANY);
        if ($funcName !== null) {
            self::$rerations[$key] = $rel;
        }

        $rel->setParent($this);
        return $rel;
    }

    /**
     if $localKey is array , it is include fixed value . ['['.1.']', 'id']
     */
    protected function morphMany($className, $name, $type = null, $id = null, $localKey = null, $optimize = true)
    {
        $funcName = debug_backtrace()[1]['function'];

        return $this->doMorphMany($funcName, $className, $name, $type, $id, $localKey, $optimize);
    }
    /**
     @return A model of className.
     */
    protected function hasOne($className, $foreignKey = null, $keyValueFieldNames = null, $optimize = true)
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
        $keyValueFieldNames = $this->getKeyFieldName($keyValueFieldNames);
        $rel = new Relation($self, $keyValueFieldNames, $funcName, $className, $foreignKey, $optimize, Relation::TYPE_HAS_ONE);
        self::$rerations[$key] = $rel;
        $rel->setParent($this);
        return $rel;
    }

    private function doBelongsTo($funcName, $className, $keyValueFieldNames = null, $otherKey = null, $optimize = true)
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
        $keyValueFieldNames = ($keyValueFieldNames === null) ?
                                mb_strtolower($className).'_id' : $keyValueFieldNames;
        $rel = new Relation($self, $keyValueFieldNames, $funcName, $className, $otherKey, $optimize, Relation::TYPE_BELONGSTO);
        if ($funcName !== null) {
            self::$rerations[$key] = $rel;
        }
        $rel->setParent($this);
        return $rel;
    }

    protected function belongsTo($className, $keyValueFieldNames = null, $otherKey = null, $optimize = true)
    {
        $funcName = debug_backtrace()[1]['function'];

        return $this->doBelongsTo($funcName, $className, $keyValueFieldNames, $otherKey, $optimize);
    }

    private function doHasMany($type, $funcName, $className, $foreignKey = null, $keyValueFieldNames = null, $optimize = true)
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

        $keyValueFieldNames = $this->getKeyFieldName($keyValueFieldNames);
        $rel = new Relation($self, $keyValueFieldNames, $funcName, $className, $index, $optimize, $type);
        if ($funcName !== null) {
            self::$rerations[$key] = $rel;
        }
        $rel->setParent($this);
        return $rel;
    }

    protected function hasMany($className, $foreignKey = null, $keyValueFieldNames = null, $optimize = true)
    {
        $funcName = debug_backtrace()[1]['function'];

        return $this->doHasMany(Relation::TYPE_HAS_MANY, $funcName, $className, $foreignKey, $keyValueFieldNames, $optimize);
    }

    protected function belongsToMany($className, $IntermediateClass = null, $foreignKey = null, $belongsToValueFields = null)
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
        $rel2 = $this->doBelongsTo(null, $className, $belongsToValueFields);
        $rel->setSubRelation($rel2);
        return $rel;
    }

    public function relation($className, $index, $keyValueFieldNames, $optimize = true)
    {
        $funcName = debug_backtrace()[1]['function'];
        $self = get_class($this);
        $key = $self.'_@_'.$funcName;
        $relCache = $this->getCachedRelation($key);
        if ($relCache !== null) {
            return $relCache;
        }
        if (is_array($keyValueFieldNames)) {
            $segments = count($keyValueFieldNames);
        } else {
            $segments = 1;
        }
        $type = Relation::TYPE_HAS_ONE;
        $hasMany = $className::queryExecuter()->isSeekHasMany($index, $segments);
        if ($hasMany === true) {
            $type = Relation::TYPE_HAS_MANY;
            if (is_array($keyValueFieldNames) === true) {
                foreach ($keyValueFieldNames as $name) {
                    if (strpos($name, '[') === 0) {
                        $type = Relation::TYPE_MORPHMANY;
                        break;
                    }
                }
            }
        } elseif ($className === null) {
            $type = Relation::TYPE_MORPHTO;
        } elseif ($index === $className::queryExecuter()->primarykey()) {
            $idx = $this->getRelationDestKeyNum($self, $keyValueFieldNames, true, true);
            if ($idx !== $self::queryExecuter()->primarykey()) {
                $type = Relation::TYPE_BELONGSTO;
            }
        }
        $rel = new Relation($self, $keyValueFieldNames, $funcName, $className,
                    $index, $optimize, $type);
        self::$rerations[$key] = $rel;
        $rel->setParent($this);
        return $rel;
    }

    public static function resetQuery()
    {
        self::queryExecuter()->reset();
    }

    public static function all($toArray = true)
    {
        return self::queryExecuter()->all($toArray);
    }

    public static function find($keyValues)
    {
        return self::queryExecuter()->indexToPrimaryKey()->find($keyValues);
    }

    public static function findWithIndex($index, $keyValues)
    {
        return self::queryExecuter()->index($index)->find($keyValues);
    }

    public static function first()
    {
        return self::queryExecuter()->indexToPrimaryKey()->first();
    }

    public static function findOrFail($keyValues)
    {
        return self::queryExecuter()->indexToPrimaryKey()->find($keyValues, true);
    }

    public static function firstOrFail()
    {
        return self::queryExecuter()->indexToPrimaryKey()->first(true);
    }

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

    public function save($options = null)
    {
        if ($options !== null) {
            $this->saveOprions = $options;
        }
        return $this->duplicateSafeExecute('doSave');
    }

    public function delete($options = null)
    {
        if ($options !== null) {
            $this->saveOprions = $options;
        }
        return $this->duplicateSafeExecute('doDelete');
    }

    public function refresh()
    {
        return self::queryExecuter()->refresh($this);
    }

    public static function resolvRelations($array, $functionNames)
    {
        if (count($array) > 0) {
            if (is_array($functionNames) === false) {
                $functionNames = array($functionNames);
            }

            foreach ($functionNames as $functionName) {
                $array[0]->{$functionName}()->resolvRelations($array);
            }
        }
    }

    public function toString()
    {
        return Serializer::serialize($this);
    }

    public function toJson()
    {
        return $this->toString();
    }

    public function assign($src)
    {
        if (is_object($src)) {
            foreach ($src as $key => $v) {
                $this->{$key} = Serializer::getTypeValue($v);
            }
            return $this;
        }
        throw new \InvalidArgumentException('$src is not object.');
    }

    public static function createInstance($src = null)
    {
        $obj = new static();
        if ($src !== null) {
            $obj->assign($src);
        }
        return $obj;
    }
}
