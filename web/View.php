<?php

namespace ej\web;


use Yii;
use ej\base\Theme;
use ej\helpers\Html;
use yii\base\ViewRenderer;
use ej\helpers\FileHelper;
use yii\base\InvalidCallException;
use yii\base\ViewContextInterface;
use yii\base\ViewNotFoundException;

/**
 * Class View
 *
 * @property Theme $theme
 * @method \ej\web\AssetManager      getAssetManager()
 */
class View extends \yii\web\View
{
    /**
     * @inheritdoc
     */
    const POS_HEAD_BEGIN = 0;
    /**
     * @inheritdoc
     */
    const PH_BEGIN_HEAD = '<![CDATA[YII-BLOCK-BEGIN-HEAD]]>';
    /**
     * @var
     */
    public $pageName;
    /**
     * @var array the view files currently being rendered. There may be multiple view files being
     * rendered at a moment because one view may be rendered within another.
     */
    private $_viewFiles = [];

    /**
     * @inheritdoc
     */
    public function beginHead()
    {
        echo self::PH_BEGIN_HEAD;
    }

    /**
     * @param bool $ajaxMode
     */
    public function endPage($ajaxMode = false)
    {
        $this->trigger(self::EVENT_END_PAGE);

        $content = ob_get_clean();

        echo strtr($content, [
            self::PH_BEGIN_HEAD => $this->renderHeadBeginHtml(),
            self::PH_HEAD       => $this->renderHeadHtml(),
            self::PH_BODY_BEGIN => $this->renderBodyBeginHtml(),
            self::PH_BODY_END   => $this->renderBodyEndHtml($ajaxMode),
        ]);

        $this->clear();
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
        if ($return === true) {
            return $this->hasBlock($block_id) ? $this->blocks[$block_id] : null;
        } elseif ($this->hasBlock($block_id)) {
            echo $this->blocks[$block_id];
        }
    }

    /**
     * @inheritdoc
     */
    protected function renderHeadBeginHtml()
    {
        $lines = [];
        if (!empty($this->jsFiles[self::POS_HEAD_BEGIN])) {
            $lines[] = implode("\n", $this->jsFiles[self::POS_HEAD_BEGIN]);
        }
        if (!empty($this->js[self::POS_HEAD_BEGIN])) {
            $lines[] = Html::script(implode("\n", $this->js[self::POS_HEAD_BEGIN]), ['type' => 'text/javascript']);
        }

        return empty($lines) ? '' : implode("\n", $lines);
    }

    /**
     * @param string $view
     * @param null $context
     *
     * @return string
     */
    protected function findViewFile($view, $context = null)
    {
        if (strncmp($view, '@', 1) === 0) {
            $file = FileHelper::getAlias($view);
        } elseif (strncmp($view, '//', 2) === 0) {
            $file = Yii::$app->getViewPath() . DIRECTORY_SEPARATOR . ltrim($view, '/');
        } elseif (strncmp($view, '/', 1) === 0) {
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

        return $file . '.' . $this->defaultExtension;
    }

    /**
     * @param string $viewFile
     * @param array $params
     * @param null $context
     *
     * @return string
     */
    public function renderFile($viewFile, $params = [], $context = null)
    {
        $viewFile = FileHelper::getAlias($viewFile);

        if ($this->theme !== null) {
            $viewFile = $this->theme->applyTo($viewFile);
        }
        if (is_file($viewFile)) {
            $viewFile = FileHelper::localize($viewFile);
        } else {
            throw new ViewNotFoundException("The view file does not exist: $viewFile");
        }

        $oldContext = $this->context;
        if ($context !== null) {
            $this->context = $context;
        }
        $output = '';
        $this->_viewFiles[] = $viewFile;

        if ($this->beforeRender($viewFile, $params)) {
            Yii::trace("Rendering view file: $viewFile", __METHOD__);
            $ext = pathinfo($viewFile, PATHINFO_EXTENSION);
            if (isset($this->renderers[$ext])) {
                if (is_array($this->renderers[$ext]) || is_string($this->renderers[$ext])) {
                    $this->renderers[$ext] = Yii::createObject($this->renderers[$ext]);
                }
                /* @var $renderer ViewRenderer */
                $renderer = $this->renderers[$ext];
                $output = $renderer->render($this, $viewFile, $params);
            } elseif (Yii::$app->getConfig()->get('renderPhpFile') || strpos($viewFile, FileHelper::getAlias('@yii')) === 0) {
                $output = $this->renderPhpFile($viewFile, $params);
            } else {
                throw new ViewNotFoundException("Could not find rendering engine for the view file: $viewFile");
            }

            $this->afterRender($viewFile, $params, $output);
        }

        array_pop($this->_viewFiles);
        $this->context = $oldContext;

        return $output;
    }

    /**
     * @return string|boolean the view file currently being rendered. False if no view file is being rendered.
     */
    public function getViewFile()
    {
        return end($this->_viewFiles);
    }
}