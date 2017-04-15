<?php

namespace ej\assets;


use ej\web\AssetBundle;

class Jquery extends AssetBundle
{
    /**
     * @var string
     */
    public $sourcePath = '@bower/jquery/dist';
    /**
     * @var array
     */
    public $js = [
        'jquery.js',
    ];
}