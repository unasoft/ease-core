<?php

namespace ej\web;


class Application extends \yii\web\Application
{
    use \ej\traits\Application;

    /**
     * @return null|DeviceDetect
     */
    public function getDevice()
    {
        return $this->get('device');
    }
}