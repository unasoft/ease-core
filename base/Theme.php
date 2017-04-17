<?php

namespace ej\base;


use Yii;
use ej\helpers\FileHelper;
use ej\helpers\ArrayHelper;
use ej\exceptions\ThemeRender;
use Symfony\Component\Yaml\Yaml;
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
     * Theme constructor.
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        FileHelper::setAlias('@themes', $this->getBasePath());

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
                $to = FileHelper::normalizePath(Yii::getAlias($to)) . DIRECTORY_SEPARATOR;
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
     * @return string
     */
    public function getBasePath()
    {
        return rtrim(parent::getBasePath() . '/' . $this->getTheme(), '/');
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
                $config = Yii::$app->getCache()->getOrSet(md5($configFile), function () use ($configFile) {
                    if (file_exists($configFile)) {
                        return Yaml::parse($configFile, Yaml::PARSE_CONSTANT);
                    }
                    return [];
                });
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

        $this->configure($config);
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
                    'sourcePath'     => $sourcePath,
                    'basePath'       => $basePath,
                    'baseUrl'        => $baseUrl,
                    'css'            => ArrayHelper::getValue($bundle, 'css', []),
                    'js'             => ArrayHelper::getValue($bundle, 'js', []),
                    'jsOptions'      => ArrayHelper::getValue($bundle, 'jsOptions', []),
                    'cssOptions'     => ArrayHelper::getValue($bundle, 'cssOptions', []),
                    'publishOptions' => ArrayHelper::getValue($bundle, 'publishOptions', []),
                ];
            }
        }
    }

    /**
     * @param $properties
     *
     * @return $this
     */
    private function configure($properties)
    {
        foreach ($properties as $name => $value) {
            if ($this->canSetProperty($name)) {
                $this->$name = $value;
            }
        }

        return $this;
    }
}