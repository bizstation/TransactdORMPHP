<?php

namespace Transactd;

class Serializer
{
    public static function isHash(&$array)
    {
        $i = 0;
        foreach ($array as $k => $v) {
            if ($k !== $i++) {
                return true;
            }
        }
        return false;
    }

    private static function arrayToStr($array)
    {
        $s = '';
        if (count($array) === 0) {
            return '[]';
        }
        $hash = self::isHash($array);
        if ($hash === true) {
            $tmp = '{';
            foreach ($array as $key => $v) {
                if (method_exists($v, 'toString')) {
                    $tmp .= '"'.$key.'":'.$v->toString();
                } else {
                    $tmp .= '"'.$key.'":'.json_encode($v);
                }
                $tmp .= ',';
            }
            $s .= substr($tmp, 0, -1).'}';
        } else {
            $tmp = '[';
            foreach ($array as $v) {
                if (method_exists($v, 'toString')) {
                    $tmp .= $v->toString();
                } else {
                    $tmp .= json_encode($v);
                }
                $tmp .= ',';
            }
            $s .= substr($tmp, 0, -1).']';
        }
        return $s;
    }

    public static function getTypeValue($v)
    {
        if (is_object($v)) {
            if (array_key_exists('className', $v) === true) {
                $tmp = $v->{'className'};
                return $tmp::createInstance($v);
            }
        } elseif (is_array($v)) {
            $ar = array();
            foreach ($v as $v1) {
                if (is_array($v1) && array_key_exists('className', $v1) === true) {
                    /*if (is_object($v1))
                        $tmp = $v1->{'className'};
                    else*/
                        $tmp = $v1['className'];
                    array_push($ar, $tmp::createInstance($v1));
                } else {
                    array_push($ar, $v1);
                }
            }
            return $ar;
        }
        return $v;
    }

    public static function serialize($obj)
    {
        $s = '{';
        foreach ($obj as $key => $value) {
            if (strpos($key, '__') !== 0) {
                $s .= '"'.$key.'":';
                $s .= json_encode($value).',';
            }
        }
        if (is_object($obj) && property_exists($obj,  'serialize')) {
            foreach ($obj->serialize as $key) {
                $s .= '"'.$key.'":';
                $s .= json_encode($obj->{$key}).',';
            }
        }
        return substr($s, 0, -1).'}';
    }

    public static function deSerialize($json)
    {
        $obj = json_decode($json, false);
        return self::getTypeValue($obj);
    }
}
