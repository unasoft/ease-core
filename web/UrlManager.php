<?php

namespace ease\web;

defined('CODE_URL_TEST') || define('CODE_URL_TEST', false);


use Yii;
use yii\web\Cookie;
use ej\base\Site;
use yii\web\Request;
use yii\caching\Cache;
use yii\web\UrlNormalizer;
use core\helpers\ArrayHelper;
use core\models\Site as SiteModel;
use yii\web\NotFoundHttpException;
use yii\base\InvalidConfigException;
use yii\web\UrlNormalizerRedirectException;

/**
 * Class UrlManager
 *
 * @property string $urlCacheKey
 *
 * @package core\web
 */
class UrlManager extends \yii\web\UrlManager
{
    /**
     * @var
     */
    public $ignoreCodeUrlPatterns = [];
    /**
     * @var array
     */
    public $codeCookieOptions = [];
    /**
     * @var array
     */
    private $_config = [
        'enableCodeUrls'        => true,
        'enableDefaultUrlCode'  => false,
        'enableCodePersistence' => false,
        'enableCodeDetection'   => true,
        'codeParam'             => '__code',
        'codeSessionKey'        => '__code',
        'codeCookieName'        => '__code',
        'codeCookieDuration'    => 2592000
    ];
    /**
     * @var bool
     */
    private $_processed = false;
    /**
     * @var Site
     */
    private $site;
    /**
     * @var \yii\web\Request
     */
    protected $_request;

    /**
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        if ($this->set($name, $value) === false) {
            return parent::__set($name, $value);
        }
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        if (array_key_exists($name, $this->_config)) {
            return $this->_config[$name];
        }

        return parent::__get($name);
    }

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->site = Yii::$app->getSite();

        if (empty($this->site->getCodes())) {
            $this->set('enableCodeUrls', false);
        }

        parent::init();
    }

    /**
     * @inheritdoc
     */
    public function parseRequest($request)
    {
        if ($this->get('enableCodeUrls')) {
            $process = true;
            $this->_request = $request;
            if ($this->ignoreCodeUrlPatterns) {
                $pathInfo = $request->getPathInfo();
                foreach ($this->ignoreCodeUrlPatterns as $k => $pattern) {
                    if (preg_match($pattern, $pathInfo)) {
                        Yii::trace("Ignore pattern '$pattern' matches '$pathInfo.' Skipping language processing.", __METHOD__);
                        $process = false;
                    }
                }
            }

            if ($process && !$this->_processed) {
                $this->_processed = true;
                $this->processCodeUrl();
            }
        }

        return parent::parseRequest($request);
    }

    /**
     * @inheritdoc
     */
    protected function processCodeUrl()
    {
        if (!$this->get('enableCodeUrls')) {
            return;
        }

        $pathInfo = $this->_request->getPathInfo();
        $pattern = implode('|', Yii::$app->getSite()->getCodes());

        if (preg_match("#^($pattern)\b(/?)#i", $pathInfo, $m)) {
            $this->_request->setPathInfo(mb_substr($pathInfo, mb_strlen($m[1] . $m[2])));
            $code = $m[1];

            if ($this->normalizer instanceof UrlNormalizer) {
                $normalized = false;
                $this->normalizer->normalizePathInfo($pathInfo, '/', $normalized);
                if ($normalized) {
                    $this->redirectToCode($code);
                }
            }

            if (!$this->get('enableDefaultUrlCode') && $code === $this->site->default()->code) {
                $this->redirectToCode('');
            }

            try {
                $this->site->switchSite($code);
            } catch (\ease\exceptions\Site $e) {
                return;
            }

            Yii::$app->language = $this->site->getConfig()->get('locale', Yii::$app->language);

            Yii::trace("Code found in URL. Setting application code '$code'.", __METHOD__);

            if ($this->get('enableCodePersistence')) {
                Yii::$app->session[$this->get('codeSessionKey')] = $code;
                Yii::trace("Persisting code '$code' in session.", __METHOD__);
                if ($this->get('codeCookieDuration')) {
                    $cookie = new Cookie(array_merge(
                        ['httpOnly' => true],
                        $this->get('codeCookieOptions'),
                        [
                            'name'   => $this->get('codeCookieName'),
                            'value'  => $code,
                            'expire' => time() + (int)$this->get('codeCookieDuration'),
                        ]
                    ));
                    Yii::$app->getResponse()->getCookies()->add($cookie);
                    Yii::trace("Persisting code '$code' in cookie.", __METHOD__);
                }
            }
        } else {
            $code = null;
            if ($this->get('enableCodePersistence')) {
                $code = Yii::$app->session->get($this->get('codeSessionKey'));
                $code !== null && Yii::trace("Found persisted code '$code' in session.", __METHOD__);
                if ($code === null) {
                    $code = $this->_request->getCookies()->getValue($this->get('codeCookieName'));
                    $code !== null && Yii::trace("Found persisted code '$code' in cookie.", __METHOD__);
                }
            }

            if ($code === null && $this->get('enableCodeDetection')) {
                foreach ($this->_request->getAcceptableLanguages() as $acceptable) {
                    $code = $this->matchCode($acceptable);
                    if ($code !== null) {
                        Yii::trace("Detected site code '$code'.", __METHOD__);
                        break;
                    }
                }
            }

            $defaultSite = $this->site->default();

            if (!$defaultSite) {
                return;
            }

            if ($code === null || $code === $defaultSite->code) {
                if ($defaultSite->getConfig()->has('locale')) {
                    Yii::$app->language = $defaultSite->getConfig()->get('locale');
                }
                if (!$this->get('enableDefaultUrlCode')) {
                    return;
                } else {
                    $code = $defaultSite->code;
                }
            }

            $this->redirectToCode($code);
        }
    }

