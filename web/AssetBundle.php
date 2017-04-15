<?php

namespace ej\web;


use ej\helpers\Url;

class AssetBundle extends \yii\web\AssetBundle
{
    /**
     * @var
     */
    private static $_webUrl;

    /**
     * @param $resource
     *
     * @return string
     */
    public static function webUrl($resource)
    {
        if (!Url::isRelative($resource)) {
            return $resource;
        }

        if (self::$_webUrl === null) {
            $bundle = \Yii::$app->getAssetManager()->getBundle(get_called_class());

            self::$_webUrl = $bundle instanceof AssetBundle ? $bundle->baseUrl : '';
        }

        return self::$_webUrl . '/' . ltrim($resource, '/');
    }
}