<?php

namespace ease\web;


class Request extends \yii\web\Request
{
    /**
     * Request constructor.
     *
     * @param array $config
     */
    public function __construct($config = [])
    {
        parent::__construct($config);

        if (!$this->cookieValidationKey) {
            $this->cookieValidationKey = \Yii::$app->getSecurity()->getValidationKey();
        }
    }
}