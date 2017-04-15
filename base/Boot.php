<?php

namespace ej\base;


use ej\helpers\Url;
use yii\base\Component;
use ej\helpers\FileHelper;
use ej\helpers\ArrayHelper;
use Symfony\Component\Yaml\Yaml;
use yii\base\InvalidCallException;
use yii\base\InvalidConfigException;

/**
 *
 * @property mixed $cacheFile
 * @property mixed $boot
 */
class Boot extends Component
{
    /**
     * @var string
     */
    public $app = 'site';
    /**
     * @var bool
     */
    public $cache = true;
    /**
     * @var array
     */
    private $_cacheFile = [
        'site'    => '@app/config/boot.php',
        'console' => '@app/console/boot.php'
    ];
    /**
     * @var array
     */
    private $_boots = [
        'site'    => [
            '@ej/config/application.yml',
            '@app/config/application.{yml,php}',
            '@app/modules/*/registration.{yml,php}',
            '@app/config/*-local.{yml,php}'
        ],
        'console' => [
            '@ej/console/config/application.yml',
            '@app/console/config/*-local.{php,yml}'
        ]
    ];
    /**
     * @var
     */
    private $_compiled = [];

    /**
     * Configurator constructor.
     *
     * @param array $config
     *
     * @throws InvalidConfigException
     */
    public function __construct($config = [])
    {
        parent::__construct($config);

        if (YII_DEBUG) {
            $this->cache = false;
        }

        if (empty($this->app)) {
            throw new InvalidConfigException('`' . get_class($this) . '::app` must be set.');
        }

        if ($this->cache === true && $this->getCacheFile($this->app) === null) {
            throw new InvalidConfigException('`' . get_class($this) . '::cacheFile` must be set.');
        }
    }

    /**
     * @inheritdoc
     */
    public function __clone()
    {
        throw new InvalidCallException('`' . get_called_class() . '` cannot be cloned.');
    }

    /**
     * @inheritdoc
     */
    public function setCacheFile($file, $app = null)
    {
        $app = $app === null ? $this->app : $app;

        $this->_cacheFile[$app] = FileHelper::getAlias($file);
    }

    /**
     * @param string $app
     *
     * @return array
     */
    public function getCacheFile(string $app)
    {
        return array_key_exists($app, $this->_cacheFile) ? $this->_cacheFile[$app] : [];
    }

    /**
     * @param $value
     * @param null $app
     * @param bool $clear
     */
    public function setBoot($value, $app = null, $clear = false)
    {
        $app = $app === null ? $this->app : $app;

        if ($clear) {
            $this->_boots[$app] = is_array($value) ? $value : [$value];
        } else if (is_string($value)) {
            $this->_boots[$app][] = $value;
        } else {
            foreach ($value as $v) {
                $this->_boots[$app][] = $v;
            }
        }
    }

    /**
     * @param $app
     *
     * @return array|mixed
     */
    public function getBoot($app)
    {
        return array_key_exists($app, $this->_boots) ? $this->_boots[$app] : [];
    }

    /**
     * @param string @class
     * @param array $config
     *
     * @return array
     * @throws InvalidConfigException
     */
    public function apply(string $class, array $config = [])
    {
        try {
            $compiled = $this->compile();
            if (!empty($compiled)) {
                $config = ArrayHelper::merge($compiled, $config);
            }
            return new $class($config);
        } catch (\Exception $e) {
            throw new InvalidConfigException($e->getMessage());
        }
    }

    /**
     * @param $data
     *
     * @return array
     */
    public function processPHP($data): array
    {
        return is_array($data) ? $data : [];
    }

    /**
     * @param $data
     *
     * @return array
     */
    public function processYml($data): array
    {
        $parse = Yaml::parse($data, Yaml::PARSE_CONSTANT);

        return is_array($parse) ? $parse : [];
    }

