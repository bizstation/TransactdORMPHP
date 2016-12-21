<?php

namespace Transactd;

require_once(__DIR__ .'/Require.php');

use BizStation\Transactd\Transactd;
use BizStation\Transactd\PooledDbManager;
use BizStation\Transactd\ConnectParams;
use \Transactd\Model;
use Transactd\IOException;

/**
 * @method \BizStation\Transactd\Database master()
 * @method \BizStation\Transactd\Database slave()
 * @method QueryExecuter queryExecuter(string $tableName, string $className = 'stdClass')
 * @method \BizStation\Transactd\Table table(string $tableName)
 * @method void beginTrn(int $bias = null)
 * @method void endTrn()
 * @method void abortTrn()
 * @method void beginTransaction(int $bias = null)
 * @method void commit()
 * @method void rollBack()
 * @method void beginSnapshot(int $bias = Transactd::CONSISTENT_READ)
 * @method void endSnapshot()

 */
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

    private function doConnect($urim, $uris, $otherConnection = false)
    {
        if ($urim != '') {
            PooledDbManager::setMaxConnections(PooledDbManager::maxConnections() + 1);
            $uri = new ConnectParams($urim);
            $this->urim = $uri;
            $this->pdm = new PooledDbManager();
            $this->pdm->c_use($uri);
            if (!$otherConnection && $urim === $uris) {
                $this->pds = $this->pdm;
            }
        }
        if ($uris != '' && ($urim !== $uris || $otherConnection)) {
            PooledDbManager::setMaxConnections(PooledDbManager::maxConnections() + 1);
            $uri = new ConnectParams($uris);
            $this->uris = $uri;
            $this->pds = new PooledDbManager();
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
    /**
     * Create a cachedQueryExecuter
     * 
     * @param string $tableName
     * @param string $className
     * @return \Transactd\CachedQueryExecuter
     */
    public function cachedQueryExecuter($tableName, $className = 'stdClass')
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

    protected function _beginSnapshot($bias = Transactd::CONSISTENT_READ)
    {
        return $this->pds->beginSnapshot($bias);
    }

    protected function _endSnapshot()
    {
        return $this->pds->endSnapshot();
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
    /**
     *
     * @param string $urim Uri for master
     * @param string $uris Uri for slave
     * @param string $name Connection name
     * @param bool $otherConnection Force creates other connection if $urim === $uris. 
     * @return self
     */
    public static function connect($urim, $uris, $name = 'default', $otherConnection = false)
    {
        if (!array_key_exists($name, self::$dbmArray)) {
            if (!array_key_exists($name, self::$dbmArray)) {
                $dbm = new self();
                $dbm->name = $name;
                $dbm->doConnect($urim, $uris, $otherConnection);
                self::$dbmArray[$name] = $dbm;
                return $dbm;
            }
        }
    }
    
    /**
     *
     * @param string $name Connection name
     * @return self
     * @throws IOException
     */
    public static function connection($name = 'default')
    {
        if (!array_key_exists($name, self::$dbmArray)) {
            throw new IOException('No connection', 1);
        }
        return self::$dbmArray[$name];
    }
    
    /**
     * Release all connections force.
     * 
     * @return void
     */
    public static function reset()
    {
        if (!(bool)self::$dbmArray) {
            return;
        }
        Model::clearTableCache();
        foreach(self::$dbmArray as $dbm) {
            if ($dbm->pds !== null) {
                $dbm->pds->unUse();
            }
            if ($dbm->pdm !== null) {
                $dbm->pdm->unUse();
            }   
        }
        reset(self::$dbmArray);
        $dbm = current(self::$dbmArray);
        self::$dbmArray = array();
        if ($dbm->pdm !== null) {
            $dbm->pdm->reset(3);
        }
    }

    /**
     * Implemets of __callStatic.
     * When the name is object method , redirected to the default connection object.
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     * @throws \BadMethodCallException
     */
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
