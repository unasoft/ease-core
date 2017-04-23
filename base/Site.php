<?php

namespace ej\base;


use Yii;
use yii\base\Component;
use ej\base\site\Record;
use ej\exceptions\Site as SiteException;

class Site extends Component
{
    /**
     * @var
     */
    private $_sites;
    /**
     * @var
     */
    private $_siteId = 0;
    /**
     * @var
     */
    private $_defaultId = 0;
    /**
     * @var array
     */
    private $_codes = [];

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        $this->load();

        $getter = 'get' . $name;
        if (method_exists($this, $getter)) {
            return $this->$getter();
        }

        if (isset($this->_sites[$name]) || array_key_exists($name, $this->_sites)) {
            return $this->_sites[$name];
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
            $this->load();

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
        $this->load();

        $object = $this->_sites[$this->_siteId];
        if (method_exists($object, $name)) {
            return call_user_func_array([$object, $name], $params);
        }

        return parent::__call($name, $params);
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
    public function switchSiteByCode($code, $throwException = true)
    {
        $this->load();

        if (!empty($code) && array_key_exists($code, $this->_codes)) {
            $this->_siteId = $this->_codes[$code];
        } elseif ($throwException) {
            throw new SiteException('Switched code "' . $code . '" not found.');
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getCodes()
    {
        $this->load();

        return array_keys($this->_codes);
    }

    /**
     * @inheritdoc
     */
    public function defaultSite()
    {
        $this->load();

        return isset($this->_sites[$this->_defaultId]) ? $this->_sites[$this->_defaultId] : null;
    }

    /**
     * @inheritdoc
     */
    protected function load()
    {
        if ($this->_sites === null) {
            $sites = Record::find()->all();
            if (!empty($sites)) {
                foreach ($sites as $site) {
                    if ($site->is_default) {
                        $this->_defaultId = $this->_siteId = $site->getId();
                    }
                    $this->_sites[$site->getId()] = $site;
                    $this->_codes[$site->code] = $site->getId();
                }
            } else {
                $this->_sites[0] = new Record();
                $this->_codes['/'] = 0;
            }
        }
    }
}