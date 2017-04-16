<?php

namespace ej\web;


use Yii;
use ej\base\Theme;
use ej\helpers\Html;
use ej\helpers\FileHelper;


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
     * @param string $viewFile
     * @param array $params
     *
     * @return bool
     * @throws ViewRenderException
     */
    public function beforeRender($viewFile, $params): bool
    {
        $ext = pathinfo($viewFile, PATHINFO_EXTENSION);
        if (!isset($this->renderers[$ext]) && !Yii::$app->getConfig()->get('allowRenderPHP') && strpos($viewFile, FileHelper::getAlias('@yii')) !== 0) {
            throw new ViewRenderException('You cannot render php file.');
        }

        return parent::beforeRender($viewFile, $params);
    }
}