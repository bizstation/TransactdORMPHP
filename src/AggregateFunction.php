<?php

namespace Transactd;

use BizStation\Transactd\transactd;

if (transactd::fieldValueMode() !== transactd::FIELD_VALUE_MODE_VALUE) {
    transactd::setFieldValueMode(transactd::FIELD_VALUE_MODE_VALUE);
}

function fsum($l, $r)
{
    return $l + $r;
}
function fmax($l, $r)
{
    return  ($l >= $r) ? $l : $r;
}
function fmin($l, $r)
{
    return  ($l <= $r) ? $l : $r;
}
function favg($total, $size)
{
    return $total / $size;
}

class AggregateFunction
{
    private static $fetchMode;

    private static function prepare($rs, $column)
    {
        self::$fetchMode = $rs->fetchMode;
        $rs->fetchMode = transactd::FETCH_RECORD_INTO;
        $fd = $rs->fielddefs()->indexByName($column);
        if ($fd === -1) {
            throw new \OutOfRangeException();
        }
        return $fd;
    }

    private static function calc($rs, $column, $func, $func2 = null)
    {
        $size = $rs->size();
        $ret = null;
        if ($size > 0) {
            $fd = self::prepare($rs, $column);
            $ret = $rs[0][$fd];
            for ($i = 1; $i < $size; ++$i) {
                $ret = $func($ret, $rs[$i][$fd]);
            }
            if ($func2 !== null) {
                $ret = $func2($ret, $size);
            }
            $rs->fetchMode = self::$fetchMode;
        }
        return $ret;
    }

    public static function sum($rs, $column)
    {
        return self::calc($rs, $column, 'Transactd\fsum');
    }

    public static function max($rs, $column)
    {
        return self::calc($rs, $column, 'Transactd\fmax');
    }

    public static function min($rs, $column)
    {
        return self::calc($rs, $column, 'Transactd\fmin');
    }

    public static function avg($rs, $column)
    {
        return self::calc($rs, $column, 'Transactd\fsum', 'Transactd\favg');
    }
}