    /**
     * @return array
     * @throws InvalidConfigException
     */
    protected function compile(): array
    {

        if ($this->cache && file_exists($this->getCacheFile($this->app)) && is_array(($compiled = require($this->getCacheFile($this->app))))) {
            return $compiled;
        }

        $config = $this->registration($this->getBoot($this->app));

        foreach ($config as $lines) {
            if (!empty($lines)) {
                $this->parse($lines);
            }
        }

        $this->makeBootstrap($this->_compiled);

        if ($this->cache) {
            $compiledDir = pathinfo($this->cacheFile, PATHINFO_DIRNAME);
            if (!FileHelper::createDirectory($compiledDir) || !file_put_contents($this->cacheFile, "<?php\n\nreturn " . var_export($this->_compiled, true) . ";\n\n?>")) {
                throw new InvalidConfigException('Error save compiled application configuration.');
            }
        }

        return $this->_compiled;
    }

    /**
     * @param $paths
     *
     * @return array
     */
    protected function registration($paths): array
    {
        if (empty($paths)) {
            return [];
        }
        $data = [];
        foreach ($paths as $path) {
            $autoloadPath = FileHelper::getAlias($path, false);
            if (!$autoloadPath) {
                continue;
            }
            $files = glob($autoloadPath, GLOB_NOSORT | GLOB_BRACE);
            if ($files === false) {
                throw new \RuntimeException('glob() returned error while searching in \'' . $path . '\'');
            }
            foreach ($files as $file) {
                $ext = pathinfo($file, PATHINFO_EXTENSION);
                switch ($ext) {
                    case "yml":
                        $data[] = $this->processYml(file_get_contents($file));
                        break;
                    default:
                        $data[] = $this->processPHP(require($file));
                }
            }
        }

        return $data;
    }

    /**
     * @inheritdoc
     */
    protected function parse($line)
    {
        foreach ($line as $name => $value) {
            $to = $name;
            if (strncmp($name, 'on ', 3) === 0) {
                $to = 'event';
            } elseif (strncmp($name, 'as ', 3) === 0) {
                $to = 'behavior';
            }
            switch ($to) {
                case 'bootstrap':
                    $this->parseBootstrap($name, $value);
                    break;
                case'aliases':
                    $this->parseAliases($name, $value);
                    break;
                case 'routes':
                    $this->parseRoutes($name, $value);
                    break;
                case 'event':
                    $this->parseEvent($name, $value);
                    break;
                case 'behavior':
                    $this->parseBehavior($name, $value);
                    break;
                case 'components':
                    $this->parseComponents($name, $value);
                    break;
                case 'module':
                case 'modules':
                    $this->parseModules($name, $value);
                    break;
                default:
                    $this->parseDefault($name, $value);
            }
        }
    }

    /**
     * @param $name
     * @param $bootstraps
     */
    protected function parseBootstrap($name, $bootstraps)
    {
        $bootstraps = is_array($bootstraps) ? $bootstraps : [$bootstraps];

        foreach ($bootstraps as $key => $bootstrap) {
            if (is_string($bootstrap)) {
                $bootstraps[$key] = ['run' => $bootstrap, 'weight' => 0];
            } elseif (!array_key_exists('weight', $bootstrap)) {
                $bootstraps[$key]['weight'] = 0;
            }
        }

        if (array_key_exists($name, $this->_compiled)) {
            $this->_compiled[$name] = ArrayHelper::merge($this->_compiled[$name], $bootstraps);
        } else {
            $this->_compiled[$name] = $bootstraps;
        }
    }

    /**
     * @param $name
     * @param $value
     */
    protected function parseAliases($name, $value)
    {
        if (array_key_exists($name, $this->_compiled)) {
            $this->_compiled[$name] = ArrayHelper::merge($this->_compiled[$name], $value);
        } else {
            $this->_compiled[$name] = $value;
        }
    }

