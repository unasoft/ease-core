<?php

namespace ej\base;


use Yii;
use ej\helpers\Yaml;
use ej\helpers\FileHelper;
use ej\helpers\ArrayHelper;
use ej\exceptions\ThemeRender;
use yii\base\InvalidConfigException;
use yii\caching\FileDependency;

class Theme extends \yii\base\Theme
{
    /**
     * @var bool
     */
    public $skipOnError = false;
    /**
     * @var bool
     */
    public $skipYiiOnError = true;
    /**
     * @var
     */
    private $_name;

    /**
     * Theme constructor.
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        $this->themeBootstrap();
    }

    /**
     * @param string $path
     *
     * @return string
     * @throws InvalidConfigException
     * @throws ThemeRender
     */
    public function applyTo($path)
    {
        $pathMap = $this->pathMap;
        if (empty($pathMap)) {
            if (($basePath = $this->getBasePath()) === null) {
                throw new InvalidConfigException('The "basePath" property must be set.');
            }
            $pathMap = [Yii::$app->getBasePath() => [$basePath]];
        }

        $path = FileHelper::normalizePath($path);

        foreach ($pathMap as $from => $to) {
            $from = FileHelper::normalizePath(Yii::getAlias($from)) . DIRECTORY_SEPARATOR;
            if (strpos($path, $from) === 0) {
                $n = strlen($from);
                if (strpos($to, $this->getBasePath()) !== 0 && strncmp($to, '@', 1)) {
                    $to = $this->getBasePath() . '/' . $to;
                }
                $to = FileHelper::normalizePath(Yii::getAlias($to)) . DIRECTORY_SEPARATOR;
                $to = strtr($to, ['{theme}' => $this->getTheme()]);
                $file = $to . substr($path, $n);
                if (is_file($file)) {
                    return $file;
                }
            }
        }
        $isYii = strpos($path, FileHelper::getAlias('@yii')) === 0;
        if (isset($file) && ((!$this->skipYiiOnError && $isYii) || (!$this->skipOnError && !$isYii))) {
            throw new ThemeRender("The view file does not exist: $file");
        }

        return $path;
    }

    /**
     * @param $name
     */
    public function setName($name)
    {
        $this->_name = $name;
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        if ($this->_name === null) {
            $this->_name = Yii::$app->getConfig()->get('theme', 'Default');
        }
        return $this->_name;
    }

    /**
     * @inheritdoc
     */
    public function getTheme()
    {
        if ($this->_name === null) {
            $this->_name = Yii::$app->getConfig()->get('theme', 'Default');
        }
        return $this->_name;
    }

    /**
     * @return array|mixed
     * @throws InvalidConfigException
     */
    protected function themeBootstrap()
    {
        $config = [];
        if ($this->getBasePath() !== null) {
            $configFile = $this->getBasePath() . '/' . $this->getTheme() . '/registration.yml';
            try {
                $config = Yaml::parse($configFile);
                if (!is_array($config)) {
                    return;
                }
            } catch (\Exception $e) {
                if (YII_DEBUG) {
                    throw new InvalidConfigException($e->getMessage());
                } else {
                    Yii::error('Error parse theme registration file: ' . $configFile);
                }
            }
        }

        $this->registerTheme($config);
    }

    /**
     * @param array $config
     */
    protected function registerTheme(array $config)
    {
        if (isset($config['assets'])) {
            $assets = ArrayHelper::remove($config, 'assets', []);
            if (is_array($assets)) {
                $this->registerAssets($assets);
            }
        }
    }

    /**
     * @param $assets
     */
    protected function registerAssets(array $assets)
    {
        foreach ($assets as $name => $bundle) {
            if (is_string($name)) {
                $basePath = ArrayHelper::getValue($bundle, 'basePath');
                $baseUrl = ArrayHelper::getValue($bundle, 'baseUrl');
                $sourcePath = ArrayHelper::getValue($bundle, 'sourcePath');
                if (is_null($basePath) && is_null($baseUrl) && is_null($sourcePath)) {
                    $sourcePath = $this->getBasePath() . '/' . $this->getTheme() . '/assets';
                }

                Yii::$app->getAssetManager()->bundles[$name] = [
                    'class'          => 'ej\web\AssetBundle',
                    'sourcePath'     => $sourcePath,
                    'basePath'       => $basePath,
                    'baseUrl'        => $baseUrl,
                    'css'            => ArrayHelper::getValue($bundle, 'css', []),
                    'js'             => ArrayHelper::getValue($bundle, 'js', []),
                    'jsOptions'      => ArrayHelper::getValue($bundle, 'jsOptions', []),
                    'cssOptions'     => ArrayHelper::getValue($bundle, 'cssOptions', []),
                    'publishOptions' => ArrayHelper::getValue($bundle, 'publishOptions', []),
                    'depends'        => ArrayHelper::getValue($bundle, 'depends', []),
                ];
            }
        }
    }
}