<?php

namespace ease\assets;


use ej\web\View;
use ej\web\AssetBundle;

class RequireJs extends AssetBundle
{
    /**
     * @var string
     */
    public $sourcePath = '@bower/requirejs';

    /**
     * @var array
     */
    public $js = [
        'require.js',
    ];
    /**
     * @var array
     */
    public $jsOptions = [
        'position' => View::POS_HEAD
    ];
}