<?php

namespace ej\models\site;


use yii\db\ActiveRecord;

class Site extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    const IS_ACTIVE = 1;
    /**
     * @inheritdoc
     */
    const IS_INACTIVE = 0;
    /**
     * @var
     */
    private $_config;

    /**
     * @return string
     */
    public static function tableName(): string
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

    /**
     * @return Config
     */
    public function getConfig()
    {
        if ($this->_config === null) {
            $this->_config = new Config($this->getId());
        }

        return $this->_config;
    }
}