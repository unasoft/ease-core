<?php

namespace ease\db;


class Connection extends \yii\db\Connection
{
    /**
     * @var bool
     */
    public $enableSchemaCache = true;
}