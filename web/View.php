<?php

namespace ej\web;


use Yii;
use ej\base\Theme;
use ej\helpers\Html;
use ej\helpers\FileHelper;
use ej\exceptions\ViewRender;
use yii\base\InvalidCallException;
use yii\base\ViewContextInterface;


/**
 * Class View
 *
 * @property Theme $theme
 * @method \ej\web\AssetManager      getAssetManager()
 */
class View extends \yii\web\View
{
    /**
     * @param array $assets
     */
    public function head($assets = ['default'])
    {
        $this->registerAsset($assets);

        parent::head();
    }

    /**
     * @param $assets
     */
    public function registerAsset($assets)
    {
        if ($assets !== false && !empty($assets)) {
            $am = $this->getAssetManager();
            $assets = is_array($assets) ? $assets : [$assets];
            foreach ($assets as $asset) {
                if (isset($am->bundles[$asset])) {
                    $this->registerAssetBundle($asset);
                }
            }
        }
    }

    /**
     * @param $url
     * @param string $bundleName
     *
     * @return string
     */
    public function getAssetUrl($url, $bundleName = 'default')
    {
        $bundle = $this->registerAssetBundle($bundleName);

        if (!$bundle) {
            return $url;
        }

        return $bundle->baseUrl . '/' . ltrim($url, '/');
    }

    /**
     * @param $block_id
     *
     * @return bool
     */
    public function hasBlock($block_id)
    {
        return !empty($this->blocks[$block_id]);
    }

    /**
     * @param $block_id
     * @param bool $return
     *
     * @return null
     */
    public function getBlock($block_id, $return = false)
    {
        if ($this->hasBlock($block_id)) {
            if ($return === true) {
                return $this->blocks[$block_id];
            }
            echo $this->blocks[$block_id];
        } else {
            return '';
        }
    }

    /**
     * @param string $viewFile
     * @param array $params
     *
     * @return bool
     * @throws ViewRender
     */
    public function beforeRender($viewFile, $params): bool
    {
        $ext = pathinfo($viewFile, PATHINFO_EXTENSION);
        if (!isset($this->renderers[$ext]) && !Yii::$app->getConfig()->get('allowRenderPHP') && strpos($viewFile, FileHelper::getAlias('@yii')) !== 0) {
            throw new ViewRender('You cannot render php file.');
        }

        return parent::beforeRender($viewFile, $params);
    }

    /**
     * @param string $view
     * @param null $context
     *
     * @return bool|string
     */
    protected function findViewFile($view, $context = null)
    {
        if (strncmp($view, '@', 1) === 0) {
            // e.g. "@app/views/main"
            $file = Yii::getAlias($view);
        } elseif (strncmp($view, '//', 2) === 0) {
            // e.g. "//layouts/main"
            $file = Yii::$app->getViewPath() . DIRECTORY_SEPARATOR . ltrim($view, '/');
        } elseif (strncmp($view, '/', 1) === 0) {
            // e.g. "/site/index"
            if (Yii::$app->controller !== null) {
                $file = Yii::$app->controller->module->getViewPath() . DIRECTORY_SEPARATOR . ltrim($view, '/');
            } else {
                throw new InvalidCallException("Unable to locate view file for view '$view': no active controller.");
            }
        } elseif ($context instanceof ViewContextInterface) {
            $file = $context->getViewPath() . DIRECTORY_SEPARATOR . $view;
        } elseif (($currentViewFile = $this->getViewFile()) !== false) {
            $file = dirname($currentViewFile) . DIRECTORY_SEPARATOR . $view;
        } else {
            throw new InvalidCallException("Unable to resolve view file for view '$view': no active view context.");
        }

        if (pathinfo($file, PATHINFO_EXTENSION) !== '') {
            return $file;
        }

        $path = $file . '.' . $this->defaultExtension;

        return $path;
    }
}