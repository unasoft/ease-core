<?php

namespace ease\base;


use Yii;
use yii\caching\Cache;
use yii\base\Component;
use yii\base\UnknownMethodException;
use ej\models\site\Site as SiteModel;
use ej\exceptions\Site as SiteException;

class Site extends Component
{
    /**
     * @var
     */
    private $_site;
    /**
     * @var
     */
    private $_default;
    /**
     * @var array
     */
    private $_codes;

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        $getter = 'get' . $name;
        if (method_exists($this, $getter)) {
            return $this->$getter();
        }

        if (isset($this->_site[$name]) || array_key_exists($name, $this->_site)) {
            return $this->_site[$name];
        } else {
            return null;
        }
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function __isset($name)
    {
        try {
            return $this->__get($name) !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @param string $name
     * @param array $params
     *
     * @return mixed
     */
    public function __call($name, $params)
    {
        if ($this->_site instanceof SiteModel && method_exists($this->_site, $name)) {
            return call_user_func_array([$this->_site, $name], $params);
        }

        try {
            return parent::__call($name, $params);
        } catch (UnknownMethodException $e) {
            return null;
        }
    }

    /**
     * @inheritdoc
     */
    public function __clone()
    {
        throw new SiteException('Class "' . get_called_class() . '" cannot be cloned.');
    }

    /**
     * @inheritdoc
     */
    public function switchSite($id, $throwException = true)
    {
        if ($id && is_int($id)) {
            return $this->_site = $this->getById($id);
        } else if (!empty($id) && array_key_exists($id, $this->_codes)) {
            return $this->_site = $this->getByCode($id);
        }

        if ($throwException) {
            throw new SiteException('Switched code "' . $id . '" not found.');
        }
    }

    /**
     * @param $id
     *
     * @return mixed
     */
    public function getById($id)
    {
        return $this->getCache('site:' . $id, function () use ($id) {
            return SiteModel::find()->where(['site_id' => $id, 'is_active' => SiteModel::IS_ACTIVE])->one();
        });
    }

    /**
     * @param $code
     *
     * @return integer|null
     */
    public function getByCode($code)
    {
        $codes = $this->getCodesIds();

        return array_key_exists($code, $codes) ? $this->getById($codes[$code]) : null;
    }

    /**
     * @param $locale
     */
    public function findByLocale($locale)
    {

    }

    /**
     * @return array
     */
    public function getCodes()
    {
        return array_keys($this->getCodesIds());
    }

    /**
     * @inheritdoc
     */
    public function getCodesIds()
    {
        if ($this->_codes === null) {
            $this->_codes = $this->getCache('codes', function () {
                $codes = [];
                foreach (SiteModel::find()->where(['is_active' => SiteModel::IS_ACTIVE])->all() as $site) {
                    $codes[$site->code] = $site->getId();
                }
                return $codes;
            });
            if (!is_array($this->_codes)) {
                $this->_codes = [];
            }
        }

        return $this->_codes;
    }

    /**
     * @inheritdoc
     */
    public function default()
    {
        if ($this->_default === null) {
            $this->_default = $this->getCache('default', function () {
                return SiteModel::find()->where(['is_default' => 1])->one();
            });
        }

        return $this->_default;
    }

    /**
     * @param $key
     * @param $data
     *
     * @return mixed
     */
    protected function getCache($key, $data)
    {
        $cache = Yii::$app->getCache();

        if (!$cache instanceof Cache) {
            if (is_callable($data)) {
                return call_user_func($data);
            }

            return $data;
        }

        $key = get_called_class() . ':' . $key;

        return $cache->getOrSet($key, $data);
    }
}