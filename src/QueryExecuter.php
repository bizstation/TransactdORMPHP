<?php

namespace Transactd;

use BizStation\Transactd\Transactd;
use BizStation\Transactd\ActiveTable;
use BizStation\Transactd\SortFields;
use BizStation\Transactd\Recordset;
use Transactd\IOException;

Transactd::setFieldValueMode(Transactd::FIELD_VALUE_MODE_VALUE);

class QueryExecuter
{
    const SEEK_EQUAL = 0;
    const SEEK_FIRST = 1;
    const SEEK_LAST =  2;
    const SEEK_GREATER_OREQUAL = 3;
    const SEEK_GREATER = 4;
    const SEEK_LESSTHAN_OREQUAL = 5;
    const SEEK_LESSTHAN = 6;

    protected $at;
    protected $tb = null;
    protected $tbr = null;
    protected $q;
    protected $dbm = null;
    protected $primaryKeyFieldNames = array();
    protected $keyFieldNameCache = array();
    protected $keyFieldCache = array();
    protected $primaryKey = 0;
    protected $with = array();
    protected $obj;
    protected $spcoll = false;

    private $gq = null;
    private $rq = null;
    private $sort = null;
    private $union = null;
    private $timeStampMode = true;
    private $removeInvalidRecord = false;
    private $oprOrder = array();
    private $joinParams = array();

    protected $creating = null;
    protected $created = null;
    protected $updating = null;
    protected $updated = null;
    protected $saving = null;
    protected $saved = null;
    protected $deleting = null;
    protected $deleted = null;

    /**
     * Set a event function of CUD and save operations.
     *
     * @param string $name The event name.<br/>
     *              ('creating', 'created')<br/>
     *              ('updating', 'updated')<br/>
     *              ('saving', 'saved')<br/>
     *              ('deleting', 'deleted')<br/>
     * @param string $class A static function class name of event handler.
     */
    public function setEvent($name, $class)
    {
        if ($name === 'creating') {
            $this->creating = $class;
        }
        if ($name === 'created') {
            $this->created = $class;
        }
        if ($name === 'updating') {
            $this->updating = $class;
        }
        if ($name === 'updated') {
            $this->updated = $class;
        }
        if ($name === 'saving') {
            $this->saving = $class;
        }
        if ($name === 'saved') {
            $this->saved = $class;
        }
        if ($name === 'deleting') {
            $this->deleting = $class;
        }
        if ($name === 'deleted') {
            $this->deleted = $class;
        }
    }
 
    /**
     * Reset the execute paramators.
     *
     * @return \Transactd\QueryExecuter
     */
    public function reset()
    {
        $this->oprOrder = array();
        $this->gq = null;
        $this->rq = null;
        $this->sort = null;
        $this->union = null;
        $this->removeInvalidRecord = false;
        $this->q->reset();
        $this->at->index($this->primaryKey);
        $this->tb->setKeyNum($this->primaryKey);
        $this->at->table()->clearBuffer();
        $this->joinParams = array();
        $this->with = array();
        return $this;
    }

    private function isInstanceOf($obj, $name)
    {
        $cname = get_class($obj);
        if ($cname !== false && strpos($cname, $name) !== false) {
            return true;
        }
        return false;
    }
    
    /**
     *
     * @param BizStation\Transactd\Recordset $rs
     * @return BizStation\Transactd\Recordset
     */
    protected function setFetchClass($rs)
    {
        $rs->fetchClass = $this->tb->fetchClass;
        $rs->fetchMode = Transactd::FETCH_USR_CLASS;
        return $rs;
    }

    private function cacheKeyFields()
    {
        $td = $this->tb->tableDef();
        $this->primaryKey = $td->primaryKeynum;
        for ($i = 0; $i < $td->keyCount; ++$i) {
            $fds = array();
            $names = array();
            $kd = $td->keyDef($i);
            for ($j = 0; $j < $kd->segmentCount; ++$j) {
                $n = $kd->segment($j)->fieldNum;
                $fds[$j] = $n;
                $names[$j] = $td->fieldDef($n)->name();
                if ($i === $this->primaryKey) {
                    $this->primaryKeyFieldNames[$j] = $names[$j];
                }
            }
            $this->keyFieldCache[$i] = $fds;
            $this->keyFieldNameCache[$i] = $names;
        }
    }
    
    /**
     * Get the array of primary key field number.
     *
     * @return int[]
     */
    protected function primaryKeyFields()
    {
        return $this->keyFieldCache[$this->primaryKey];
    }
    
    /**
     * Get the primary key number.
     *
     * @return int
     */
    public function primarykey()
    {
        return $this->primaryKey;
    }
    
     /**
     * Get the array of primary key field name.
     *
     * @return string[]
     */
    public function primaryKeyFieldNames()
    {
        return $this->keyFieldNameCache[$this->primaryKey];
    }
    
    /**
     *  Get the array of key field name of specified index.
     *
     * @param int $index The key number
     * @return string[]
     */
    public function keyFieldNames($index)
    {
        return $this->keyFieldNameCache[$index];
    }

    /**
     * Returns whether multiple records search from the specified parameters.
     *
     * @param int $index The key number.
     * @param int $segments The number of segments to be used.
     * @return boolean
     */
    public function isSeekHasMany($index, $segments)
    {
        if ($segments < count($this->keyFieldCache[$index])) {
            return true;
        }
        $kd = $this->tb->tableDef()->keyDef($index);

        return $kd->segment(0)->flags->bit0 === 1;
    }

    /**
     * Get the writable internal table Object.
     *
     * @return BizStation\Transactd\Table
     */
    public function table()
    {
        return $this->tb;
    }

    /**
     * Get the internal ActiveTable Object.
     *
     * @return BizStation\Transactd\ActiveTable
     */
    public function activeTable()
    {
        return $this->at;
    }

    /**
     *  Get the writable internal table Object.
     *
     * @return BizStation\Transactd\Table
     */
    public function getWritableTable()
    {
        return $this->tb;
    }

