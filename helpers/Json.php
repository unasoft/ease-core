<?php

namespace ej\helpers;


class Json extends \yii\helpers\Json
{
    /**
     * @param $data
     * @return string
     */
    public static function encodeJs($data)
    {
        return static::encode($data, JSON_HEX_QUOT | JSON_HEX_TAG);
    }
}