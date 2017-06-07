<?php

namespace ease\assets;


use ej\web\AssetBundle;

class FontsAsset extends AssetBundle
{
    /**
     * @var string
     */
    public $sourcePath = __DIR__ . '/dist/fonts';
    /**
     * @var array
     */
    public $css = [
        'fonts.css'
    ];
}