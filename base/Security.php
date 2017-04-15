<?php

namespace ej\base;


use yii\base\ErrorException;

class Security extends \yii\base\Security
{
    /**
     * @return string
     * @throws ErrorException
     */
    public function getValidationKey()
    {
        if ($key = \Yii::$app->getConfig()->get('securityKey')) {
            return $key;
        }

        $key = $this->generateRandomString(64);

        if (\Yii::$app->getConfig()->set('securityKey', $key)) {
            return $key;
        }
    }
}