    /**
     *  Get the read-only internal table Object.
     *
     * @return BizStation\Transactd\Table
     */
    public function getReadbleTable()
    {
        return $this->tbr;
    }

    /**
     *
     * @param string $tableName
     * @param BizStation\Transactd\PooledDbManager|BizStation\Transactd\database $dbm Master database.
     * @param BizStation\Transactd\PooledDbManager|BizStation\Transactd\database $dbs Slave database.
     * @param string $className (optional) A class name of result.
     * @throws IOException
     */
    public function __construct($tableName, $dbm, $dbs, $className='stdClass')
    {
        $mode_m = Transactd::TD_OPEN_NORMAL;
        $mode_s = Transactd::TD_OPEN_READONLY;
        if ($dbm === null) {
            $mode_m = Transactd::TD_OPEN_READONLY;
            $dbm = $dbs;
        } elseif ($dbs === null) {
            $mode_s = Transactd::TD_OPEN_NORMAL;
            $dbs = $dbm;
        }
        $this->dbm = $dbm;
        
        // For replication, Master first 
        if ($mode_m !== $mode_s) {
            $this->tb = $dbm->openTable($tableName, $mode_m);
            if ($this->tb === null) {
                throw new IOException($tableName.' table open stat='.$dbm->stat());
            }
            //Wait for the table to be created on the slave.
            /*$db =  ($dbs instanceof BizStation\Transactd\PooledDbManager) ?  $dbs->slave() : $dbs;
            if ($db !== null) {
                $index = $db->dbdef()->tableNumByName($tableName);
                while (!$db->existsTableFile($index)) {
                    usleep(10000);
                }
            }*/
        }
        $this->at = new ActiveTable($dbs, $tableName, $mode_s);
        if ($this->at === null) {
            throw new IOException($tableName.' active table open stat='.$dbs->stat());
        }
        if ($mode_m === $mode_s) {
            $this->tb = $this->at->table();
        }

        $this->tb->fetchClass = $className;
        $this->tb->fetchMode = Transactd::FETCH_USR_CLASS;
        $this->tbr = $this->at->table();
        $this->setFetchClass($this->tbr);

        $this->cacheKeyFields();
        $this->q = new QueryAdapter();
        $this->obj = new $className();
        $this->spcoll = method_exists($this->obj, 'newCollection');
    }

    /**
     *
     * @param BizStation\Transactd\Table $tb
     * @param BizStation\Transactd\Table $src
     */
    protected function copyKeyValues($tb, $src)
    {
        $td = $tb->tableDef();
        $kd = $td->keyDef($tb->keyNum());
        for ($i = 0; $i < $kd->segmentCount; ++$i) {
            $index = $kd->segment($i)->fieldNum;
            $tb->setFV($index, $src->getFVstr($index));
        }
    }

    /**
     * Set key values to current key fields
     *
     * @param mixed|mixed[] $id
     * @param BizStation\Transactd\Table $tb
     */
    protected function setKeyValues($id, $tb)
    {
        $fields = $this->keyFieldCache[$tb->keyNum()];
        if (is_array($id)) {
            $n = count($fields) < count($id) ?
                        count($fields) : count($id);
            for ($i = 0; $i < $n; ++$i) {
                $tb->setFV($fields[$i], $id[$i]);
            }
        } else {
            $tb->setFV($fields[0], $id);
        }
    }
    
    /**
     *
     * @param string|string[] $fieldNames
     * @param bool $notUniqueAble
     * @param bool $ignoreCount
     * @return int
     * @throws \UnexpectedValueException
     */
    public function getIndexByFieldNames($fieldNames, $notUniqueAble = false, $ignoreCount = false)
    {
        $src = array();
        if (!is_array($fieldNames)) {
            array_push($src, $this->tb->fieldNumByName($fieldNames));
        } else {
            for ($i = 0; $i < count($fieldNames); ++$i) {
                array_push($src, $this->tb->fieldNumByName($fieldNames[$i]));
            }
        }
        $keysFields = $this->keyFieldCache;
        for ($i = 0; $i < count($keysFields); ++$i) {
            $fields = $keysFields[$i];
            $flag = true;
            if (count($fields) === count($src)) {
                for ($j = 0; $j < count($fields); ++$j) {
                    if ($fields[$j] !== $src[$j]) {
                        $flag = false;
                        break;
                    }
                }
                if ($flag === true) {
                    if ($notUniqueAble === false &&
                            $this->tb->tableDef()->keyDef($i)->segment(0)->flags->bit0 === 1) {
                        throw new \UnexpectedValueException('Index is not unique key.');
                    }
                    return $i;
                }
            } elseif ($ignoreCount === true && count($fields) >= count($src)) {
                for ($j = 0; $j < count($src); ++$j) {
                    if ($fields[$j] !== $src[$j]) {
                        $flag = false;
                        break;
                    }
                }
                if ($flag === true) {
                    return $i;
                }
            }
        }
        throw new \UnexpectedValueException('Index field name(s):'.json_encode($fieldNames));
    }
    
    /**
     * Set timestamp mode to wriatble internal table.
     *
     * @param bool $v
     */
    public function setTimeStampMode($v)
    {
        if ($this->timeStampMode !== $v) {
            if ($v === false) {
                $this->tb->setTimestampMode(Transactd::TIMESTAMP_VALUE_CONTROL);
            } else {
                $this->tb->setTimestampMode(Transactd::TIMESTAMP_ALWAYS);
            }
            if ($this->tb->stat() === 0) {
                $this->timeStampMode = $v;
            }
        }
    }
    
    /**
     * Set field name aliases
     *
     * @param array $aliases [['original' => 'alias'], ...]
     */
    public function setAliases($aliases)
    {
        foreach ($aliases as $key => $value) {
            $this->at->alias($key, $value);
            $this->tbr->setAlias($key, $value);
            $this->tb->setAlias($key, $value);
        }
    }

