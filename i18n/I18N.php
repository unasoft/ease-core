<?php

namespace ej\i18n;


use Yii;
use ResourceBundle;
use ej\helpers\FileHelper;

class I18N extends \yii\i18n\I18N
{
    /**
     * @var bool Whether the [PHP intl extension](http://php.net/manual/en/book.intl.php) is loaded.
     */
    private $_intlLoaded = false;

    /**
     * @var array|null All of the known locales
     * @see getAllLocales()
     */
    private $_allLocaleIds;


    /**
     * @inheritdoc
     */
    public function init()
    {
        if (!isset($this->translations['*'])) {
            $this->translations['*'] = [
                'class' => DbMessageSource::className(),
            ];
        }
        if (!isset($this->translations['app']) && !isset($this->translations['app*'])) {
            $this->translations['app'] = [
                'class' => DbMessageSource::className(),
            ];
        }

        parent::init();

        $this->_intlLoaded = extension_loaded('intl');
    }

    /**
     * Returns whether the [Intl extension](http://php.net/manual/en/book.intl.php) is loaded.
     *
     * @return bool Whether the Intl extension is loaded.
     */
    public function getIsIntlLoaded()
    {
        return $this->_intlLoaded;
    }

    /**
     * Returns a locale by its ID.
     *
     * @param string $localeId
     *
     * @return Locale
     */
    public function getLocaleById($localeId)
    {
        return new Locale($localeId);
    }

    /**
     * Returns an array of all known locale IDs.
     *
     * If the [PHP intl extension](http://php.net/manual/en/book.intl.php) is loaded, then this will be based on
     * all of the locale IDs it knows about. Otherwise, it will be based on the locale data files located in
     * `vendor/craftcms/cms/src/config/locales/` and `config/locales/`.
     *
     * @return array An array of locale IDs.
     * @link http://php.net/manual/en/resourcebundle.locales.php
     */
    public function getAllLocaleIds()
    {
        if ($this->_allLocaleIds === null) {
            if ($this->getIsIntlLoaded()) {
                $this->_allLocaleIds = ResourceBundle::getLocales(null);
            } else {
                $appLocalesPath = APP_DIR . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'locales';
                $customLocalesPath = Yii::$app->getBasePath() . '/config/locales';

                $localeFiles = FileHelper::findFiles($appLocalesPath, [
                    'only'      => ['*.php'],
                    'recursive' => false
                ]);

                if (is_dir($customLocalesPath)) {
                    $localeFiles = array_merge($localeFiles, FileHelper::findFiles($customLocalesPath, [
                        'only'      => ['*.php'],
                        'recursive' => false
                    ]));
                }

                $this->_allLocaleIds = [];

                foreach ($localeFiles as $file) {
                    $this->_allLocaleIds[] = pathinfo($file, PATHINFO_FILENAME);
                }
            }

            // Hyphens, not underscores
            foreach ($this->_allLocaleIds as $i => $locale) {
                $this->_allLocaleIds[$i] = str_replace('_', '-', $locale);
            }
        }

        return $this->_allLocaleIds;
    }
}
