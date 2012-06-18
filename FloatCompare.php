<?php
namespace shozu;
/*
                LICENCE PUBLIQUE RIEN A BRANLER
                     Version 1, Mars 2009

Copyright (C) 2011 Mickael Desfrenes <desfrenes@gmail.com>

La copie et la distribution de copies exactes de cette licence sont
autorisées, et toute modification est permise à condition de changer
le nom de la licence.

        CONDITIONS DE COPIE, DISTRIBUTON ET MODIFICATION
              DE LA LICENCE PUBLIQUE RIEN A BRANLER

 0. Faites ce que vous voulez, j’en ai RIEN A BRANLER.
 */

/**
 * Warning: while this class may be enough for simple money problems,
 * DO NOT use this for math/scientific problems as this may not be
 * accurate enough. There are far better alternatives for this:
 * - http://www.php.net/manual/fr/ref.bc.php
 * - http://www.php.net/manual/fr/ref.gmp.php
 */
class FloatCompare
{
    const OPERATOR_EQUALS = '==';
    const OPERATOR_BIGGER = '>';
    const OPERATOR_SMALLER = '<';

    /**
     *
     * @param float $operand1
     * @param string $operator
     * @param float $operand2
     * @throws \InvalidArgumentException
     * @return bool
     */
    public static function assert($operand1, $operator, $operand2)
    {
        if(!in_array($operator, self::operators()))
        {
            throw new \InvalidArgumentException('Unknown operator');
        }
        $operand1 = (float)$operand1;
        $operand2 = (float)$operand2;
        if($operator == self::OPERATOR_EQUALS)
        {
            return self::equality($operand1, $operand2);
        }
        if($operator == self::OPERATOR_BIGGER)
        {
            return self::bigger($operand1, $operand2);
        }
        if($operator == self::OPERATOR_SMALLER)
        {
            return self::smaller($operand1, $operand2);
        }
    }

    private static function operators()
    {
        return array(
            self::OPERATOR_EQUALS,
            self::OPERATOR_BIGGER,
            self::OPERATOR_SMALLER);
    }
    private static function equality($operand1, $operand2)
    {
        return((string)$operand1 === (string)$operand2);
    }
    
    private static function bigger($operand1, $operand2)
    {
        $ints = self::toInt($operand1, $operand2);
        return $ints[0] > $ints[1];
    }

    private static function smaller($operand1, $operand2)
    {
        $ints = self::toInt($operand1, $operand2);
        return $ints[0] < $ints[1];
    }

    private static function toInt($operand1, $operand2)
    {
        $operand1 = self::formatNumber($operand1);
        $operand2 = self::formatNumber($operand2);
        list($left1, $right1) = explode('.', $operand1);
        list($left2, $right2) = explode('.', $operand2);
        if(strlen($right1) > strlen($right2))
        {
            $right2 = str_pad($right2, strlen($right1), '0', STR_PAD_RIGHT);
        }
        if(strlen($right1) < strlen($right2))
        {
            $right1 = str_pad($right1, strlen($right2), '0', STR_PAD_RIGHT);
        }
        return array((int)($left1 . $right1), (int)($left2 . $right2));
    }

    private static function formatNumber($number)
    {
        if(!strstr('.', $number))
        {
            $number.= '.00';
        }
        return $number;
    }

}

if(count(debug_backtrace()) == 0)
{
    echo "\n////////// PHP Float Comparison Test:\n\n";
    echo '$op1: 12.00 + 13.23 + 6.76' . "\n";
    $op1 = 12.00 + 13.23 + 6.76;
    echo '$op2: 31.99' . "\n";
    $op2 = 31.99;
    
    echo "\nisEqual test:\n";
    echo '    $op1 == $op2                           : '; var_dump($op1 == $op2);
    echo '    FloatCompare::assert($op1, \'==\', $op2) : '; var_dump(FloatCompare::assert($op1, '==', $op2));

    echo "\nisBigger test:\n";
    echo '    $op1 > $op2                           : '; var_dump($op1 > $op2);
    echo '    FloatCompare::assert($op1, \'>\', $op2) : '; var_dump(FloatCompare::assert($op1, '>', $op2));

    echo "\nisSmaller test:\n";
    echo '    $op1 < $op2                           : '; var_dump($op1 < $op2);
    echo '    FloatCompare::assert($op1, \'<\', $op2) : '; var_dump(FloatCompare::assert($op1, '<', $op2));
    echo "\n";
}