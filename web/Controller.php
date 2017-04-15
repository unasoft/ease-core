<?php

namespace ej\web;


use yii\web\Response;

/**
 * Class Controller
 *
 * @method \ej\web\View getView()
 */
class Controller extends \yii\web\Controller
{
    /**
     * Sets the response format of the given data as RAW.
     *
     * @param mixed $var The RAW array data.
     *
     * @return Response The response object.
     */
    public function asRaw($var = [])
    {
        $response = \Yii::$app->getResponse();
        $response->data = $var;
        $response->format = Response::FORMAT_RAW;

        return $response;
    }
}