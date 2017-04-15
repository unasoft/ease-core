<?php

namespace ej\helpers;

class ArrayHelper extends \yii\helpers\ArrayHelper
{
    /**
     * @param $needle
     * @param array $haystack
     * @param null $strict
     *
     * @return boolean
     */
    public static function inArray($needle, array $haystack, $strict = null)
    {
        if (is_array($needle)) {
            foreach ($needle as $key) {
                if (in_array($key, $haystack, $strict)) {
                    return true;
                }
            }
        } elseif (is_string($needle)) {
            return in_array($needle, $haystack, $strict);
        }

        return false;
    }

    /**
     * @param array|object|string $object
     * @param array $properties
     * @param bool $recursive
     *
     * @return array|object|string
     */
    public static function toArray($object, $properties = [], $recursive = true)
    {
        if ($object === null) {
            return [];
        }

        if (is_string($object)) {
            // Split it on the non-escaped commas
            $object = preg_split('/(?<!\\\),/', $object);

            // Remove any of the backslashes used to escape the commas
            foreach ($object as $key => $val) {
                // Remove leading/trailing whitespace
                $val = trim($val);

                // Remove any backslashes used to escape commas
                $val = str_replace('\,', ',', $val);

                $object[$key] = $val;
            }

            // Remove any empty elements and reset the keys
            $object = array_merge(array_filter($object));

            return $object;
        }

        return parent::toArray($object, $properties, $recursive);
    }

    /**
     * Prepends or appends a value to an array.
     *
     * @param array &$arr
     * @param mixed $value
     *
     * @param boolean $prepend
     */
    public static function prependOrAppend(&$arr, $value, $prepend)
    {
        if ($prepend) {
            array_unshift($arr, $value);
        } else {
            array_push($arr, $value);
        }
    }

    /**
     * Filters empty strings from an array.
     *
     * @param array $arr
     *
     * @return array
     */
    public static function filterEmptyStringsFromArray($arr)
    {
        return array_filter($arr,
            ['\jw\helpers\ArrayHelper', '_isNotAnEmptyString']);
    }

    /**
     * Returns the first key in a given array.
     *
     * @param array $arr
     *
     * @return string|integer|null The first key, whether that is a number (if the array is numerically indexed) or a string, or null if $arr isn’t an array, or is empty.
     */
    public static function getFirstKey($arr)
    {
        if (is_array($arr)) {
            foreach ($arr as $key => $value) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Returns the first value in a given array.
     *
     * @param array $arr
     *
     * @return mixed|null
     */
    public static function getFirstValue($arr)
    {
        if (is_array($arr)) {
            foreach ($arr as $value) {
                return $value;
            }
        }

        return null;
    }

    /**
     * The array_filter() callback function for filterEmptyStringsFromArray().
     *
     * @param string $val
     *
     * @return boolean
     */
    private static function _isNotAnEmptyString($val)
    {
        return (mb_strlen($val) != 0);
    }
}