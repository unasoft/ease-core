<?php

namespace ease\web;


use Yii;
use yii\web\Response;

/**
 * Class Controller
 *
 * @method \ease\web\View getView()
 */
class Controller extends \yii\web\Controller
{
    /**
     * @param \yii\base\View $view
     *
     * @return bool|string
     */
    public function findLayoutFile($view)
    {
        $module = $this->module;
        if (is_string($this->layout)) {
            $layout = $this->layout;
        } elseif ($this->layout === null) {
            while ($module !== null && $module->layout === null) {
                $module = $module->module;
            }
            if ($module !== null && is_string($module->layout)) {
                $layout = $module->layout;
            }
        }

        if (!isset($layout)) {
            return false;
        }

        if (strncmp($layout, '@', 1) === 0) {
            $file = Yii::getAlias($layout);
        } elseif (strncmp($layout, '/', 1) === 0) {
            $file = Yii::$app->getLayoutPath() . DIRECTORY_SEPARATOR . substr($layout, 1);
        } else {
            $file = $module->getLayoutPath() . DIRECTORY_SEPARATOR . $layout;
        }

        if (pathinfo($file, PATHINFO_EXTENSION) !== '') {
            return $file;
        }
        $path = $file . '.' . $view->defaultExtension;

        return $path;
    }

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