<?php

namespace ej\traits;


use Yii;
use ej\base\Site;
use ej\base\Config;
use yii\base\Behavior;
use ej\helpers\ArrayHelper;
use yii\base\InvalidConfigException;

trait Application
{
    /**
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        $setter = 'set' . $name;
        if (method_exists($this, $setter)) {
            // set property
            $this->$setter($value);

            return;
        } elseif (strncmp($name, 'on ', 3) === 0) {
            // on event: attach event handler
            $events = is_array($value) ? $value : [$value];
            foreach ($events as $event) {
                if (is_array($event)) {
                    $condition = ArrayHelper::remove($event, 'condition');
                    if (!$condition && isset($event[2])) {
                        $condition = ArrayHelper::remove($event, 2);
                    }
                    if (!$this->checkCondition($condition)) {
                        continue;
                    }
                }
                $this->on(trim(substr($name, 3)), $event);
            }
            return;
        } elseif (strncmp($name, 'as ', 3) === 0) {
            // as behavior: attach behavior
            $name = trim(substr($name, 3));
            $behaviors = is_array($value) ? $value : [$value];
            foreach ($behaviors as $behavior) {
                if (is_array($behavior)) {
                    $condition = ArrayHelper::remove($behavior, 'condition');
                    if (!$this->checkCondition($condition)) {
                        continue;
                    }
                }
                $this->attachBehavior($name, $behavior instanceof Behavior ? $behavior : Yii::createObject($behavior));
            }
            return;
        }

        parent::__set($name, $value);
    }

    /**
     * @param array $config
     */
    public function preInit(&$config)
    {
        if (!isset($config['components']['config'])) {
            $this->set('config', ['class' => '\ej\base\Config']);
        }

        if (!isset($config['components']['db'])) {
            $this->set('db', function () {
                if (!Yii::$app->getConfig()->isInstalled()) {
                    throw new InvalidConfigException('The "db" component can be used only when the application is installed.');
                }

                return Yii::createObject(Yii::$app->getConfig()->get('db'));
            });
        }

        parent::preInit($config);
    }

    /**
     * @inheritdoc
     */
    public function bootstrap()
    {
        if (!empty($this->bootstrap)) {
            foreach ($this->bootstrap as $key => $value) {
                if (is_array($value)) {
                    if ($this->checkCondition($value)) {
                        $this->bootstrap[$key] = ArrayHelper::getValue($value, 'run');
                    } else {
                        unset($this->bootstrap[$key]);
                    }
                }
            }
        }

        parent::bootstrap();
    }

    /**
     * @return null|Config
     */
    public function getConfig()
    {
        return $this->get('config');
    }

    /**
     * @return null|Site
     */
    public function getSite()
    {
        return $this->get('site');
    }

    /**
     * @return mixed
     */
    public function getMutex()
    {
        return $this->get('mutex');
    }

    /**
     * @param string $id
     * @param array|null|\yii\base\Module $module
     */
    public function setModule($id, $module)
    {
        $condition = ArrayHelper::remove($module, 'condition');

        if ($condition && !$this->checkCondition($condition)) {
            return;
        }

        parent::setModule($id, $module);
    }

    /**
     * @param array $modules
     */
    public function setModules($modules)
    {
        foreach ($modules as $id => $module) {
            $this->setModule($id, $module);
        }
    }

    /**
     * @param $condition
     * @param bool $default
     *
     * @return bool|mixed
     */
    protected function checkCondition($condition, $default = true)
    {
        $condition = is_array($condition) && array_key_exists('condition', $condition) ? $condition['condition'] : $condition;

        $evalPrefix = 'eval:';
        if (is_string($condition) && strpos($condition, $evalPrefix) === 0) {
            $expression = substr($condition, strlen($evalPrefix));
            return $this->evaluateExpression($expression);
        } elseif (is_string($condition)) {
            return $this->callCondition($condition, $default);
        } elseif (is_callable($condition)) {
            return call_user_func($condition);
        }

        return $default;
    }

    /**
     * @param $string
     * @param $default
     *
     * @return bool
     */
    protected function callCondition($string, $default)
    {
        switch ($string) {
            case 'dev':
                return YII_DEBUG;
                break;
            case '!dev':
                return !YII_DEBUG;
                break;
            case 'installed':
                return Yii::$app->getConfig()->isInstalled();
                break;
            case '!installed':
                return !Yii::$app->getConfig()->isInstalled();
                break;
            case 'adminRequest':
                return \Admin\Module::isAdminRequest();
                break;
            case '!adminRequest':
                return !\Admin\Module::isAdminRequest();
                break;
            default:
                return $default;
        }
    }

    /**
     * @param $expression
     *
     * @return mixed
     */
    private function evaluateExpression($expression)
    {
        return eval('return ' . $expression . ';');
    }
}