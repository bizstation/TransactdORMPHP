<?php

namespace Transactd;

class Serializer
{
    /**
     * Specified array returns whether or not the hash.
     * 
     * @param array $array
     * @return bool
     */
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
    
    /**
     * Change object types by 'className'.
     * 
     * @param object $v
     * @return object
     */
    public static function chengeObjectType($v)
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

    /**
     * Serializes to JSON string.
     * 
     * @param object $obj
     * @return string
     */
    public static function serialize($obj)
    {
        $s = '{';
        foreach ($obj as $key => $value) {
            if (strpos($key, '__') !== 0) {
                $s .= '"'.$key.'":';
                $s .= json_encode($value).',';
            }
        }
		$className = get_class($obj);
        if (is_object($obj) && property_exists($className,  'serialize')) {
            foreach ($className::$serialize as $key) {
                $s .= '"'.$key.'":';
                $s .= json_encode($obj->{$key}).',';
            }
        }
        return substr($s, 0, -1).'}';
    }

    /**
     * Deserializes from JSON string.
     * 
     * @param string $json
     * @return object
     */
    public static function deSerialize($json)
    {
        $obj = json_decode($json, false);
        return self::chengeObjectType($obj);
    }
}
