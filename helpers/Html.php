<?php

namespace ej\helpers;


use libphonenumber\PhoneNumberUtil;

class Html extends \yii\helpers\Html
{
    /**
     * @param \yii\base\Model $model
     * @param string $attribute
     * @param array $options
     *
     * @return string
     */
    public static function error($model, $attribute, $options = [])
    {
        return $model->hasErrors($attribute) ? parent::error($model, $attribute, $options) : '';
    }

    /**
     * @param $data
     *
     * @return string
     */
    public static function postJsonData($data)
    {
        if (\Yii::$app->getRequest()->enableCsrfValidation) {
            $data[\Yii::$app->getRequest()->csrfParam] = \Yii::$app->getRequest()->csrfToken;
        }

        return Json::encodeJs($data);
    }

    /**
     * @param $value
     *
     * @return string
     */
    public static function inputTypeByValue($value)
    {
        $value = trim($value);
        if (preg_match("/^(.*<?)(.*)@(.*?)(>?)$/", $value)) {
            return 'email';
        } else if (!empty(($phone = PhoneNumberUtil::isViablePhoneNumber($value)))) {
            return 'phone';
        } else {
            return 'text';
        }
    }
}