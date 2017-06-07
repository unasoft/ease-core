<?php

namespace ease\validators;


use ej\helpers\Html;
use ej\helpers\Json;
use yii\validators\Validator;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberType;
use libphonenumber\NumberParseException;

class Phone extends Validator
{
    /**
     * @var mixed
     */
    public $region;
    /**
     * @var integer
     */
    public $type;

    /**
     * @inheritdoc
     */
    public function init()
    {
        if (!$this->message) {
            $this->message = __('user', 'The format of {attribute} is invalid.');
        }
        parent::init();
    }

    /**
     * @param mixed $value
     *
     * @return array|null
     */
    protected function validateValue($value)
    {
        $valid = false;
        $phoneUtil = PhoneNumberUtil::getInstance();
        $value = $this->normalizeNumber($value);

        try {
            $phoneProto = $phoneUtil->parse($value, null);
            if ($this->region !== null) {
                $regions = is_array($this->region) ? $this->region : [$this->region];
                foreach ($regions as $region) {
                    if ($phoneUtil->isValidNumberForRegion($phoneProto, $region)) {
                        $valid = true;
                        break;
                    }
                }
            } else {
                if ($phoneUtil->isValidNumber($phoneProto)) {
                    $valid = true;
                }
            }
            if ($this->type !== null) {
                if (PhoneNumberType::UNKNOWN != $type = $phoneUtil->getNumberType($phoneProto)) {
                    $valid = $valid && $type == $this->type;
                }
            }
        } catch (NumberParseException $e) {
        }
        return $valid ? null : [$this->message, []];
    }

    /**
     * @inheritdoc
     */
    public function clientValidateAttribute($model, $attribute, $view)
    {
        $telInputId = Html::getInputId($model, $attribute);
        $options = Json::htmlEncode([
            'message' => \Yii::$app->getI18n()->format($this->message, [
                'attribute' => $model->getAttributeLabel($attribute)
            ], \Yii::$app->language)
        ]);
        return <<<JS
var options = $options, telInput = $("#$telInputId");
if($.trim(telInput.val())){
    if(!telInput.intlTelInput("isValidNumber")){
        messages.push(options.message);
    }
}
JS;
    }

    /**
     * @param $value
     *
     * @return string
     */
    protected function normalizeNumber($value)
    {
        if (strpos($value, PhoneNumberUtil::PLUS_SIGN) !== 0) {
            $value = PhoneNumberUtil::PLUS_SIGN . $value;
        }

        return $value;
    }
}