    /**
     * Set the index number for search.
     *
     * @param int $index
     * @return \Transactd\QueryExecuter
     */
    public function index($index)
    {
        $this->at->index($index);
        $this->tb->setKeyNum($index);
        return $this;
    }

    /**
     * Change the index number to the primary key.
     *
     * @return \Transactd\QueryExecuter
     */
    public function indexToPrimaryKey()
    {
        return $this->index($this->primaryKey);
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
    public function keyValue($v1, $v2 = null, $v3 = null, $v4 = null, $v5 = null, $v6 = null, $v7 = null, $v8 = null)
    {
        $this->at->keyValue($v1, $v2, $v3, $v4, $v5, $v6, $v7, $v8);
        return $this;
    }
    
    /**
     * Whether or not to enable updateConflictCheck.
     *
     * @param bool $v
     * @return \Transactd\QueryExecuter
     * @throws \RuntimeException
     */
    public function updateConflictCheck($v)
    {
        if ($this->tb->setUpdateConflictCheck($v) === false) {
            throw new \RuntimeException('This table has no updatable timestamp field.');
        }
        return $this;
    }
    
    /**
     * Search results the registration of the chunk.
     *
     * @param int $n Count of a chunk.
     * @param string $func Handler function name.
     * @return bool
     */
    public function chunk($n, $func)
    {
        //Execute query at each chunk.
        $this->take($n);
        $this->skip(0);
        $rs = $this->get(false);
        $ret = true;
        while ($rs->size()) {
            $this->setFetchClass($rs);
            $ret = $func($rs->toArray());
            if ($ret === false) {
                break;
            }
            $rs = $this->at->readMore();
        }
        return $ret;
    }

    private function doRremoveInvalidRecord($rs)
    {
        $size = $rs->size();
        for ($i = $size - 1; $i >= 0; --$i) {
            if ($rs[$i]->isInvalidRecord() === true) {
                $rs->erase($i);
            }
        }
    }

    private function recordsetOpration($rs)
    {
        $count = count($this->oprOrder);
        $joinCount = 0;
        for ($i = 0; $i < $count; ++$i) {
            switch ($this->oprOrder[$i]) {
            case 'removeNullRecord':
                $this->doRremoveInvalidRecord($rs);
                break;
            case 'groupBy':
                $rs->groupBy($this->gq);
                break;
            case 'matchBy':
                $rs->matchBy($this->rq);
                break;
            case 'orderBy':
                $rs->orderBy($this->sort);
                break;
            case 'join':
                $rs = $this->execJoin($rs, $this->joinParams[$joinCount]);
                ++$joinCount;
                break;
            }
        }
        return $rs;
    }

    /**
     * Execute the contents of the queue in the order. And returns the result.
     *
     * @param bool $toArray (optional) false: Get by recordset.
     * @return \Transactd\Collection|BizStation\Transactd\Recordset
     * @throw IOException
     */
    public function read($toArray = true)
    {
        return $this->get($toArray);
    }

    /**
     * Create a collection from a result array.
     *
     * @param array $ar A array of result.
     * @param \Transactd\Relation $rel (optinal) Relation object of result.
     * @param object $parent (optinal) A parent object of relation.
     * @return \Transactd\Collection
     */
    public function arrayToCollection($ar, $rel = null, $parent = null)
    {
        if ($this->spcoll === true) {
            return $this->obj->newCollection($ar, $rel, $parent);
        }
        return new Collection($ar, $rel, $parent);
    }

    private function getResults($rs, $toArray, $with)
    {
        try {
            $this->setFetchClass($rs);
            $toArray = ($with === true) ? true : $toArray;
            if ($toArray === true) {
                $rs = $rs->toArray();
                if ($with === true) {
                    Model::resolveRelations($rs, $this->with);
                }
                $rs = $this->arrayToCollection($rs);
            }
            $this->reset();
            return $rs;
        } catch (\Exception $e) {
            $this->reset();
            throw $e;
        }
    }

    /**
     * The alias of this::read().
     *
     * @param bool $toArray (optional) false: Get by recordset.
     * @return \Transactd\Collection|BizStation\Transactd\Recordset
     * @throw IOException
     */
    public function get($toArray = true)
    {
        $with = count($this->with) !== 0;
        $rs = $this->at->read($this->q->query());
        $rs = $this->recordsetOpration($rs);
        if ($this->q->getSkip() > 0) {
            $n = $this->q->getSkip();
            for ($i = 0; $i < $n; ++$i) {
                $rs->erase(0);
            }
        }
        if ($this->union !== null) {
            $rs = $this->union->unionRecordset($rs);
        }
        return $this->getResults($rs, $toArray, $with);
    }

    private function execJoin($rs, $p)
    {
        $keyNmaes = $p[2];
        $q = $p[1];
        $v0 = count($keyNmaes) > 0 ? $keyNmaes[0] : null;
        $v1 = count($keyNmaes) > 1 ? $keyNmaes[1] : null;
        $v2 = count($keyNmaes) > 2 ? $keyNmaes[2] : null;
        $v3 = count($keyNmaes) > 3 ? $keyNmaes[3] : null;
        $v4 = count($keyNmaes) > 4 ? $keyNmaes[4] : null;
        $v5 = count($keyNmaes) > 5 ? $keyNmaes[5] : null;
        $v6 = count($keyNmaes) > 6 ? $keyNmaes[6] : null;
        $v7 = count($keyNmaes) > 7 ? $keyNmaes[7] : null;
        if ($p[0] === true) {
            $rs = $q->at->outerJoin($rs, $q->q->query(), $v0, $v1, $v2, $v3, $v4, $v5, $v6, $v7);
        } else {
            $rs = $q->at->join($rs, $q->q->query(), $v0, $v1, $v2, $v3, $v4, $v5, $v6, $v7);
        }
        $q->reset();
        return $rs;
    }

    private function addJoin($q, $keyNmaes, $outer)
    {
        array_push($this->joinParams, array($outer, $q, $keyNmaes));
        array_push($this->oprOrder, 'join');
        return $this;
    }

    /**
     * Add the outer-join in the execution queue.
     *
     * @param \BizStation\Transactd\Query $q Queryy of select field.
     * @param string[] $keyNmaes Key names of source table.
     * @return \Transactd\Collection
     */
    public function outerJoin($q, $keyNmaes)
    {
        return $this->addJoin($q, $keyNmaes, true);
    }

    /**
     * Add the inner-join in the execution queue.
     *
     * @param \BizStation\Transactd\Query $q Queryy of select field.
     * @param string[] $keyNmaes Key names of source table.
     * @return \Transactd\Collection
     */
    public function join($q, $keyNmaes)
    {
        return $this->addJoin($q, $keyNmaes, false);
    }

    /**
     * Add the grouping query in the execution queue.
     *
     * @param \BizStation\Transactd\GroupQuery $gq
     * @return \Transactd\QueryExecuter
     * @throws \InvalidArgumentException
     */
    public function groupBy($gq)
    {
        if ($this->gq === null &&
            $this->isInstanceOf($gq, 'BizStation\Transactd\GroupQuery') === true) {
            $this->gq = $gq;
            array_push($this->oprOrder, 'groupBy');
        } else {
            throw new \InvalidArgumentException('arg1 is not class of BizStation\Transactd\groupQuery.');
        }
        return $this;
    }

   /**
     * Add the sort-ordering query in the execution queue.
     *
     * @param string $name A name of sort-target field name
     * @param bool $asc Specifies whether the ascending order.
     * @return \Transactd\QueryExecuter
     * @throws \InvalidArgumentException
     */
    public function orderBy($name, $asc)
    {
        if ($this->sort === null) {
            $this->sort = new SortFields();
            array_push($this->oprOrder, 'orderBy');
        }
        $this->sort->add($name, $asc);
        return $this;
    }

    /**
     * Add the filter(match-by) query in the execution queue.
     *
     * @param \BizStation\Transactd\RecordsetQuery $rq
     * @return \Transactd\QueryExecuter
     */
    public function matchBy($rq)
    {
        $this->rq = $rq;
        array_push($this->oprOrder, 'matchBy');
        return $this;
    }
    
    /**
     * Execute the contents of the queue in the order. And returns the BizStation\Transactd\Recordset result.
     *
     * @return BizStation\Transactd\Recordset
     */
    public function recordset()
    {
        return $this->get(false);
    }
    
    /**
     * Set the union data.
     *
     * @param \BizStation\Transactd\Recordset $rs A source data of recordset.
     * @return \Transactd\QueryExecuter
     * @throws \InvalidArgumentException
     */
    public function union($rs)
    {
        if ($this->isInstanceOf($rs, 'BizStation\Transactd\Recordset') === true) {
            $this->union = $rs;
            return $this;
        }
        throw new \InvalidArgumentException('arg1 is not class of BizStation\Transactd\recordset.');
    }
    
    /**
     *
     * @param string $func
     * @return \Transactd\QueryExecuter
     */
    public function with($func)
    {
        array_push($this->with, $func);
        return $this;
    }

    /**
     * Add the remove null operation in the execution queue.
     *
     * @return \Transactd\QueryExecuter
     */
    public function removeNullRecord()
    {
        $this->removeInvalidRecord = true;
        array_push($this->oprOrder, 'removeNullRecord');
        return $this;
    }
    
    private function zeroCountResult($toArray = true)
    {
        if ($toArray === true) {
            return $this->arrayToCollection(array());
        }
        return new Recordset();
    }
    
    /**
     * Reads all records from a table by the primary key.
     *
     * @param bool $toArray (optional) false: Get by recordset.
     * @return \Transactd\Collection|BizStation\Transactd\Recordset
     */
    public function all($toArray = true)
    {
        $this->indexToPrimaryKey();
        $tb = $this->tbr;
        $tb->clearBuffer();
        $tb->seekFirst(); // set first key values
        if ($tb->stat() === 0) {
            return $this->get($toArray);
        }
        $this->reset();
        return $this->zeroCountResult($toArray);
    }
    
    /**
     * Find a record from the table by the current key.
     *
     * @param mixed|mixed[] $id The primary key values.
     * @param bool $throwException Whether to return an error when an exception.
     * @return object|null
     * @throws ModelNotFoundException
     */
    public function find($id, $throwException = false)
    {
        $tb = $this->tbr;
        $stat = $tb->seekKeyValue($id);
        if ($stat === 0) {
            return $tb->fields();
        } elseif ($throwException === true && $stat === Transactd::STATUS_NOT_FOUND_TI) {
            throw new ModelNotFoundException();
        }
        return null;
    }
    
    /**
     * Find multiple records by the current key values.
     *
     * @param array $keyValuesArray A araay of the current key values. Ex:[1, 2] or [[1,1],[1,2]]
     * @return \Transactd\Collection
     * @throws \InvalidArgumentException
     */
    public function findMany($keyValuesArray)
    {
        $segments = count($this->primaryKeyFields());
        $flatArray = array();
        foreach ($keyValuesArray as $id) {
            if (is_array($id)) {
                if (count($id) !== $segments) {
                    throw new \InvalidArgumentException();
                }
                array_push($flatArray, $id);
            }
        }
        array_push($flatArray, $id);
        return $this->whereInKey($flatArray, $segments)->get();
    }

    /**
     * Find a first record by the current conditions.
     *
     * @param bool $throwException Whether throw an exception that could not be found.
     * @return object
     * @throws ModelNotFoundException
     */
    public function first($throwException = false)
    {
        if ($this->q->isWhereDefined() === true) {
            return $this->get(false)->first();
        }
        $ret = null;
        $tb = $this->tbr;
        $tb->seekFirst();
        if ($tb->stat() === 0) {
            $ret = $tb->fields();
        } elseif ($throwException === true && $tb->stat() === Transactd::STATUS_EOF) {
            $this->reset();
            throw new ModelNotFoundException();
        }
        $this->reset();
        return $ret;
    }
    
    /**
     * Get a genaretor of the current query.
     * Please set Index keyValue and query conditions beforehand.
     * @return Generator
     */
    public function cursor()
    {
        $tb = $this->tbr;
        $tb->setQuery($this->q->query());
        $tb->find();
        while ($tb->stat() === 0) {
            yield $tb->fields();
            $tb->findNext();
        }
        $this->reset();
    }
    
    private static function createIterator($tb, $forword, $lockBias)
    {
        return ($forword) ? new TableForwordIterator($tb, $lockBias) :
                        new TableReverseIterator($tb, $lockBias);
    }        
    
    /**
     * Read a record with the $op and return a iteraor. 
     * Please set key number and key value beforehand.
     * 
     * @param type $op
     * @param type $lockBias SEEK_EQUAL(0) to SEEK_LESSTHAN(6)
     * @return \Transactd\TableIterator
     * @throws \InvalidArgumentException
     */
    public static function getIterator($tb, $op = 0, $forword = true, $lockBias = Transactd::LOCK_BIAS_DEFAULT)
    {
        switch ($op) {
            case self::SEEK_EQUAL:
                $tb->seek($lockBias);
                return self::createIterator($tb, $forword, $lockBias);
            case self::SEEK_FIRST:
                $tb->seekFirst($lockBias);
                return self::createIterator($tb, true, $lockBias);
            case self::SEEK_GREATER_OREQUAL:
                $tb->seekGreater(true, $lockBias);
                return self::createIterator($tb, $forword, $lockBias);
            case self::SEEK_GREATER:
                $tb->seekGreater(false, $lockBias);
                return self::createIterator($tb, $forword, $lockBias);
            case self::SEEK_LAST:
                $tb->seekLast($lockBias);
                return self::createIterator($tb, false, $lockBias);
            case self::SEEK_LESSTHAN_OREQUAL:
                $tb->seekLessThan(true, $lockBias);
                return self::createIterator($tb, $forword, $lockBias);
            case self::SEEK_LESSTHAN:
                $tb->seekLessThan(false, $lockBias);
                return self::createIterator($tb, $forword, $lockBias);
        }
        throw new \InvalidArgumentException('Argument 2 $op');
    }
       
    /**
     * Read a record with the $op and return a writable iteraor. 
     * Please set Index and keyValue beforehand. And finally call reset() to restore index and keyValue.
     * 
     * @param int $op Table::SEEK_EQUAL to Table::SEEK_LESSTHAN
     * @param int $lockBias
     * @return \BizStation\Transactd\TableIterator
     * @throw IOException
     */
    public function serverCursor($op = self::SEEK_EQUAL, $forword = true, $lockBias = Transactd::LOCK_BIAS_DEFAULT)
    {
        $this->copyKeyValues($this->tb, $this->tbr);
        return self::getIterator($this->tb, $op, $forword, $lockBias);    
    }

    private function prepareCreate($attributes)
    {
        $tb = $this->getWritableTable();
        $tb->clearBuffer();
        if ($tb->fetchMode === Transactd::FETCH_USR_CLASS) {
            $obj = new $tb->fetchClass();
            $attributes = $obj->filterCreateAttribute($attributes);
        }
        foreach ($attributes as $fd => $value) {
            $tb->setFV($fd, $value);
        }
        return $tb;
    }

    private function getCreatedObject($tb)
    {
        $obj = $tb->fields();
        if ($this->created !== null) {
            $tmp = $this->created;
            if ($tmp::created($obj) === false) {
                return null;
            }
        }
        return $obj;
    }

   
    /**
     * Create(insert) a new object specified by the property of fetchClass.
     *
     * @param array $attributes The initial value of the attribute.
     * @param type $nosave Whether at the same time it stored in the database?<br/>
     *                      - true Do not save.
     *                      - false Do save.
     *  <ul>
     *   <li> true : Do not save</li>
     *   <li> false : Do save</li>
     *  </ul>
     * @return object
     * @throws IOException
     */
    public function create(array $attributes,  $nosave = false)
    {
        $tb = $this->prepareCreate($attributes);
        if ($nosave === false) {
            $tmp = $this->creating;
            if ($tmp !== null && $tmp::creating($tb->fields()) === false) {
                return null;
            }
            $tb->insert();
            if ($tb->stat() !== 0) {
                throw new IOException($tb->statMsg(), $tb->stat());
            }
        }
        return $this->getCreatedObject($tb);
    }
    /**
     * Find or create(insert) a object specified by attribute.
     *
     * @param array $attributes The initial value of the attribute.
     * @return object
     * @throws IOException
     */
    public function firstOrCreate(array $attributes)
    {
        $tb = $this->prepareCreate($attributes);
        $tb->seek();
        if ($tb->stat() === STATUS_NOT_FOUND_TI) {
            return $this->create($attributes,  false);
        }
        return $tb->fields();
    }

    /**
     * Find or instantiate a object specified by attribute.
     *
     * @param array $attributes The initial value of the attribute.
     * @return object
     * @throws IOException
     */
    public function firstOrNew(array $attributes)
    {
        $tb = $this->prepareCreate($attributes);
        $tb->seek();
        if (($tb->stat() === 0) || $tb->stat() === STATUS_NOT_FOUND_TI) {
            return $tb->fields();
        }
        throw new IOException($tb->statMsg(), $tb->stat());
    }
    
    /**
     * @param \BizStation\Transactd\Table $tb
     * @param bool $delete
     * @param array|null $attributes
     * @return object[]|false A updated object array or false.
     * Caceled by event then throws the ModelUserCancelException.
     * @throws IOException, ModelUserCancelException
     */
    private function doUpdateWhere($tb, $delete, $attributes = null)
    {
        $updateConflictCheck = $tb->updateConflictCheck();
        try {
            $array = array();
            $tb->seekGreater(true);
            if ($tb->stat() === STATUS_EOF) {
                $this->reset();
                return $array;
            }
            $query = $this->q->query();
            $query->bookmarkAlso(true);
            $tb->setQuery($query);
            {
                $size = $tb->recordCount(false, true);
                $tb->setUpdateConflictCheck(false);
                for ($i = 0; $i < $size; ++$i) {
                    $tb->moveBookmarks($i);
                    if ($delete === true) {
                        $tmp = $this->deleting;
                        if ($tmp !== null && $tmp::deleting($tb->fields()) === false) {
                            throw new ModelUserCancelException('deleting');
                        }
                        $tb->del();
                        $tmp = $this->deleted;
                        if ($tmp !== null && $tb->stat() === 0) {
                            $tmp::deleted($tb->fields());
                        }
                    } else {
                        foreach ($attributes as $fd => $value) {
                            $tb->setFV($fd, $value);
                        }
                        $tmp = $this->updating;
                        if ($tmp !== null && $tmp::updating($tb->fields()) === false) {
                            throw new ModelUserCancelException('updating');
                        }
                        $tb->update();
                        $tmp = $this->updated;
                        if ($tmp !== null && $tb->stat() === 0) {
                            $tmp::updated($tb->fields());
                        }
                    }
                    if ($tb->stat() !== 0) {
                        throw new IOException($tb->statMsg(), $tb->stat());
                    }
                    array_push($array, $tb->fields());
                }
            }
            $this->reset();
            $tb->setUpdateConflictCheck($updateConflictCheck);
            return $array;
        } catch (\Exception $e) {
            $this->reset();
            $tb->setUpdateConflictCheck($updateConflictCheck);
            throw $e;
        }
    }

    private function doUpdateOne($tb, $attributes)
    {
        $tb->seek();
        if ($tb->stat() === 0) {
            foreach ($attributes as $fd => $value) {
                $tb->setFV($fd, $value);
            }
            $obj = $tb->fields();
            $tmp = $this->updating;
            if ($tmp !== null && $tmp::updating($obj) === false) {
                throw new ModelUserCancelException('updating');
            }
            $tb->update();
            if ($tb->stat() !== 0) {
                throw new IOException($tb->statMsg(), $tb->stat());
            }
            $tmp = $this->updated;
            if ($tmp !== null) {
                $tmp::updated($obj);
            }
            return array($obj);
        }
        throw new IOException($tb->statMsg(), $tb->stat());
    }

    private function doDeleteOne($tb)
    {
        $obj = $tb->fields();
        $tmp = $this->deleting;
        if ($tmp !== null && $tmp::deleting($obj) === false) {
            throw new ModelUserCancelException('deleting');
        }
        $tb->del(true);
        if ($tb->stat() === 0) {
            $tmp = $this->deleted;
            if ($tmp !== null && $tb->stat() === 0) {
                $tmp::deleted($obj);
            }
            return array($obj);
        }
        throw new IOException($tb->statMsg(), $tb->stat());
    }

    /**
     *
     * @param array $attributes The update value of attributes.
     * @return object[]
     * @throws IOException
     */
    protected function doUpdate($attributes)
    {
        $tb = $this->getWritableTable();
        $this->copyKeyValues($tb, $this->tbr);
        if ($this->q->isWhereDefined() === false) {
            return $this->doUpdateOne($tb, $attributes);
        }
        return $this->doUpdateWhere($tb, false, $attributes);
    }

    /**
     *
     * @return object[]
     * @throws IOException
     */
    protected function doDelete()
    {
        $tb = $this->getWritableTable();
        $this->copyKeyValues($tb, $this->tbr);
        if ($this->q->isWhereDefined() === false) {
            return $this->doDeleteOne($tb);
        }
        return $this->doUpdateWhere($tb, true);
    }

    /**
     * Update records selected by current conditions by the attributes.
     * If no conditions specified updates current key values.
     * If conditions specified the updateConflictCheck dose not works.
     * @param array $attributes The update value of attributes.
     *  Do not specify the current key field.
     * @return int Count of effects.
     * @throws IOException
     */
    public function update(array $attributes)
    {
        return count($this->doUpdate($attributes));
    }

    /**
     * Delete records selected by current conditions.
     * If no conditions specified deletes current key values.
     * If conditions specified the updateConflictCheck dose not works.
     * @return int Count of effects.
     * @throws IOException
     */
    public function delete()
    {
        return count($this->doDelete());
    }
    
    /**
     No error is thrown if id is not found, IOException is thrown if other errors.
     */
    protected function doDestroy($ids)
    {
        $fields = $this->primaryKeyFields();
        $tb = $this->getWritableTable();
        $tb->setKeyNum($this->primaryKey);
        $tb->clearBuffer();
        $idsa = array();
        $qbjArray = array();
        $n = 0;
        if (!is_array($ids) || count($fields) === count($ids)) {
            $idsa[0] = $ids;
        } else {
            $idsa = $ids;
        }
        try {
            for ($i = 0; $i < count($idsa); ++$i) {
                $this->setKeyValues($idsa[$i], $tb);
                $tmp = $this->deleting;
                if ($tmp !== null && $tmp::deleting($tb->fields()) === false) {
                    throw new ModelUserCancelException('deleting');
                }
                $tb->del(true);
                if ($tb->stat() === 0) {
                    ++$n;
                    $obj = $tb->fields();
                    array_push($qbjArray, $obj);
                    $tmp = $this->deleted;
                    if ($tmp !== null) {
                        $tmp::deleted($obj);
                    }
                } elseif ($tb->stat() !== Transactd::STATUS_NOT_FOUND_TI) {
                    throw new IOException($tb->statMsg(), $tb->stat());
                }
            }
            return $qbjArray;
        } catch (\Exception $e) {
            $this->reset();
            throw $e;
        }
    }
    /**
     *
     * @param int|string|(int|string)[] $ids
     * @return int
     */
    public function destroy($ids)
    {
        $array = $this->doDestroy($ids);
        return count($array);
    }
    
    /**
     *
     * @param object $obj
     * @return bool
     * @throws IOException
     */
    public function deleteObject($obj)
    {
        $tmp = $this->deleting;
        if ($tmp !== null && $tmp::deleting($obj) === false) {
            return false;
        }
        $tb = $this->getWritableTable();
        $tb->deleteByObject($obj);
        $stat = $tb->stat();
        if ($stat !== 0 && $stat !== Transactd::STATUS_NOT_FOUND_TI) {
            throw new IOException($tb->statMsg(), $tb->stat());
        }
        $tmp = $this->deleted;
        if ($tmp !== null) {
            $tmp::deleted($obj);
        }
        return true;
    }
    /**
     *
     * @param object $obj
     * @param boolean $forceInsert
     * @return boolean
     * @throws IOException
     */
    public function save($obj, $forceInsert = false)
    {
        //ToDo insert and update event
        $tmp = $this->saving;
        if ($tmp !== null && $tmp::saving($obj) === false) {
            return false;
        }
        $tb = $this->getWritableTable();
        if ($forceInsert === true) {
            $tb->insertByObject($obj, true);
        } else {
            $tb->saveByObject($obj);
        }
        if ($tb->stat() !== 0) {
            throw new IOException($tb->statMsg(), $tb->stat());
        }
        $tmp = $this->saved;
        if ($tmp !== null) {
            $tmp::saved($obj);
        }
        return $tb->stat() === 0;
    }
    
    /**
     * Insert a record.
     *
     * @param array $attributes The initial value of the attribute.
     * @return object
     * @throws IOException
     */
    public function insert($attributes)
    {
        return $this->create($attributes, false);
    }
           
    /**
     * Get a key string from primary key field value of the object.
     *
     * @param object $obj
     * @return string
     */
    public function getUniqueKey($obj)
    {
        $fields = $this->primaryKeyFieldNames;
        $count = count($fields);
        if ($count == 1) {
            if (property_exists($obj, $fields[0])===true) {
                return (string) $obj->{$fields[0]};
            }
        } else {
            $key = '';
            for ($i = 0; $i < $count; ++$i) {
                if (property_exists($obj, $fields[$i])===false) {
                    return null;
                }
                $key .= $obj->{$fields[$i]}.'$\t';
            }
            return $key;
        }
        return null;
    }

    public function __call($name, $arguments)
    {
        if (method_exists('BizStation\Transactd\Recordset', $name)) {
            $reflectionMethod = new \ReflectionMethod('BizStation\Transactd\Recordset', $name);
            return $reflectionMethod->invokeArgs(get(), $arguments);
        } elseif (method_exists($this->tb->fetchClass, 'scope'.$name)) {
            $obj = $this->obj;
            $reflectionMethod = new \ReflectionMethod($this->tb->fetchClass, 'scope'.$name);
            array_unshift($arguments, $this);
            $reflectionMethod->invokeArgs($obj, $arguments);
            return $this;
        }
        throw new \BadMethodCallException($name);
    }

    /**
     * Execute the closure that has been specified by the condition.
     *
     * @param bool $condition
     * @param type $func The closure.
     * @return \Transactd\QueryExecuter
     */
    public function when($condition, $func)
    {
        if ($condition == true) {
            $func($this);
        }
        return $this;
    }
    
    /**
     *
     * @param string $name A field name.
     * @param string|mixed $operator  Operator or a value.
     * @param mixed $value (optional) a value
     * @return \Transactd\QueryExecuter
     */
    public function where($name, $operator=null, $value = null)
    {
        $this->q->where($name, $operator, $value);
        return $this;
    }

    /**
     *
     * @param string $name A field name.
     * @param string $operator  Operator or a value.
     * @param mixed (optional) a value
     * @return \Transactd\QueryExecuter
     */
    public function orWhere($name, $operator=null, $value = null)
    {
        $this->q->orWhere($name, $operator, $value);
        return $this;
    }

    /**
     *
     * @param string $name A field name.
     * @return \Transactd\QueryExecuter
     */
    public function whereNull($name)
    {
        $this->q->whereNull($name);
        return $this;
    }

    /**
     *
     * @param string $name A field name.
     * @return \Transactd\QueryExecuter
     */
    public function orNull($name)
    {
        $this->q->orNull($name);
        return $this;
    }

    /**
     *
     * @param string $name A field name.
     * @return \Transactd\QueryExecuter
     */
    public function whereNotNull($name)
    {
        $this->q->whereNotNull($name);
        return $this;
    }

    /**
     *
     * @param string $name A field name.
     * @return \Transactd\QueryExecuter
     */
    public function orNotNull($name)
    {
        $this->q->orNotNull($name);
        return $this;
    }

    /**
     *
     * @param @param mixed $values Key values
     * @param @param int $segments The segment count of values.
     * @return \Transactd\QueryExecuter
     */
    public function whereInKey($values, $segments = null)
    {
        $this->q->whereInKey($this->tbr, $values, $segments);
        return $this;
    }

    /**
     *
     * @param string $name A field name.
     * @param array $values Key values.
     * @return \Transactd\QueryExecuter
     */
    public function whereIn($name, $values)
    {
        $this->q->whereIn($name, $values);
        return $this;
    }

    /**
     *
     * @param string $name A field name.
     * @param mixed $values Key values.
     * @return \Transactd\QueryExecuter
     */
    public function whereNotIn($name, $values)
    {
        $this->q->whereNotIn($name, $values);
        return $this;
    }

    /**
     *
     * @param string $name A field name.
     * @param mixed[2] $valuePair A pair of first value and end value.
     * @return \Transactd\QueryExecuter
     */
     public function whereBetween($name, $valuePair)
     {
         $this->q->whereBetween($name, $valuePair);
         return $this;
     }

    /**
     *
     * @param string $name A field name.
     * @param mixed[2] $valuePair A pair of first value and end value.
     * @return \Transactd\QueryExecuter
     */
    public function whereNotBetween($name, $valuePair)
    {
        $this->q->whereNotBetween($name, $valuePair);
        return $this;
    }
   
    /**
     *
     * @param string $name A field name.
     * @param string|mixed $operator  Operator or a value.
     * @param mixed $value (optional) a value
     * @return \Transactd\QueryExecuter
     */
    public function whereColumn($name, $operator=null, $value = null)
    {
        $this->q->whereColumn($name, $operator, $value);
        return $this;
    }

    /**
     *
     * @param string $name A field name.
     * @param string|mixed $operator  Operator or a value.
     * @param mixed $value (optional) a value
     * @return \Transactd\QueryExecuter
     */
    public function orColumn($name, $operator=null, $value = null)
    {
        $this->q->orColumn($name, $operator, $value);
        return $this;
    }

    /**
     *
     * @param string|string[] $name1 A field name.
     * @param string $name2 (optional)  A field name.
     * @param string $name3 (optional)  A field name.
     * @param string $name4 (optional)  A field name.
     * @param string $name5 (optional)  A field name.
     * @param string $name6 (optional)  A field name.
     * @param string $name7 (optional)  A field name.
     * @param string $name8 (optional)  A field name.
     * @return \Transactd\QueryExecuter
     */
    public function select($name1, $name2 = null, $name3 = null, $name4 = null, $name5 = null, $name6 = null, $name7 = null, $name8 = null)
    {
        if (is_array($name1)) {
            $this->q->query()->clearSelectFields();
            foreach ($name1 as $nm) {
                $this->q->addSelect($nm);
            }
        } else {
            $this->q->select($name1, $name2, $name3, $name4, $name5, $name6, $name7, $name8);
        }
        return $this;
    }

    /**
     *
     * @param string $name A field name.
     * @return \Transactd\QueryExecuter
     */
    public function addSelect($name)
    {
        $this->q->addSelect($name);
        return $this;
    }

    /**
     *
     * @param int $v
     * @return \Transactd\QueryExecuter
     */
    public function reject($v)
    {
        $this->q->reject($v);
        return $this;
    }
    
    /**
     * Alias of reject(0xffff)
     *
     * @return \Transactd\QueryExecuter
     */
    public function noBreakRecject()
    {
        $this->q->reject(0xffff);
        return $this;
    }
    /**
     *
     * @param int $n
     * @return \Transactd\QueryExecuter
     */
    public function skip($n)
    {
        $this->q->skip($n);
        return $this;
    }

    /**
     *
     * @param int $n
     * @return \Transactd\QueryExecuter
     */
    public function take($n)
    {
        $this->q->take($n);
        return $this;
    }

    /**
     * Alias to the take
     * 
     * @param int $n
     * @return \Transactd\QueryExecuter
     */
    public function limit($n)
    {
        $this->q->take($n);
        return $this;
    }
    
    /**
     * 
     * @param int $v Nstable::findForword| Nstable::findBackForword
     * @return \Transactd\QueryExecuter
     */
    public function direction($v)
    {
        $this->q->direction($v);
        return $this;
    }

    /**
     * Get a description of the query.
     *
     * @return string
     */
    public function queryString()
    {
        return $this->q->query()->toString();
    }

    /**
     * Get a description of the key values.
     *
     * @param \BizStation\Transactd\Table $tb
     * @return string
     */
    private function keyValueDescription($tb)
    {
        $value = '';
        $value .= 'index = '.$tb->keyNum().': ';
        $td = $tb->tableDef();
        for ($i = 0; $i < $td->keyDef($tb->keyNum())->segmentCount; ++$i) {
            if ($i !== 0) {
                $value .= ', ';
            }
            $fdNum = $td->keyDef($tb->keyNum())->segment($i)->fieldNum;
            $value .= $td->fieldDef($fdNum)->name().' = '.$tb->getFVstr($fdNum);
        }
        return $value;
    }

    /**
     * Get a description of the key values and query.
     *
     * @param \BizStation\Transactd\Query $q
     * @return string
     */
    public function queryDescription($q = null)
    {
        if ($q === null) {
            $q = $this->q->query();
        }
        $keyvalue = 'tablename      : '.$this->tbr->tableDef()->tableName().PHP_EOL;
        $keyvalue .= 'key read       : '.$this->keyValueDescription($this->tbr).PHP_EOL;
        $keyvalue .= 'key write      : '.$this->keyValueDescription($this->tb).PHP_EOL;
        $s = 'conditions     : '.$q->toString().','.PHP_EOL;
        $s .= '                 reject = '.$q->getReject();
        $s .= ', limit = '.$q->getLimit();
        $s .= ', stopAtLimit = '.(int) $q->isStopAtLimit();
        $s .= ', direction = '.(int) $q->getDirection().PHP_EOL;
        return $keyvalue.$s;
    }

    /**
     * Get the record count.
     *
     * @return int
     */
    public function count()
    {
        return $this->get(false)->size();
    }

    /**
     * Calculate the total value of the specified column.
     *
     * @param string $column
     * @return double
     */
    public function sum($column)
    {
        return AggregateFunction::sum($this->get(false), $column);
    }

    /**
     * Find the minimum value of the specified column.
     *
     * @param string $column
     * @return double
     */
    public function min($column)
    {
        return AggregateFunction::min($this->get(false), $column);
    }
    
    /**
     * Find the maximum value of the specified column.
     *
     * @param string $column
     * @return double
     */
    public function max($column)
    {
        return AggregateFunction::max($this->get(false), $column);
    }

     /**
     * Calculate the average value of the specified column.
     *
     * @param string $column
     * @return double
     */
    public function avg($column)
    {
        return AggregateFunction::avg($this->get(false), $column);
    }
    
    /**
     * The alias name of the avg function .
     * @param type $column
     * @return type
     */
    public function average($column)
    {
        return AggregateFunction::avg($this->get(false), $column);
    }
}
