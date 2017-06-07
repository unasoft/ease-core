<?php

namespace ease\helpers;

use Yii;
use core\i18n\Locale;


class Localization
{
    /**
     * @var
     */
    private static $_translations;

    /**
     * Normalizes a user-submitted number for use in code and/or to be saved into the database.
     *
     * Group symbols are removed (e.g. 1,000,000 => 1000000), and decimals are converted to a periods, if the current
     * locale uses something else.
     *
     * @param mixed $number The number that should be normalized.
     *
     * @return mixed The normalized number.
     */
    public static function normalizeNumber($number)
    {
        if (is_string($number)) {
            $locale = \Yii::$app->getLocale();
            $decimalSymbol = $locale->getNumberSymbol(Locale::SYMBOL_DECIMAL_SEPARATOR);
            $groupSymbol = $locale->getNumberSymbol(Locale::SYMBOL_GROUPING_SEPARATOR);

            // Remove any group symbols
            $number = str_replace($groupSymbol, '', $number);

            // Use a period for the decimal symbol
            $number = str_replace($decimalSymbol, '.', $number);
        }

        return $number;
    }

    /**
     * Returns fallback data for a locale if the Intl extension isn't loaded.
     *
     * @param string $localeId
     *
     * @return array|null
     */
    public static function getLocaleData($localeId)
    {
        $data = null;

        // Load the locale data
        $dataPath = FileHelper::getAlias('@app/config') . '/locales/' . $localeId . '.php';
        $customDataPath = FileHelper::getAlias('@var') . '/locales/' . $localeId . '.php';

        if (Io::fileExists($dataPath)) {
            $data = require($dataPath);
        }

        if (Io::fileExists($customDataPath)) {
            if ($data !== null) {
                $data = ArrayHelper::merge($data, require($customDataPath));
            } else {
                $data = require($customDataPath);
            }
        }

        return $data;
    }
}
