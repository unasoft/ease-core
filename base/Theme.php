<?php

namespace ease\base;


use Yii;
use ej\helpers\Yaml;
use ej\helpers\FileHelper;
use ej\helpers\ArrayHelper;
use ej\exceptions\ThemeRender;
use yii\base\InvalidConfigException;

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
     * @var
     */
    private $_themePath;

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
                if (strpos($to, $this->getBasePath()) === 0) {
                    $to = substr($to, strlen($this->getBasePath()));
                }
                $to = FileHelper::normalizePath($this->getThemePath() . '/' . ltrim($to, '/')) . DIRECTORY_SEPARATOR;
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
            throw new InvalidConfigException('Theme property "name" must be set.');
        }

        return $this->_name;
    }

    /**
     * @param $value
     */
    public function setThemePath($value)
    {
        $this->_themePath = $value;
    }

    /**
     * @inheritdoc
     */
    public function getThemePath()
    {
        if ($this->_themePath === null) {
            throw new InvalidConfigException('Theme property "themePath" must be set.');
        }

        return $this->getBasePath() . DIRECTORY_SEPARATOR . $this->_themePath;
    }

    /**
     * @return array|mixed
     * @throws InvalidConfigException
     */
    protected function themeBootstrap()
    {
        if ($this->getBasePath() !== null && ($theme = Yii::$app->getConfig()->get('theme', 'Default'))) {
            $config = [];
            $configFile = $this->getBasePath() . '/' . $theme . '/registration.yml';
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
            $themeName = ArrayHelper::remove($config, 'name');
            if ($themeName) {
                $this->setName($themeName);
            }
            $useDeviceTheme = ArrayHelper::remove($config, 'useDeviceTheme', false);
            if ($useDeviceTheme === true) {
                $device = Yii::$app->getDevice();
                if (!$device->isMobile() && !$device->isTablet()) {
                    if (array_key_exists('desktop', $config)) {
                        $config = ArrayHelper::getValue($config, 'desktop', []);
                    } else {
                        unset($config['tablet'], $config['mobile']);
                    }
                } elseif ($device->isTablet() && array_key_exists('tablet', $config)) {
                    $config = ArrayHelper::getValue($config, 'tablet', []);
                } else {
                    $config = ArrayHelper::getValue($config, 'mobile', []);
                }
            } else {
                unset($config['tablet'], $config['mobile']);
            }

            $themePath = ArrayHelper::remove($config, 'path');

            $this->setThemePath($themeName ? $theme . '/' . $themePath : $theme);
            $this->registerTheme($config);
        }
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
                    $sourcePath = $this->getThemePath() . '/assets';
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

    /**
     * @inheritdoc
     */
    protected function configure(array $config)
    {
        foreach ($config as $attribute => $value) {
            if ($this->canSetProperty($attribute)) {
                $this->$attribute = $value;
            }
        }
    }
}