    /**
     * @inheritdoc
     */
    public function createUrl($params)
    {
        if ($this->get('enableCodeUrls')) {
            if ($this->ignoreCodeUrlPatterns) {
                $params = (array)$params;
                $route = trim($params[0], '/');
                foreach ($this->ignoreCodeUrlPatterns as $pattern => $v) {
                    if (preg_match($pattern, $route)) {
                        return parent::createUrl($params);
                    }
                }
            }

            $params = (array)$params;
            $isCodeGiven = isset($params[$this->get('codeParam')]);
            $code = $isCodeGiven ? $params[$this->get('codeParam')] : $this->site->default()->code;
            $isDefaultCode = $code === $this->site->default()->code;

            if ($isCodeGiven) {
                unset($params[$this->get('codeParam')]);
            }

            $url = parent::createUrl($params);

            if (!empty($code) && (!$isDefaultCode || $this->get('enableDefaultUrlCode') || $isCodeGiven && ($this->get('enableCodePersistence') || $this->get('enableCodeDetection')))) {
                $key = array_search($code, $this->site->getCodes());
                if (is_string($key)) {
                    $code = $key;
                }

                $prefix = $this->showScriptName ? $this->getScriptUrl() : $this->getBaseUrl();
                $insertPos = strlen($prefix);

                if ($this->suffix !== '/') {
                    if (count($params) === 1) {
                        if ($url === $prefix . '/') {
                            $url = rtrim($url, '/');
                        }
                    } elseif (strncmp($url, $prefix . '/?', $insertPos + 2) === 0) {
                        $url = substr_replace($url, '', $insertPos, 1);
                    }
                }

                if (strpos($url, '://') !== false) {
                    if (($pos = strpos($url, '/', 8)) !== false || ($pos = strpos($url, '?', 8)) !== false) {
                        $insertPos += $pos;
                    } else {
                        $insertPos += strlen($url);
                    }
                }
                if ($insertPos > 0) {
                    return substr_replace($url, '/' . $code, $insertPos, 0);
                } else {
                    return '/' . $code . $url;
                }
            } else {
                return $url;
            }
        } else {
            return parent::createUrl($params);
        }
    }

    /**
     * @inheritdoc
     */
    public function set($key, $value)
    {
        if (array_key_exists($key, $this->_config)) {
            $this->_config[$key] = $value;
            return true;
        }

        return false;
    }

    /**
     * @param $key
     * @param null $default
     *
     * @return mixed|null
     */
    public function get($key, $default = null)
    {
        return array_key_exists($key, $this->_config) ? $this->_config[$key] : $default;
    }

    /**
     * @param $locale
     *
     * @return null
     */
    protected function matchCode($locale)
    {
        if (!$this->get('enableCodeUrls')) {
            return null;
        }

        $site = $this->site->findByLocale($locale);

        return $site ? $site->code : null;
    }

    /**
     * @param $code
     *
     * @throws NotFoundHttpException
     * @throws \yii\base\Exception
     */
    protected function redirectToCode($code)
    {
        try {
            $result = parent::parseRequest($this->_request);
        } catch (UrlNormalizerRedirectException $e) {
            if (is_array($e->url)) {
                $params = $e->url;
                $route = array_shift($params);
                $result = [$route, $params];
            } else {
                $result = [$e->url, []];
            }
        }
        if ($result === false) {
            throw new \yii\web\NotFoundHttpException(Yii::t('yii', 'Page not found.'));
        }

        list ($route, $params) = $result;

        if ($code) {
            $params[$this->get('codeParam')] = $code;
        }

        $params = $params + $this->_request->getQueryParams();
        $routeCrete = $params;
        array_unshift($routeCrete, $route);

        $url = $this->createUrl($routeCrete);

        if (count($params) === 1 && array_key_exists($this->get('codeParam'), $params)) {
            $url = rtrim($url, '/') . '/';
        }

        if ($url === $this->_request->url) {
            return;
        }
        Yii::trace("Redirecting to $url.", __METHOD__);
        Yii::$app->getResponse()->redirect($url);
        if (CODE_URL_TEST) {
            throw new \yii\base\Exception(\ease\helpers\Url::to($url));
        } else {
            Yii::$app->end();
        }
    }
}
