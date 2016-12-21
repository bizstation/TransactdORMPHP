<?php

namespace Transactd;

use BizStation\Transactd\Query;

/**
 * This class is used only in QueryExecuter. The user does not have to be used.
 */
class QueryAdapter
{
    private $q;
    private $whereFlag = false;
    private $skip = 0;
    private $take = 0;

    private function throwUseFirstException($name)
    {
        $this->reset();
        throw new \LogicException($name.' method use in the first condition.');
    }

    private function throwUseSecondException($name)
    {
        $this->reset();
        throw new \LogicException($name.' method use in the second and subsequent conditions.');
    }

    public function __construct()
    {
        $this->q = new Query();
    }
  
    public function reset()
    {
        $this->whereFlag = false;
        $this->skip = 0;
        $this->take = 0;
        query_reset($this->q->cPtr);
    }

    public function isWhereDefined()
    {
        return $this->whereFlag;
    }
    
    /**
     *
     * @return BizStation\Transactd\Query
     */
    public function query()
    {
        if ($this->take > 0) {
            $this->q->stopAtLimit(true)->limit($this->take + $this->skip);
        } else {
            $this->q->stopAtLimit(false)->limit(0);
        }
        return $this->q;
    }
    
    /**
     *
     * @return int
     */
    public function getSkip()
    {
        return $this->skip;
    }
  
    private function extructArrayAndCall($name, $func)
    {
        if (is_array($name) === true && count($name) > 0) {
            if (is_array($name[0]) === true) {
                foreach ($name as $where) {
                    $name1 = $where[0];
                    $name2 = count($where) > 1 ? $where[1] : null;
                    $name3 = count($where) > 2 ? $where[2] : null;
                    $this->{$func}($name1, $name2, $name3);
                }
            } else {
                $name1 = $name[0];
                $name2 = count($name) > 1 ? $name[1] : null;
                $name3 = count($name) > 2 ? $name[2] : null;
                $this->{$func}($name1, $name2, $name3);
            }
            return true;
        }
        return false;
    }
    
    /**
     *
     * @param string $name A field name.
     * @param string|mixed $operator  Operator or a value.
     * @param mixed $value (optional) a value
     * @return void
     */
    public function where($name, $operator, $value = null)
    {
        if ($this->extructArrayAndCall($name, 'where') === true) {
            return;
        }
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        if ($this->whereFlag === true) {
            $this->q->and_($name, $operator, $value);
        } else {
            $this->q->where($name, $operator, $value);
        }
        $this->whereFlag = true;
    }
    
    /**
     *
     * @param string $name A field name.
     * @param string|mixed $operator  Operator or a value.
     * @param mixed $value (optional) a value
     * @return void
     */
    public function orWhere($name, $operator, $value = null)
    {
        if ($this->whereFlag === false) {
            $this->throwUseSecondException('orWhere');
        }
        if ($this->extructArrayAndCall($name, 'orWhere') === true) {
            return;
        }
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        $this->q->or_($name, $operator, $value);
    }
    
    /**
     *
     * @param string $name A field name.
     * @param string|mixed $operator  Operator or a value.
     * @param mixed $value (optional) a value
     * @return void
     */
    public function whereColumn($name, $operator, $value = null)
    {
        if ($this->extructArrayAndCall($name, 'whereColumn') === true) {
            return;
        }

        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        $value = '['.$value.']';

        if ($this->whereFlag === true) {
            $this->q->and_($name, $operator, $value);
        } else {
            $this->q->where($name, $operator, $value);
        }
        $this->whereFlag = true;
    }
    
    /**
     *
     * @param string $name A field name.
     * @param string|mixed $operator  Operator or a value.
     * @param mixed $value (optional) a value
     * @return void
     */
    public function orColumn($name, $operator, $value = null)
    {
        // search same field value
        if ($this->whereFlag === false) {
            $this->throwUseSecondException('orColumn');
        }

        if ($this->extructArrayAndCall($name, 'orColumn') === true) {
            return;
        }
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        $value = '['.$value.']';
        $this->q->or_($name, $operator, $value);
        $this->whereFlag = true;
    }
    
    /**
     *
     * @param string $name A field name.
     * @return void
     */
    public function whereNull($name)
    {
        if ($this->whereFlag === true) {
            $this->q->andIsNull($name);
        } else {
            $this->q->whereIsNull($name);
        }
        $this->whereFlag = true;
    }
    
