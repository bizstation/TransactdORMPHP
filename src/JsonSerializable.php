<?php

namespace Transactd;

trait JsonSerializable
{
    /**
     *
     * @var type A class name for serizlization.
     */
    protected $className;
    
    public function getClassName()
    {
        return $this->className;
    }
    
    /**
     * Return a JSON text of this model
     *
     * @return string
     */
    public function toString()
    {
        return static::serializeToJson($this);
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
            $hasCollection = false;
            foreach ($src as $key => $v) {
                $this->{$key} = static::chengeObjectType($v, $this, $key);
                if ($this->{$key} instanceof Collection) {
                    $hasCollection = true;
                }
            }
            if ($hasCollection) {
                foreach ($src as $key => $v) {
                    if ($this->{$key} instanceof Collection) {
                        $this->{$key}->setRelation($this->{$key}());
                        $this->{$key}->setParent($this);
                    }
                }
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
    public static function chengeObjectType($v, $parent, $name)
    {
        if (is_object($v)) {
            if (array_key_exists('className', $v) === true) {
                $tmp = $v->{'className'};
                $obj = $tmp::createInstance($v);
                return $obj;
            } elseif (property_exists($v, '0')) {
                $v = (array) $v;
            }
        } 
        if (is_array($v)) {
            $ar = array();
            foreach ($v as $v1) {
                if (is_object($v1) && array_key_exists('className', $v1) === true) {
                    $tmp = $v1->{'className'};
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
    public static function serializeToJson($obj)
    {
        $s = '{';
        $props =  get_object_vars($obj);
        foreach ($props as $key => $value) {
            $s .= '"'.$key.'":';
            if (is_object($value) === true) {
                if ($value instanceof Collection) {
                    $s .= $value->toString();
                } elseif (property_exists($value, 'className')) {
                    $name = $value->getClassName();
                    $s .= $name::serializeToJson($value);
                } else {
                    $s .= self::serializeToJson($value);
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
    public static function deSerializeFromJson($json)
    {
        $obj = json_decode($json, false);
        return static::chengeObjectType($obj, null, null);
    }
    
    /**
     * Deserializes from JSON string.
     *
     * @param string $json
     * @return object
     */
    public static function fromJson($json)
    {
        return static::deSerializeFromJson($json);
    }
}
