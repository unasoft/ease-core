<?php

namespace ej\base\site;


use yii\db\ActiveRecord;

class Record extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName()
    {
        return '{{%sites}}';
    }

    /**
     * @return integer
     */
    public function getId(): int
    {
        return (int)$this->site_id;
    }
}