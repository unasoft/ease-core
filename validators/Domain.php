<?php

namespace ease\validators;


use kdn\yii2\validators\DomainValidator;

class Domain extends DomainValidator
{
    /**
     * @var bool
     */
    public $allowWildCard = false;

    /**
     * @inheritdoc
     */
    protected function validateValue($value)
    {
        if ($this->allowWildCard) {
            $parts = explode('.', $value);

            if (isset($parts[0]) && $parts[0] === '*') {
                array_shift($parts);
            }

            return parent::validateValue(implode('.', $parts));
        } else {
            return parent::validateValue($value);
        }
    }
}