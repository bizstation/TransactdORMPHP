<?php

namespace Transactd\boot\Laravel;

require_once(__DIR__ . "/../../Require.php");

use Illuminate\Support\ServiceProvider;
use BizStation\Transactd\database;
use Transactd\DatabaseManager;

class TransactdLaravelServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $mode = env("TRANSACTD_COPATIBLE_MODE");
        if ($mode !== '') {
            database::setCompatibleMode((int)$mode);
        }
        $tableCash = env('TRANSACTD_TABLE_CASHE');
        DatabaseManager::$tableCash = (boolean)$tableCash;
        DatabaseManager::connect(self::master(), self::slave());
    }
    
    public static function getUri($Key)
    {
        return env($Key);
    }

    public static function master()
    {
        return self::getUri('TRANSACTD_MASTER');
    }
    
    public static function slave()
    {
        return self::getUri('TRANSACTD_SLAVE');
    }
}
