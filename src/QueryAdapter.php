<?php

namespace Transactd;

use BizStation\Transactd\query;

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
        $this->q = new query();
    }

    public function reset()
    {
        $this->whereFlag = false;
        $this->skip = 0;
        $this->take = 0;
        $this->q->reset();
    }

    public function isWhereDefined()
    {
        return $this->whereFlag;
    }

    public function query()
    {
        if ($this->take > 0) {
            $this->q->stopAtLimit(true)->limit($this->take + $this->skip);
        } else {
            $this->q->stopAtLimit(false)->limit(0);
        }
        return $this->q;
    }

    public function getSkip()
    {
        return $this->skip;
    }

    private function extructArrayAndCall($a, $func)
    {
        if (is_array($a) === true && count($a) > 0) {
            if (is_array($a[0]) === true) {
                foreach ($a as $where) {
                    $a1 = $where[0];
                    $a2 = count($where) > 1 ? $where[1] : null;
                    $a3 = count($where) > 2 ? $where[2] : null;
                    $this->{$func}($a1, $a2, $a3);
                }
            } else {
                $a1 = $a[0];
                $a2 = count($a) > 1 ? $a[1] : null;
                $a3 = count($a) > 2 ? $a[2] : null;
                $this->{$func}($a1, $a2, $a3);
            }
            return true;
        }
        return false;
    }

    public function where($a, $b = null, $c = null)
    {
        if ($this->extructArrayAndCall($a, 'where') === true) {
            return;
        }
        if ($c === null) {
            $c = $b;
            $b = '=';
        }
        if ($this->whereFlag === true) {
            $this->q->and_($a, $b, $c);
        } else {
            $this->q->where($a, $b, $c);
        }
        $this->whereFlag = true;
    }

    public function orWhere($a, $b = null, $c = null)
    {
        if ($this->whereFlag === false) {
            $this->throwUseSecondException('orWhere');
        }
        if ($this->extructArrayAndCall($a, 'orWhere') === true) {
            return;
        }
        if ($c === null) {
            $c = $b;
            $b = '=';
        }
        $this->q->or_($a, $b, $c);
    }

    public function whereColumn($a, $b = null, $c = null)
    {
        // search same field value

        if ($this->extructArrayAndCall($a, 'whereColumn') === true) {
            return;
        }

        if ($c === null) {
            $c = $b;
            $b = '=';
        }
        $c = '['.$c.']';

        if ($this->whereFlag === true) {
            $this->q->and_($a, $b, $c);
        } else {
            $this->q->where($a, $b, $c);
        }
        $this->whereFlag = true;
    }

    public function orColumn($a, $b = null, $c = null)
    {
        // search same field value
        if ($this->whereFlag === false) {
            $this->throwUseSecondException('orColumn');
        }

        if ($this->extructArrayAndCall($a, 'orColumn') === true) {
            return;
        }
        if ($c === null) {
            $c = $b;
            $b = '=';
        }
        $c = '['.$c.']';
        $this->q->or_($a, $b, $c);
        $this->whereFlag = true;
    }

    public function whereNull($fdname)
    {
        if ($this->whereFlag === true) {
            $this->q->andIsNull($fdname);
        } else {
            $this->q->whereIsNull($fdname);
        }
        $this->whereFlag = true;
    }

    public function orNull($fdname)
    {
        if ($this->whereFlag === false) {
            $this->throwUseSecondException('orNull');
        }
        $this->q->orIsNull($fdname);
    }

    public function whereNotNull($fdname)
    {
        if ($this->whereFlag === true) {
            $this->q->andIsNotNull($fdname);
        } else {
            $this->q->whereIsNotNull($fdname);
        }
        $this->whereFlag = true;
    }

    public function orNotNull($fdname)
    {
        if ($this->whereFlag === false) {
            $this->throwUseSecondException('orNotNull');
        }
        $this->q->orIsNotNull($fdname);
    }

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

    public function whereIn($fdName, $values)
    {
        if ($this->whereFlag === true) {
            $this->throwUseFirstException('whereIn');
        }
        foreach ($values as $value) {
            if ($this->whereFlag === true) {
                $this->q->or_($fdName, '=', $value);
            } else {
                $this->q->where($fdName, '=', $value);
                $this->whereFlag = true;
            }
        }
    }

    public function whereNotIn($fdName, $values)
    {
        if ($this->whereFlag === true) {
            $this->throwUseFirstException('whereNotIn');
        }
        foreach ($values as $value) {
            if ($this->whereFlag === true) {
                $this->q->and_($fdName, '<>', $value);
            } else {
                $this->q->where($fdName, '<>', $value);
                $this->whereFlag = true;
            }
        }
    }

    public function whereBetween($fdName, $valuePair)
    {
        if ($this->whereFlag === true) {
            $this->throwUseFirstException('whereBetween');
        }
        $this->q->where($fdName, '>=', $valuePair[0])->and_($fdName, '<=', $valuePair[1]);
        $this->whereFlag = true;
    }

    public function whereNotBetween($fdName, $valuePair)
    {
        if ($this->whereFlag === true) {
            $this->throwUseFirstException('whereNotBetween');
        }
        $this->q->where($fdName, '<', $valuePair[0])->or_($fdName, '>', $valuePair[1]);
        $this->whereFlag = true;
    }

    public function select($a, $b = null, $c = null, $d = null, $e = null, $f = null, $g = null, $h = null)
    {
        $this->q->clearSelectFields();
        $this->q->select($a, $b, $c, $d, $e, $f, $g, $h);
    }

    public function addSelect($a)
    {
        $this->q->select($a);
    }

    public function reject($v)
    {
        $this->q->reject($v);
    }

    public function skip($n)
    {
        $this->skip = $n;
    }

    public function take($n)
    {
        $this->take = $n;
    }
}
