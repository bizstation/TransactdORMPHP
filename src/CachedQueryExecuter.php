<?php

namespace Transactd;

use Transactd\QueryExecuter;
use Transactd\IOException;

/**
 *  QueryExecuter with cache.
 *
 *  Return from the cache if exists in the cache before reading.
 */
class CachedQueryExecuter extends QueryExecuter
{
    private $objectCache = array();

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
     * Update the cache.
     * 
     * @param object $obj
     * @param bool $clear
     * @return object
     */
    public function updateCache($obj, $clear = false)
    {
        if ($obj === null) {
            return $obj;
        }
        $key = $this->getUniqueKey($obj);
        if ($clear === true) {
            if (array_key_exists($key, $this->objectCache) === true) {
                unset($this->objectCache[$key]);
            }
        } else {
            $this->objectCache[$key] = $obj;
        }
        return $obj;
    }

    /**
     *
     * @param object $obj
     * @return string Return a cache key.
     */
    public function addToCache($obj)
    {
        if ($obj !== null) {
            $key = $this->getUniqueKey($obj);
            $this->objectCache[$key] = $obj;
            return;
        }
    }
    /**
     * Clear all cache object of this table.
     */
    public function clear()
    {
        $this->objectCache = array();
    }
    /**
     *
     * @param mixed $id Key values for find.
     * @param bool $throwException
     * @return object|null
     */
    public function find($id, $throwException = false)
    {
        Model::$resolvByCache = false;
        $key = $this->cacheKey($id);
        if (array_key_exists($key, $this->objectCache) === true) {
            Model::$resolvByCache = true;
            return $this->objectCache[$key];
        }
        $ret = parent::find($id, $throwException);
        if ($ret !== null) {
            return $this->updateCache($ret);
        }
        return $ret;
    }
    /**
     *
     * @param mixed $id Key values for find.
     * @return object
     * @throws ModelNotFoundException
     */
    public function findOrFail($id)
    {
        return $this->find($id, true);
    }

    /**
     *
     * @param array $ids
     * @param bool $throwException
     * @return array|\Transactd\Collection|\BizStation\Transactd\Recordset
     */
    public function findMany($ids, $throwException = false)
    {
        $ret = array();
        foreach ($ids as $id) {
            $key = $this->cacheKey($id);
            if (array_key_exists($key, $this->objectCache) === true) {
                Model::$resolvByCache = true;
                $ret[] = $this->objectCache[$key];
            }
        }
        if (count($ret) === count($ids)) {
            return  $this->arrayToCollection($ret);
        }
 
        $ret = parent::findMany($ids, $throwException);
        foreach ($ret as $obj) {
            $key = $this->getUniqueKey($obj);
            $this->objectCache[$key] = $obj;
        }
        return $ret;
    }
    /**
     * Find a first record by the current conditions.
     *
     * @param bool $throwException Whether throw an exception that could not be found.
     * @return object
     */
    public function first($throwException = false)
    {
        return $this->updateCache(parent::first($throwException));
    }
    /**
     *
     * @return \Transactd\Model
     * @throws ModelNotFoundException
     */
    public function firstOrFail()
    {
        return $this->first(true);
    }
    /**
     *
     * @param array $attributes
     * @return object
     */
    public function firstOrCreate(array $attributes)
    {
        return $this->updateCache(parent::firstOrCreate($attributes));
    }
    /**
     *
     * @param array $attributes
     * @return object
     */
    public function firstOrNew(array $attributes)
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
     * @param array $attributes
     * @param bool $nosave
     * @return object
     */
    public function create(array $attributes, $nosave = false)
    {
        return $this->updateCache(parent::create($attributes, $nosave));
    }
    /**
     *
     * @param object $obj
     * @return bool
     * @throws IOException
     */
    public function deleteObject($obj)
    {
        if (parent::deleteObject($obj) === true) {
            $this->updateCache($obj, true);
            return true;
        }
        return false;
    }
    /**
     *
     * @param object $obj
     * @param bool $forceInsert
     * @return bool
     * @throws IOException
     */
    public function save($obj, $forceInsert = false)
    {
        if (parent::save($obj, $forceInsert) === true) {
            $this->updateCache($obj);
            return true;
        }
        return false;
    }

    /**
     *
     * @param array $attributes
     * @return int|false Return count of array or false.
     */
    public function update(array $attributes)
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
    
   /*
    *
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
     * Delete records by current index's values.
     * NOTE: The current record is changed by this operation.  
     * @param array $values
     * @param function $func Igonered in this function.
     * @return int Number of deleted object.
     */
    public function deleteMany($values, $func=null) 
    {
        return parent::deleteMany($values, function($obj){
            $this->updateCache($obj, true);
        });
    }
}
