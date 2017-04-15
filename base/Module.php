<?php

namespace ej\base;


abstract class Module extends \yii\base\Module
{
    /**
     * @return string
     */
    public function getName()
    {
        return '';
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return '';
    }
}