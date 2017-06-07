<?php

namespace ease\log;


class Dispatcher extends \yii\log\Dispatcher
{
    /**
     * @return int
     */
    public function getTraceLevel()
    {
        return YII_DEBUG ? (parent::getTraceLevel() == 0 ? 3 : parent::getTraceLevel()) : parent::getTraceLevel();
    }
}