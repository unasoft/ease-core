<?php

namespace core\interfaces;


use yii\web\IdentityInterface;
use core\modules\user\models\Fields;

interface Identity extends IdentityInterface
{
    /**
     * @return Fields
     */
    public function fields();
}