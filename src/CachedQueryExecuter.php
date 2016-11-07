<?php

namespace Transactd;

use BizStation\Transactd\Transactd;
use Transactd\QueryExecuter;

/**
 *  QueryExecuter with cache.
 * 
 *  Return from the cache if exists in the cache before reading.
 */
class CachedQueryExecuter extends QueryExecuter
{
    public static $returnClone = false;
    private $findCache = array();
    private $caching = true;
    /**
     * Get a key string from specified field values of the object.
     * 
     * @param object $obj
     * @param array $fields
     * @return string
     */
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
    /**
     * Get a key string from primary key field value of the object.
     * 
     * @param object $obj
     * @return string
     */
    public function cacheKeyByObj($obj)
    {
        return self::getCacheKeyByObj($obj, $this->primaryKeyFieldNames);
    }
    /**
     * 
     * @param object $obj
     * @param bool $clear
     * @return object
     */
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

    /**
     * 
     * @param object $obj
     * @return string Return a cache key.
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
    /**
     * Clear all cache object of this table. 
     */
    public function clear()
    {
        $this->findCache = array();
    }
    /**
     * 
     * @param mixed $id
     * @param bool $throwException
     * @return object|null 
     */
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
    /**
     * 
     * @param array $ids
     * @param bool $throwException
     * @return array|\Transactd\Collection|\BizStation\Transactd\Recordset
     */
    public function findMany($ids, $throwException = false)
    {
        $ret = parent::findMany($ids, $throwException);
        return $ret;
    }
    /**
     * 
     * @param string $key
     * @return object|false
     */
    public function getCache($key)
    {
        if (array_key_exists($key, $this->findCache) === true) {
            return $this->getClone($this->findCache[$key]);
        }
        return false;
    }
    /**
     * 
     * @param array $attributes
     * @param bool $nosave
     * @return object
     */
    public function create($attributes, $nosave = false)
    {
        return $this->updateCache(parent::create($attributes, $nosave));
    }
    /**
     * 
     * @param array $attributes
     * @return object
     */
    public function firstOrCreate($attributes)
    {
        return $this->updateCache(parent::firstOrCreate($attributes));
    }
    /**
     * 
     * @param array $attributes
     * @return object
     */
    public function firstOrNew($attributes)
    {
        return $this->updateCache(parent::firstOrNew($attributes));
    }
    /**
     * Re-read from the database.
     * 
     * @param object $obj
     * @return object
     * @throws IOException
     */
    public function refresh($obj)
    {
        $tb = $this->getWritableTable();
        $tb->readByObject($obj);
        if ($tb->stat() !== 0) {
            throw new IOException($tb->statMsg(), $tb->stat());
        }
        $this->updateCache($obj);
        return $tb->stat() === 0;
    }
    /**
     * 
     * @param object $obj
     * @return bool
     * @throws IOException
     */
    public function deleteByObj($obj)
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
        $this->updateCache($obj, true);
        return true;
    }
    /**
     * 
     * @param object $obj
     * @return boolean
     * @throws IOException
     */
    public function save($obj)
    {
        //ToDo insert and update event
        $tmp = $this->saving;
        if ($tmp !== null && $tmp::saving($obj) === false) {
            return false;
        }
        $tb = $this->getWritableTable();
        $tb->saveByObject($obj);
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
     * 
     * @param array $attributes
     * @return int|false Return count of array or false.
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
    /**
     * 
     * @return int|false Return count of array or false.
     */
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
                } elseif ($tb->stat() !== Transactd::STATUS_NOT_FOUND_TI) {
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
    /**
     * 
     * @param int|string|(int|string)[] $ids
     * @return int
     */
    public function destroy($ids)
    {
        $array = $this->doDestroy($ids);
        foreach ($array as $obj) {
            $this->updateCache($obj, true);
        }
        return count($array);
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
     * 
     * @param string $func
     * @return \Transactd\CachedQueryExecuter
     */
    public function with($func)
    {
        array_push($this->with, $func);
        return $this;
    }
   
    public function __call($name, $arguments)
    {
        if (method_exists($this->tb->fetchClass, 'scope'.$name)) {
            $obj = $this->obj;
            $reflectionMethod = new \ReflectionMethod($this->tb->fetchClass, 'scope'.$name);
            array_unshift($arguments, $this);
            $reflectionMethod->invokeArgs($obj, $arguments);
            return $this;
        }
        return parent::__call($name, $arguments);
    }
}
