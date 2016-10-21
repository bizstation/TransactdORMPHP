<?php

namespace Transactd;

use Transactd\QueryExecuter;

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
