<?php

namespace ej\helpers;


class Yaml extends \Symfony\Component\Yaml\Yaml
{
    /**
     * @param string $input
     * @param int $flags
     *
     * @return mixed
     */
    public static function parse($input, $flags = 0)
    {
        if (is_file($input)) {
            $input = file_get_contents($input);
        }

        return parent::parse($input, $flags);
    }
}