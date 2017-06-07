<?php

namespace ease\base;


class DynamicModel extends \yii\base\DynamicModel
{
    /**
     * @var
     */
    private $_attributeLabels;

    /**
     * @param array $labels
     */
    public function setAttributeLabels(array $labels)
    {
        $this->_attributeLabels = $labels;
    }

    /**
     * @return mixed
     */
    public function attributeLabels()
    {
        if (!is_array($this->_attributeLabels)) {
            $this->_attributeLabels = [];
        }

        return $this->_attributeLabels;
    }
}