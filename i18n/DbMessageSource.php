<?php

namespace ej\i18n;


class DbMessageSource extends \yii\i18n\DbMessageSource
{
    /**
     * @var string
     */
    public $sourceMessageTable = '{{%i18n_source}}';
    /**
     * @var string
     */
    public $messageTable = '{{%i18n_message}}';
    /**
     * @var bool
     */
    public $enableCaching = true;
}