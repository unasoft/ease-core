<?php

namespace ej\helpers;

class Stringy extends \Stringy\Stringy
{
    /**
     * @return array
     */
    public function getAsciiCharMap()
    {
        return parent::charsArray();
    }
}
