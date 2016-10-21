<?php

namespace Transactd;

require_once(__DIR__ .'/Require.php');

use BizStation\Transactd\pooledDbManager;
use BizStation\Transactd\connectParams;
use BizStation\Transactd\transactd;

class DatabaseManager
{
    public static $tableCash = false;
    private static $dbmArray = array();
    private $pdm = null;
    private $pds = null;

    private static function throwError($db)
    {
        throw new IOException($db->statMsg(), $db->stat());
    }

    private function doConnect($urim, $uris)
    {
        if ($urim != '') {
            pooledDbManager::setMaxConnections(pooledDbManager::maxConnections() + 1);
            $uri = new connectParams($urim);
            $this->urim = $uri;
            $this->pdm = new pooledDbManager();
            $this->pdm->c_use($uri);
        }
        if ($uris != '') {
            pooledDbManager::setMaxConnections(pooledDbManager::maxConnections() + 1);
            $uri = new connectParams($uris);
            $this->uris = $uri;
            $this->pds = new pooledDbManager();
            $this->pds->c_use($uri);
        }
    }

    protected function _master()
    {
        return $this->pdm->db();
    }

    protected function _slave()
    {
        if ($this->pds !== null) {
            return $this->pds->db();
        }

        return null;
    }

    protected function _cachedQueryExecuter($tableName, $className = 'stdClass')
    {
        if (self::$tableCash === true) {
            return new CachedQueryExecuter($tableName, $this->pdm, $this->pds, $className);
        }

        return new CachedQueryExecuter($tableName, $this->_master(), $this->_slave(), $className);
    }

    protected function _table($tableName)
    {
        if (self::$tableCash === true) {
            return $this->pdm->table($tableName);
        }

        return $this->_master()->openTable($tableName);
    }

    protected function _queryExecuter($tableName, $className = 'stdClass')
    {
        if (self::$tableCash === true) {
            return new QueryExecuter($tableName, $this->pdm, $this->pds, $className);
        }

        return new QueryExecuter($tableName, $this->_master(), $this->_slave(), $className);
    }

    protected function _beginTrn($bias = null)
    {
        $this->pdm->beginTrn($bias);
    }

    protected function _endTrn()
    {
        $this->pdm->endTrn();
    }

    protected function _abortTrn()
    {
        $this->pdm->abortTrn();
        Model::clearTableCache();
    }

    protected function _beginSnapshot($bias = transactd::CONSISTENT_READ)
    {
        return $this->pds->beginSnapshot($bias);
    }

    protected function _endSnapshot()
    {
        $this->pds->endSnapshot();
    }

    protected function _beginTransaction($bias = null)
    {
        $this->beginTrn($bias);
    }

    protected function _commit()
    {
        $this->endTrn();
    }

    protected function _rollBack()
    {
        $this->abortTrn();
    }

    public function __call($name, $arguments)
    {
        if (method_exists('Transactd\DatabaseManager', '_'.$name)) {
            $reflectionMethod = new \ReflectionMethod($this, '_'.$name);
            $reflectionMethod->setAccessible(true);

            return $reflectionMethod->invokeArgs($this, $arguments);
        }
        throw new \BadMethodCallException($name);
    }

    public static function connect($urim, $uris, $name = 'default')
    {
        if (!array_key_exists($name, self::$dbmArray)) {
            if (!array_key_exists($name, self::$dbmArray)) {
                $dbm = new self();
                $dbm->name = $name;
                $dbm->doConnect($urim, $uris);
                self::$dbmArray[$name] = $dbm;

                return $dbm;
            }
        }
    }

    public static function connection($name = 'default')
    {
        if (!array_key_exists($name, self::$dbmArray)) {
            throw new IOException('No connection', 1);
        }

        return self::$dbmArray[$name];
    }

    public static function __callStatic($name, $arguments)
    {
        if (method_exists('Transactd\DatabaseManager', '_'.$name)) {
            $obj = self::connection('default');
            $reflectionMethod = new \ReflectionMethod('Transactd\DatabaseManager', '_'.$name);
            $reflectionMethod->setAccessible(true);
            return $reflectionMethod->invokeArgs($obj, $arguments);
        }
        throw new \BadMethodCallException($name);
    }
}