    /**
     * @param $name
     * @param $routes
     */
    protected function parseRoutes($name, $routes)
    {
        if (is_array($routes)) {
            foreach ($routes as $key => $route) {
                if (is_string($key)) {
                    $this->_compiled['components']['urlManager']['rules'][$key] = $route;
                } else {
                    $this->_compiled['components']['urlManager']['rules'][] = $route;
                }
            }
        }
    }

    /**
     * @param $name
     * @param $value
     */
    protected function parseEvent($name, $value)
    {
        $value = is_array($value) ? $value : [$value];

        if (array_key_exists($name, $this->_compiled)) {
            $this->_compiled[$name] = ArrayHelper::merge($this->_compiled[$name], $value);
        } else {
            $this->_compiled[$name] = $value;
        }
    }

    /**
     * @param $name
     * @param $behaviors
     */
    protected function parseBehavior($name, $behaviors)
    {
        $behaviors = is_array($behaviors) ? $behaviors : [$behaviors];

        foreach ($behaviors as $behavior) {
            if (is_string($behavior)) {
                $behavior = ['class' => $behavior];
            }
            $this->_compiled[$name][] = $behavior;
        }
    }

    /**
     * @param $name
     * @param $components
     */
    protected function parseComponents($name, $components)
    {
        if (is_array($components)) {
            foreach ($components as $id => $component) {
                $this->_compiled[$name][$id] = $component;
            }
        }
    }

    /**
     * @param $name
     * @param $modules
     */
    protected function parseModules($name, $modules)
    {
        if ($name === 'modules') {
            foreach ($modules as $id => $module) {
                if (is_array($module) && is_string($id)) {
                    $module['id'] = ArrayHelper::remove($module, 'id', $id);
                } elseif (is_string($id) && is_string($module)) {
                    $module = ['id' => $id, 'class' => $module];
                }
                $this->parseModules('module', $module);
            }
        } else {
            $id = ArrayHelper::remove($modules, 'id');
            $class = ArrayHelper::remove($modules, 'class');

            if ($id && is_string($id) && $class) {
                $this->_compiled['modules'][$id] = !empty($modules) ? ArrayHelper::merge(['class' => $class], $modules) : $class;
            }
        }
    }

    /**
     * @param $name
     * @param $value
     */
    protected function parsePath($name, $value)
    {
        $this->_compiled[$name] = FileHelper::getAlias($value);
    }

    /**
     * @param $name
     * @param $value
     */
    protected function parseDefault($name, $value)
    {
        if (is_string($value) && strncmp($value, '@', 1) === 0 && ($alias = FileHelper::getAlias($value, false)) !== false) {
            $value = $alias;
        }

        $this->_compiled[$name] = $value;
    }

    /**
     * @param $config
     */
    protected function makeBootstrap(&$config)
    {
        if (array_key_exists('bootstrap', $config) && is_array($config['bootstrap'])) {
            $data = ArrayHelper::remove($config, 'bootstrap');
            $bootstraps = [];

            usort($data, function ($a, $b) {
                if (is_string($a)) {
                    return 0;
                }
                if (is_array($a) && $a['weight'] > 0 && is_string($b)) {
                    return -1;
                }
                if (is_string($a) && is_string($b) || $a['weight'] == $b['weight']) {
                    return 0;
                }
                return ($a['weight'] < $b['weight']) ? 1 : -1;
            });

            foreach ($data as $bootstrap) {
                if (is_array($bootstrap)) {
                    $run = ArrayHelper::remove($bootstrap, 'run');
                    $condition = ArrayHelper::remove($bootstrap, 'condition');
                    if ($run) {
                        $bootstrap = ['run' => $run];
                        if ($condition) {
                            $bootstrap['condition'] = $condition;
                        }
                        $bootstraps[] = $bootstrap;
                    }
                } else {
                    $bootstraps[] = $bootstrap;
                }
            }

            $config['bootstrap'] = $bootstraps;
        }
    }
}