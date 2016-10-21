<?php

namespace Transactd;

use BizStation\Transactd\transactd;
use BizStation\Transactd\activeTable;
use BizStation\Transactd\sortFields;

transactd::setFieldValueMode(transactd::FIELD_VALUE_MODE_VALUE);

class QueryExecuter
{
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

    public function setEvent($name, $func)
    {
        if ($name === 'creating') {
            $this->creating = $func;
        }
        if ($name === 'created') {
            $this->created = $func;
        }
        if ($name === 'updating') {
            $this->updating = $func;
        }
        if ($name === 'updated') {
            $this->updated = $func;
        }
        if ($name === 'saving') {
            $this->saving = $func;
        }
        if ($name === 'saved') {
            $this->saved = $func;
        }
        if ($name === 'deleting') {
            $this->deleting = $func;
        }
        if ($name === 'deleted') {
            $this->deleted = $func;
        }
    }

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
    }

    private function isInstanceOf($obj, $name)
    {
        $cname = get_class($obj);
        if ($cname !== false && strpos($cname, $name) !== false) {
            return true;
        }
        return false;
    }

    protected function setFetchClass($rs)
    {
        $rs->fetchClass = $this->tb->fetchClass;
        $rs->fetchMode = transactd::FETCH_USR_CLASS;
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

    protected function primaryKeyFields()
    {
        return $this->keyFieldCache[$this->primaryKey];
    }
    public function primarykey()
    {
        return $this->primaryKey;
    }
    public function primaryKeyFieldNames()
    {
        return $this->keyFieldNameCache[$this->primaryKey];
    }

    public function keyFieldNames($index)
    {
        return $this->keyFieldNameCache[$index];
    }

    public function isSeekHasMany($index, $segments)
    {
        if ($segments < count($this->keyFieldCache[$index])) {
            return true;
        }
        $kd = $this->tb->tableDef()->keyDef($index);

        return $kd->segment(0)->flags->bit0 === 1;
    }

    public function table()
    {
        return $this->tb;
    }

    public function activeTable()
    {
        return $this->at;
    }

    public function getWritableTable()
    {
        return $this->tb;
    }

    public function getReadbleTable()
    {
        return $this->tbr;
    }

    public function __construct($tableName, $dbm, $dbs, $className)
    {
        $mode_m = transactd::TD_OPEN_NORMAL;
        $mode_s = transactd::TD_OPEN_READONLY;
        if ($dbm === null) {
            $mode_m = transactd::TD_OPEN_READONLY;
            $dbm = $dbs;
        } elseif ($dbs === null) {
            $mode_s = transactd::TD_OPEN_NORMAL;
            $dbs = $dbm;
        }
        $this->dbm = $dbm;
        $this->at = new activeTable($dbs, $tableName, $mode_s);
        if ($this->at === null) {
            throw new IOException($tableName.' active table open stat='.$dbs->stat());
        }

        $this->tb = ($mode_m === $mode_s) ? $this->at->table() : $dbm->openTable($tableName, $mode_m);
        if ($this->tb === null) {
            throw new IOException($tableName.' table open stat='.$dbm->stat());
        }
        $this->tb->fetchClass = $className;
        $this->tb->fetchMode = transactd::FETCH_USR_CLASS;
        $this->tbr = $this->at->table();
        $this->setFetchClass($this->tbr);

        $this->cacheKeyFields();
        $this->q = new QueryAdapter();
        $this->obj = new $className();
        $this->spcoll = method_exists($this->obj, 'newCollection');
    }

    protected function copyKeyValues($tb, $src)
    {
        $td = $tb->tableDef();
        $kd = $td->keyDef($tb->keyNum());
        for ($i = 0; $i < $kd->segmentCount; ++$i) {
            $index = $kd->segment($i)->fieldNum;
            $tb->setFV($index, $src->getFVstr($index));
        }
    }

    /* set unique values to field */
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

    public function setTimeStampMode($v)
    {
        if ($this->timeStampMode !== $v) {
            if ($v === false) {
                $this->tb->setTimestampMode(transactd::TIMESTAMP_VALUE_CONTROL);
            } else {
                $this->tb->setTimestampMode(transactd::TIMESTAMP_ALWAYS);
            }
            if ($this->tb->stat() === 0) {
                $this->timeStampMode = $v;
            }
        }
    }

    public function setAliases($aliases)
    {
        foreach ($aliases as $key => $value) {
            $this->at->alias($key, $value);
            $this->tbr->setAlias($key, $value);
            $this->tb->setAlias($key, $value);
        }
    }

    public function index($index)
    {
        $this->at->index($index);
        $this->tb->setKeyNum($index);
        return $this;
    }

    public function indexToPrimaryKey()
    {
        return $this->index($this->primaryKey);
    }

    public function keyValue($v1, $v2 = null, $v3 = null, $v4 = null, $v5 = null, $v6 = null, $v7 = null, $v8 = null)
    {
        $this->at->keyValue($v1, $v2, $v3, $v4, $v5, $v6, $v7, $v8);
        return $this;
    }

    public function updateConflictCheck($v)
    {
        if ($this->tb->setUpdateConflictCheck($v) === false) {
            throw new \RuntimeException('This table has no updatable timestamp field.');
        }
        return $this;
    }

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

    public function read($toArray = true)
    {
        $this->get($toArray);
    }

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
            if ($with === true /*|| $this->spcoll=== true*/) {
                $toArray = true;
            }
            if ($toArray === true) {
                $rs = $rs->toArray();
                if ($with === true) {
                    $tmp = $this->tb->fetchClass;
                    $tmp::resolvRelations($rs, $this->with);
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

    public function outerJoin($q, $keyNmaes)
    {
        return $this->addJoin($q, $keyNmaes, true);
    }

    public function join($q, $keyNmaes)
    {
        return $this->addJoin($q, $keyNmaes, false);
    }

    public function groupBy($gq)
    {
        if ($this->gq === null &&
            $this->isInstanceOf($gq, 'BizStation\Transactd\groupQuery') === true) {
            $this->gq = $gq;
            array_push($this->oprOrder, 'groupBy');
        } else {
            throw new \InvalidArgumentException('arg1 is not class of BizStation\transactd\groupQuery.');
        }
        return $this;
    }

    /* repeatable */
    public function orderBy($name, $asc)
    {
        if ($this->sort === null) {
            $this->sort = new sortFields();
            array_push($this->oprOrder, 'orderBy');
        }
        $this->sort->add($name, $asc);
        return $this;
    }

    public function matchBy($rq)
    {
        $this->rq = $rq;
        array_push($this->oprOrder, 'matchBy');
        return $this;
    }

    public function recordset()
    {
        return $this->get(false);
    }

    public function union($rs)
    {
        if ($this->isInstanceOf($rs, 'BizStation\Transactd\Recordset') === true) {
            $this->union = $rs;
            return $this;
        }
        throw new \InvalidArgumentException('arg1 is not class of BizStation\transactd\recordset.');
    }

    public function removeNullRecord()
    {
        $this->removeInvalidRecord = true;
        array_push($this->oprOrder, 'removeNullRecord');
        return $this;
    }

    public function all($toArray = true)
    {
        return $this->index(0)->keyValue(0)->get($toArray);
    }

    public function find($id, $throwException = false)
    {
        $tb = $this->tbr;
        /*$tb->clearBuffer();
        $this->setKeyValues($id, $tb);
        $tb->seek();*/
        $stat = $tb->seekKeyValue($id);
        if ($stat === 0) {
            return $tb->fields();
        } elseif ($throwException === true && $stat === transactd::STATUS_NOT_FOUND_TI) {
            throw new ModelNotFoundException();
        }
        return null;
    }

    public function findMany($ids, $throwException = false)
    {
    }

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
        } elseif ($throwException === true && $tb->stat() === transactd::STATUS_EOF) {
            throw new ModelNotFoundException();
        }
        $this->reset();
        return $ret;
    }

    public function cursor()
    {
        $tb = $this->tbr;
        $tb->setQuery($this->q->query());
        $tb->find();
        //if (version_compare(phpversion(), '5.5.0', '<'))
        //	return new tableIterator($tb);
        while ($tb->stat() === 0) {
            yield $tb->fields();
            $tb->findNext();
        }
        $this->reset();
    }

    public function prepareCreate($attributes)
    {
        $tb = $this->getWritableTable();
        $tb->clearBuffer();
        if ($tb->fetchMode === transactd::FETCH_USR_CLASS) {
            $obj = new $tb->fetchClass();
            $attributes = $obj->filterCreateAttribute($attributes);
        }
        foreach ($attributes as $fd => $value) {
            $tb->setFV($fd, $value);
        }
        return $tb;
    }

    public function getCreatedObject($tb)
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
     Object is returned as parameters. It does not reflect such as Timestamp.
     */
    public function create($attributes,  $nosave = false)
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

    public function firstOrCreate($attributes)
    {
        $tb = $this->prepareCreate($attributes);
        $tb->seek();
        if ($tb->stat() === STATUS_NOT_FOUND_TI) {
            $tmp = $this->creating;
            if ($tmp !== null && $tmp::creating($tb->fields()) === false) {
                return null;
            }
            $tb->insert();
            if ($tb->stat() !== 0) {
                throw new IOException($tb->statMsg(), $tb->stat());
            }

            return $this->getCreatedObject($tb);
        }
        if ($tb->stat() !== 0) {
            throw new IOException($tb->statMsg(), $tb->stat());
        }
        return $tb->fields();
    }

    public function firstOrNew($attributes)
    {
        $tb = $this->prepareCreate($attributes);
        $tb->seek();
        if (($tb->stat() === 0) || $tb->stat() === STATUS_NOT_FOUND_TI) {
            return $tb->fields();
        }
        throw new IOException($tb->statMsg(), $tb->stat());
    }

    protected function cancelTrnByEvent($eventName)
    {
        //$this->dbm->abortTrn();
        throw new ModelUserCancelException($eventName);
    }

    /**

     @return updated object array or false. Caceled by event then return false .
     */
    private function doUpdateWhere($tb, $delete, $attributes = null)
    {
        try {
            $array = array();
            //$this->dbm->beginTrn();
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
                for ($i = 0; $i < $size; ++$i) {
                    $tb->moveBookmarks($i);
                    if ($delete === true) {
                        $tmp = $this->deleting;
                        if ($tmp !== null && $tmp::deleting($tb->fields()) === false) {
                            $this->cancelTrnByEvent('deleting');
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
                            $this->cancelTrnByEvent('updating');
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
            //$this->dbm->endTrn();
            $this->reset();
            return $array;
        } catch (\Exception $e) {
            //$this->dbm->abortTrn();
            $this->reset();
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

    protected function doUpdate($attributes)
    {
        $tb = $this->getWritableTable();
        $this->copyKeyValues($tb, $this->tbr);
        if ($this->q->isWhereDefined() === false) {
            return $this->doUpdateOne($tb, $attributes);
        }
        return $this->doUpdateWhere($tb, false, $attributes);
    }

    protected function doDelete()
    {
        $tb = $this->getWritableTable();
        $this->copyKeyValues($tb, $this->tbr);
        if ($this->q->isWhereDefined() === false) {
            return $this->doDeleteOne($tb);
        }
        return $this->doUpdateWhere($tb, true);
    }

    public function update($attributes)
    {
        return count($this->doUpdate($attributes));
    }

    public function delete()
    {
        return count($this->doDelete());
    }

    public function __call($name, $arguments)
    {
        if (method_exists('BizStation\Transactd\Recordset', $name)) {
            $reflectionMethod = new \ReflectionMethod('BizStation\Transactd\Recordset', $name);
            return $reflectionMethod->invokeArgs(get(), $arguments);
        }
        throw new \BadMethodCallException($name);
    }

    public function when($jadge, $func)
    {
        if ($jadge == true) {
            $func($this);
        }
        return $this;
    }
    public function where($a, $b = null, $c = null)
    {
        $this->q->where($a, $b, $c);
        return $this;
    }

    public function orWhere($a, $b = null, $c = null)
    {
        $this->q->orWhere($a, $b, $c);
        return $this;
    }

    public function whereNull($fdname)
    {
        $this->q->whereNull($fdname);
        return $this;
    }

    public function orNull($fdname)
    {
        $this->q->orNull($fdname);
        return $this;
    }

    public function whereNotNull($fdname)
    {
        $this->q->whereNotNull($fdname);
        return $this;
    }

    public function orNotNull($fdname)
    {
        $this->q->orNotNull($fdname);
        return $this;
    }

    public function whereInKey($values, $segments = null)
    {
        $this->q->whereInKey($this->tbr, $values, $segments);
        return $this;
    }

    public function whereIn($fdName, $values)
    {
        $this->q->whereIn($fdName, $values);
        return $this;
    }

    public function whereNotIn($fdName, $values)
    {
        $this->q->whereNotIn($fdName, $values);
        return $this;
    }

    public function whereBetween($fdName, $valuePair)
    {
        $this->q->whereBetween($fdName, $valuePair);
        return $this;
    }

    public function whereNotBetween($fdName, $valuePair)
    {
        $this->q->whereNotBetween($fdName, $valuePair);
        return $this;
    }

    public function whereColumn($a, $b = null, $c = null)
    {
        $this->q->whereColumn($a, $b, $c);
        return $this;
    }

    public function orColumn($a, $b = null, $c = null)
    {
        $this->q->orColumn($a, $b, $c);
        return $this;
    }

    public function select($a, $b = null, $c = null, $d = null, $e = null, $f = null, $g = null, $h = null)
    {
        $this->q->select($a, $b, $c, $d, $e, $f, $g, $h);
        return $this;
    }

    public function addSelect($a)
    {
        $this->q->addSelect($a);
        return $this;
    }

    public function reject($v)
    {
        $this->q->reject($v);
        return $this;
    }

    public function skip($n)
    {
        $this->q->skip($n);
        return $this;
    }

    public function take($n)
    {
        $this->q->take($n);
        return $this;
    }

    public function limit($n)
    {
        $this->q->take($n);
        return $this;
    }//alias to take

    public function queryString()
    {
        return $this->q->query()->toString();
    }

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
        $s .= ', stopAtLimit = '.(int) $q->isStopAtLimit().PHP_EOL;
        return $keyvalue.$s;
    }

    public function count()
    {
        return $this->get(false)->size();
    }

    public function sum($column)
    {
        return AggregateFunction::sum($this->get(false), $column);
    }

    public function min($column)
    {
        return AggregateFunction::min($this->get(false), $column);
    }

    public function max($column)
    {
        return AggregateFunction::max($this->get(false), $column);
    }

    public function avg($column)
    {
        return AggregateFunction::avg($this->get(false), $column);
    }

    public function average($column)
    {
        return AggregateFunction::avg($this->get(false), $column);
    }
}

class CachedQueryExecuter extends QueryExecuter
{
    public static $returnClone = false;
    private $findCache = array();
    private $caching = true;

    public static function getCacheKeyByObj($obj, $fields)
    {
        $count = count($fields);
        if ($count == 1) {
            return (string) $obj->{$fields[0]};
        } else {
            $key = '';
            for ($i = 0; $i < $count; ++$i) {
                $key .= $obj->{$fields[$i]}.'$\t';
            }
            return $key;
        }
    }

    private function setPrimaryKeyValueByObj($obj)
    {
        $fields = $this->primaryKeyFieldNames;
        for ($i = 0; $i < count($fields); ++$i) {
            $name = $fields[$i];
            $this->tb->setFV($name, $obj->{$name});
        }
    }

    private function getClone($obj)
    {
        if (self::$returnClone === true) {
            return clone $obj;
        }
        return $obj;
    }

    /* get hash key of uniqe values */
    private function cacheKey($id)
    {
        if (is_array($id)) {
            if (count($id) === 1) {
                return $id[0];
            }
            $key = '';
            foreach ($id as $value) {
                $key .= $value.'$\t';
            }
            return $key;
        }
        return $id;
    }

    public function cacheKeyByObj($obj)
    {
        return self::getCacheKeyByObj($obj, $this->primaryKeyFieldNames);
    }

    private function updateCache($obj, $clear = false)
    {
        if ($this->caching === false) {
            return $obj;
        }
        if ($obj === null) {
            return $obj;
        }
        $key = $this->cacheKeyByObj($obj);
        if ($clear === true) {
            if (array_key_exists($key, $this->findCache) === true) {
                unset($this->findCache[$key]);
            }
        } else {
            $this->findCache[$key] = $obj;
        }
        return $obj;
    }

    /** push obj to cache

     @return key of cache.
     */
    public function pushToCache($obj)
    {
        $key = $this->cacheKeyByObj($obj);
        if ($this->caching === false) {
            return $key;
        }
        $this->findCache[$key] = $obj;
        return $key;
    }

    public function clear()
    {
        $this->findCache = array();
    }

    public function find($id, $throwException = false)
    {
        Model::$resolvByCache = false;
        if ($this->caching === true) {
            $key = $this->cacheKey($id);
            if (array_key_exists($key, $this->findCache) === true) {
                Model::$resolvByCache = true;

                return $this->getClone($this->findCache[$key]);
            }
        }
        $ret = parent::find($id, $throwException);
        if ($ret !== null) {
            return $this->getClone($this->updateCache($ret));
        }
        return $ret;
    }

    public function findMany($ids, $throwException = false)
    {
        $ret = parent::findMany($ids, $throwException);
        return $ret;
    }

    public function getCache($key)
    {
        if (array_key_exists($key, $this->findCache) === true) {
            return $this->getClone($this->findCache[$key]);
        }
        return false;
    }

    public function create($attributes, $nosave = false)
    {
        return $this->updateCache(parent::create($attributes, $nosave));
    }

    public function firstOrCreate($attributes)
    {
        return $this->updateCache(parent::firstOrCreate($attributes));
    }

    public function firstOrNew($attributes)
    {
        return $this->updateCache(parent::firstOrNew($attributes));
    }

    public function refresh($obj)
    {
        $tb = $this->getWritableTable();
        $tb->readByObj($obj);
        if ($tb->stat() !== 0) {
            throw new IOException($tb->statMsg(), $tb->stat());
        }
        $this->updateCache($obj);
        return $tb->stat() === 0;
    }

    public function deleteByObj($obj)
    {
        $tmp = $this->deleting;
        if ($tmp !== null && $tmp::deleting($obj) === false) {
            return false;
        }
        $tb = $this->getWritableTable();
        $tb->deleteByObj($obj);
        $stat = $tb->stat();
        if ($stat !== 0 && $stat !== transactd::STATUS_NOT_FOUND_TI) {
            throw new IOException($tb->statMsg(), $tb->stat());
        }
        $tmp = $this->deleted;
        if ($tmp !== null) {
            $tmp::deleted($obj);
        }
        $this->updateCache($obj, true);
        return true;
    }

    public function save($obj)
    {
        //ToDo insert and update event
        $tmp = $this->saving;
        if ($tmp !== null && $tmp::saving($obj) === false) {
            return false;
        }
        $tb = $this->getWritableTable();
        $tb->saveByObj($obj, true);
        if ($tb->stat() !== 0) {
            throw new IOException($tb->statMsg(), $tb->stat());
        }
        $tmp = $this->saving;
        if ($tmp !== null) {
            $tmp::saved($obj);
        }
        $this->updateCache($obj);
        return $tb->stat() === 0;
    }
    /**
     @return count of array or false
     */
    public function update($attributes)
    {
        $array = parent::doUpdate($attributes);
        if ($array === false) {
            return false;
        }
        foreach ($array as $obj) {
            $this->updateCache($obj);
        }
        return count($array);
    }

    public function delete()
    {
        $array = parent::doDelete();
        if ($array === false) {
            return false;
        }
        foreach ($array as $obj) {
            $this->updateCache($obj, true);
        }
        return count($array);
    }

    /**
     No error is thrown if id is not found, IOException is thrown if other errors.
     */
    private function doDestroy($ids)
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
            $this->dbm->beginTrn();
            for ($i = 0; $i < count($idsa); ++$i) {
                $this->setKeyValues($idsa[$i], $tb);
                $tmp = $this->deleting;
                if ($tmp !== null && $tmp::deleting($tb->fields()) === false) {
                    return $this->cancelTrnByEvent();
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
                } elseif ($tb->stat() !== transactd::STATUS_NOT_FOUND_TI) {
                    throw new IOException($tb->statMsg(), $tb->stat());
                }
            }
            $this->dbm->endTrn();

            return $qbjArray;
        } catch (\Exception $e) {
            $this->dbm->abortTrn();
            $this->reset();
            throw $e;
        }
    }

    public function destroy($ids)
    {
        $array = $this->doDestroy($ids);
        foreach ($array as $obj) {
            $this->updateCache($obj, true);
        }
        return count($array);
    }

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

    public function with($func)
    {
        array_push($this->with, $func);
        return $this;
    }

    public function __call($name, $arguments)
    {
        if (method_exists($this->tb->fetchClass, 'scope'.$name)) {
            $obj = $this->obj;
            $this;
            $reflectionMethod = new \ReflectionMethod($this->tb->fetchClass, 'scope'.$name);
            array_unshift($arguments, $this);
            $reflectionMethod->invokeArgs($obj, $arguments);
            return $this;
        }
        return parent::__call($name, $arguments);
    }
}
