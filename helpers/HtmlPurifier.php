<?php

namespace ease\helpers;

use HTMLPurifier_Config;


class HtmlPurifier extends \yii\helpers\HtmlPurifier
{
    /**
     * @param string $string
     *
     * @return string
     */
    public static function cleanUtf8($string)
    {
        return \HTMLPurifier_Encoder::cleanUTF8($string);
    }

    /**
     * @param string $string
     * @param HTMLPurifier_Config $config
     *
     * @return string
     */
    public static function convertToUtf8($string, $config)
    {
        return \HTMLPurifier_Encoder::convertToUTF8($string, $config, null);
    }
}
