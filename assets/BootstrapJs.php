<?php

namespace ej\assets;


use ej\web\AssetBundle;

class BootstrapJs extends AssetBundle
{
    /**
     * @var string
     */
    public $sourcePath = __DIR__ . '/bootstrap/dist';
    /**
     * @var array
     */
    public $js = [
        YII_DEBUG ? 'js/bootstrap.js' : 'js/bootstrap.min.js',
    ];
    /**
     * @var array
     */
    public $depends = [
        'ej\assets\Jquery',
    ];
}
