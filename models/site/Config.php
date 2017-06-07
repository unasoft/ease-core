<?php

namespace ease\models\site;


class Config
{
    /**
     * @var array
     */
    private $_data = [];


    /**
     * @param $key
     *
     * @return bool
     */
    public function has($key): bool
    {
        return array_key_exists($key, $this->_data);
    }

    /**
     * @param $key
     * @param null $default
     *
     * @return mixed|null
     */
    public function get($key, $default = null)
    {
        return $this->_data[$key] ?? $default;
    }
}