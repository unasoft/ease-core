<?php

if (!function_exists('__')) {
    /**
     * @param string $source
     * @param $message
     * @param array $params
     * @param null $language
     *
     * @return string
     */
    function __($source, $message, $params = [], $language = null)
    {
        if (Yii::$app !== null && !array_key_exists($source, Yii::$app->getI18n()->translations)) {
            return $message;
        }

        return \Yii::t($source, $message, $params, $language);
    }
}