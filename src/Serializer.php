<?php

namespace Transactd;

trait Serializer
{
    /**
     *
     * @var type A class name for serizlization.
     */
    protected $className;
    
    /**
     * Return a JSON text of this model
     *
     * @return string
     */
    public function toString()
    {
        return static::serialize($this);
    }
    
    /**
     * Return a JSON text of this model
     *
     * @return string
     */
    public function toJson()
    {
        return $this->toString();
    }
    /**
     * Copy contents of the original model.
     *
     * @param \Transactd\Model $src model of original
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function assign($src)
    {
        if (is_object($src)) {
            foreach ($src as $key => $v) {
                $this->{$key} = static::chengeObjectType($v);
            }
            return $this;
        }
        throw new \InvalidArgumentException('$src is not object.');
    }
    
    /**
     * Create a new instance or clone model
     *
     * @param \Transactd\Model $src (optional) For clone
     * @return \Transactd\Model
     */
    public static function createInstance($src = null)
    {
        $obj = new static();
        if ($src !== null) {
            $obj->assign($src);
        }
        return $obj;
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
        $props =  get_object_vars($obj);
        foreach ($props as $key => $value) {
            $s .= '"'.$key.'":';
            if (is_object($value) === true) {
                if ($value instanceof Collection) {
                    $s .= $value->toString();
                } else {
                    $s .= Model::serialize($value);
                }
            } else {
                $s .= json_encode($value);
            }
            $s .= ',';
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
        return static::chengeObjectType($obj);
    }
    
    /**
     * Deserializes from JSON string.
     *
     * @param string $json
     * @return object
     */
    public static function fromJson($json)
    {
        return static::deSerialize($json);
    }
}
