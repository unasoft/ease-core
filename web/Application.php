<?php

namespace ease\web;


class Application extends \yii\web\Application
{
    use \ease\traits\Application;

    /**
     * @return null|DeviceDetect
     */
    public function getDevice()
    {
        return $this->get('device');
    }
}