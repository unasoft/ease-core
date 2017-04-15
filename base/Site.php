<?php

namespace ej\base;


use ej\backend\web\Application;
use Yii;
use yii\base\Component;
use core\models\Domains;
use yii\base\Exception;
use core\models\SiteConfig;

class Site extends Component
{
    /**
     * @var
     */
    private $_defaultSite;
    /**
     * @var
     */
    private $_site = [];
    /**
     * @var SiteConfig
     */
    private $_config;
    /**
     * @var
     */
    private $_data;


    /**
     * @inheritdoc
     */
    public function switchSiteByCode($code, $throwException = true)
    {
        $this->load();

        if (isset($this->_data['codes'][$code])) {
            $site_id = $this->_data['codes'][$code];
            $this->_site = $this->_data['sites'][$site_id];
            $this->_config = null;
        } elseif ($throwException) {
            throw new Exception('site error');
        }
    }

    /**
     * @return integer|null
     */
    public function getId()
    {
        $this->load();

        return isset($this->_site['site_id']) ? (int)$this->_site['site_id'] : null;
    }

    /**
     * @inheritdoc
     */
    public function getCodes()
    {
        $this->load();

        return isset($this->_data['codes']) ? array_keys($this->_data['codes']) : [];
    }

    /**
     * @return SiteConfig
     */
    public function getConfig()
    {
        if ($this->_config == null) {
            $this->_config = new SiteConfig($this->getId());
        }

        return $this->_config;
    }

    /**
     * @inheritdoc
     */
    public function defaultSite()
    {
        $this->load();

        if ($this->_defaultSite === null) {
            $default = null;
            $this->_defaultSite = clone $this;
            if (!empty($this->_data['sites'])) {
                foreach ($this->_data['sites'] as $site) {
                    if ($site['is_default']) {
                        $default = $site;
                    }
                }
            }
            $this->_defaultSite->_site = $default ? $default : [];
        }

        return $this->_defaultSite;
    }

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
            $this->load();

            return $this->__get($name) !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function __clone()
    {
        $this->_data = [];
        $this->_config = null;

        parent::__clone();
    }

    /**
     * @inheritdoc
     */
    protected function load()
    {
        if ($this->_data === null) {
            $sites = Domains::getSitesByHost(Yii::$app->getRequest()->getHostName(), true);
            if (!empty($sites)) {
                foreach ($sites as $site) {
                    if ($site['is_default']) {
                        $this->_site = $site;
                        $this->_defaultSite = clone $this;
                        $this->_defaultSite->_site = $site;
                    }
                    $this->_data['sites'][$site['site_id']] = $site;
                    $this->_data['codes'][$site['code']] = $site['site_id'];
                }
            }
        }
    }
}