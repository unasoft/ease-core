<?php

namespace ej\base;


use Yii;
use yii\base\Component;
use yii\base\Exception;
use ej\helpers\FileHelper;
use yii\base\ErrorException;
use ej\helpers\ArrayHelper;

class Config extends Component
{
    /**
     * @var string
     */
    public $configFile = '@protected/runtime/config.php';
    /**
     * @var bool|null
     */
    private $_useFileLocks;

    /**
     * @var array
     */
    private $_configSettings = [
        'defaultDirMode'      => 775,
        'defaultFileMode'     => null,
        'useFileLocks'        => 'auto',
        'sendPoweredByHeader' => true,
        'allowRenderPHP'      => true,
        'useWriteFileLock'    => true
    ];
    /**
     * @var bool
     */
    private $_loaded = false;

    /**
     * @param $key
     * @param null $value
     *
     * @throws ErrorException
     */
    public function set($key, $value = null)
    {
        $this->_loadConfig();

        if (empty($key)) {
            return;
        }

        if (is_array($key) && $value === null) {
            $this->_configSettings = ArrayHelper::merge($this->_configSettings, $key);
        } else {
            $this->_configSettings[$key] = $value;
        }

        $configFile = FileHelper::getAlias($this->configFile);

        try {
            FileHelper::writeToFile($configFile, "<?php\n\nreturn " . var_export($this->_configSettings, true) . ";");
        } catch (\Exception $e) {
            throw new ErrorException('Tried to write to file at ' . $configFile . ', but the file is not writable.');
        }
    }

    /**
     * @param $item
     * @param null $default
     * @param bool $notEmpty
     *
     * @return mixed|null
     */
    public function get($item, $default = null, $notEmpty = true)
    {
        $this->_loadConfig();

        if (($notEmpty !== true && $this->exists($item)) || ($notEmpty === true && $this->exists($item) && !empty($this->_configSettings[$item]))) {
            return $this->_configSettings[$item];
        }

        return $default;
    }

    /**
     * Returns whether to use file locks when writing to files.
     *
     * @return bool
     */
    public function getUseFileLocks()
    {
        if ($this->_useFileLocks !== null) {
            return $this->_useFileLocks;
        }

        if (is_bool($configVal = $this->get('useFileLocks'))) {
            return $this->_useFileLocks = $configVal;
        }

        // Do we have it cached?
        if (($cachedVal = Yii::$app->getCache()->get('useFileLocks')) !== false) {
            return $this->_useFileLocks = ($cachedVal === 'y');
        }

        // Try a test lock
        $this->_useFileLocks = false;

        try {
            $mutex = Yii::$app->getMutex();
            $name = uniqid('test_lock', true);
            if (!$mutex->acquire($name)) {
                throw new Exception('Unable to acquire test lock.');
            }
            if (!$mutex->release($name)) {
                throw new Exception('Unable to release test lock.');
            }
            $this->_useFileLocks = true;
        } catch (\Exception $e) {
            Yii::warning('Write lock test failed: ' . $e->getMessage(), __METHOD__);
        }

        // Cache for two months
        $cachedValue = $this->_useFileLocks ? 'y' : 'n';
        Yii::$app->getCache()->set('useFileLocks', $cachedValue, 5184000);

        return $this->_useFileLocks;
    }

    /**
     * @param $item
     *
     * @return bool
     */
    public function exists($item): bool
    {
        $this->_loadConfig();

        if (array_key_exists($item, $this->_configSettings)) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isInstalled()
    {
        return $this->exists('db');
    }

    /**
     * @throws Exception
     */
    public function __clone()
    {
        throw new Exception('This class cannot be cloned.');
    }

    /**
     * @inheritdoc
     */
    private function _loadConfig()
    {
        if ($this->_loaded === false) {
            try {
                $this->_loaded = true;
                $configFile = FileHelper::getAlias($this->configFile);
                if (is_file($configFile)) {
                    $config = require($configFile);
                    if (!empty($config) && is_array($config)) {
                        $this->_configSettings = ArrayHelper::merge($this->_configSettings, $config);
                    }
                }
            } catch (\Exception $e) {
            }
        }
    }
}