    /**
     *
     * @param string $name A field name.
     * @return void
     */
    public function orNull($name)
    {
        if ($this->whereFlag === false) {
            $this->throwUseSecondException('orNull');
        }
        $this->q->orIsNull($name);
    }

    /**
     *
     * @param string $name A field name.
     * @return void
     */
    public function whereNotNull($name)
    {
        if ($this->whereFlag === true) {
            $this->q->andIsNotNull($name);
        } else {
            $this->q->whereIsNotNull($name);
        }
        $this->whereFlag = true;
    }

    /**
     *
     * @param string $name A field name.
     * @return void
     */
    public function orNotNull($name)
    {
        if ($this->whereFlag === false) {
            $this->throwUseSecondException('orNotNull');
        }
        $this->q->orIsNotNull($name);
    }
    
    /**
     *
     * @param BizStation\Transactd\Table $tb
     * @param array $values Key values. A $values is always a one-dimensional array.
     * @param int $segments The segment count of values.
     */
    public function whereInKey($tb, $values, $segments = null)
    {
        if ($this->whereFlag === true) {
            $this->throwUseFirstException('whereInKey');
        }
        $td = $tb->tableDef();
        if ($segments !== null) {
            $this->q->segmentsForInValue($segments);
        } else {
            $this->q->segmentsForInValue($td->keyDef($tb->keyNum())->segmentCount);
        }
        $this->q->clearSeekKeyValues();
        foreach ($values as $value) {
            $this->q->in($value);
        }
    }
    /**
     *
     * @param string $name A field name.
     * @param array $values Key values. A $values is always a one-dimensional array.
     */
    public function whereIn($name, $values)
    {
        if ($this->whereFlag === true) {
            $this->throwUseFirstException('whereIn');
        }
        foreach ($values as $value) {
            if ($this->whereFlag === true) {
                $this->q->or_($name, '=', $value);
            } else {
                $this->q->where($name, '=', $value);
                $this->whereFlag = true;
            }
        }
    }
    
    /**
     *
     * @param string $name A field name.
     * @param mixed $values Key values.
     */
    public function whereNotIn($name, $values)
    {
        if ($this->whereFlag === true) {
            $this->throwUseFirstException('whereNotIn');
        }
        foreach ($values as $value) {
            if ($this->whereFlag === true) {
                $this->q->and_($name, '<>', $value);
            } else {
                $this->q->where($name, '<>', $value);
                $this->whereFlag = true;
            }
        }
    }

    /**
     *
     * @param string $name A field name.
     * @param mixed[2] $valuePair A pair of first value and end value.
     */
    public function whereBetween($name, $valuePair)
    {
        if ($this->whereFlag === true) {
            $this->throwUseFirstException('whereBetween');
        }
        $this->q->where($name, '>=', $valuePair[0])->and_($name, '<=', $valuePair[1]);
        $this->whereFlag = true;
    }
    /**
     *
     * @param string $name A field name.
     * @param mixed[2] $valuePair A pair of first value and end value.
     */
     public function whereNotBetween($name, $valuePair)
     {
         if ($this->whereFlag === true) {
             $this->throwUseFirstException('whereNotBetween');
         }
         $this->q->where($name, '<', $valuePair[0])->or_($name, '>', $valuePair[1]);
         $this->whereFlag = true;
     }

    /**
     *
     * @param string $name1 A field name.
     * @param type $name2 (optional)  A field name.
     * @param type $name3 (optional)  A field name.
     * @param type $name4 (optional)  A field name.
     * @param type $name5 (optional)  A field name.
     * @param type $name6 (optional)  A field name.
     * @param type $name7 (optional)  A field name.
     * @param type $name8 (optional)  A field name.
     */
    public function select($name1, $name2 = null, $name3 = null, $name4 = null, $name5 = null, $name6 = null, $name7 = null, $name8 = null)
    {
        $this->q->clearSelectFields();
        $this->q->select($name1, $name2, $name3, $name4, $name5, $name6, $name7, $name8);
    }

    /**
     *
     * @param string $name A field name.
     */
    public function addSelect($name)
    {
        $this->q->select($name);
    }
    /**
     *
     * @param int $n
     */
    public function reject($n)
    {
        $this->q->reject($n);
    }
    
    /**
     *
     * @param int $n
     */
    public function skip($n)
    {
        $this->skip = $n;
    }

    /**
     *
     * @param int $n
     */
    public function take($n)
    {
        $this->take = $n;
    }
    /**
     * 
     * @param int $v Nstable::findForword| Nstable::findBackForword
     */
    public function direction($v)
    {
        $this->q->direction($v);
    }
}
