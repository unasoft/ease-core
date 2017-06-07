<?php

namespace ease\console\controllers;


/**
 * Class MessageController
 * @package ej\console\controllers
 */
class MessageController extends \yii\console\controllers\MessageController
{
    /**
     * @var array
     */
    public $languages = ['en', 'ru'];
    /**
     * @var string
     */
    public $translator = '__';
    /**
     * @var string
     */
    public $sourcePath = '@protected';
    /**
     * @var string
     */
    public $format = 'db';
    /**
     * @var string
     */
    public $sourceMessageTable = '{{%i18n_source}}';
    /**
     * @var string custom name for translation message table for "db" format.
     */
    public $messageTable = '{{%i18n_message}}';
    /**
     * @var array
     */
    public $except = [
        '.svn',
        '.git',
        '.gitignore',
        '.gitkeep',
        '.hgignore',
        '.hgkeep',
        '/messages',
        '/vendor',
        '/var',
        '/functions.php',
        '/BaseYii.php',
    ];
}
