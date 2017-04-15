<?php

namespace ej\base;

use Yii;
use ej\helpers\FileHelper;
use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;

class Theme extends \yii\base\Theme
{
    /**
     * @var array the mapping between view directories and their corresponding themed versions.
     * This property is used by [[applyTo()]] when a view is trying to apply the theme.
     * Path aliases can be used when specifying directories.
     * If this property is empty or not set, a mapping [[Application::basePath]] to [[basePath]] will be used.
     */
    public $pathMap;
    /**
     * Show exception if view file not exists, default false.
     *
     * @var bool
     */
    public $skipOnError = true;
    /**
     * Theme name
     *
     * @var
     */
    private $_name;

    /**
     * @param string $path
     * @param bool $includeMap
     * @return string
     */
    public function applyTo($path, $includeMap = false)
    {
        if (($themeName = $this->getName()) !== null) {
            if (($basePath = $this->getBasePath()) === null) {
                $this->setBasePath('@themes');
            }

            $pathMap = $this->pathMap;

            if (empty($pathMap)) {
                return $path;
            }

            $path = FileHelper::normalizePath($path);

            foreach ($pathMap as $from => $to) {
                $map = ['from' => $from, 'to' => $to];
                $from = FileHelper::normalizePath(FileHelper::getAlias($from)) . DIRECTORY_SEPARATOR;

                if (strpos($path, $from) === 0) {

                    $n = strlen($from);
                    $parts = explode(DIRECTORY_SEPARATOR, substr($path, $n));

                    if (($pos = array_search('views', $parts)) !== false) {
                        unset($parts[$pos]);
                    }

                    $filePath = implode('/', $parts);

                    if (is_callable($to)) {
                        $to = call_user_func_array($to, [$path, $from, $filePath, $themeName]);
                        if (is_string($to) && !empty($to)) {
                            $filePath = $to;
                        } else {
                            throw new InvalidParamException("Callback function must be return string and not empty.");
                        }
                    } else {
                        $filePath = FileHelper::normalizePath(FileHelper::getAlias($to)) . DIRECTORY_SEPARATOR . $themeName . DIRECTORY_SEPARATOR . $filePath;
                    }

                    if (is_file($filePath)) {
                        if ($includeMap === true) {
                            return array_merge($map, ['file' => $filePath]);
                        } else {
                            return $filePath;
                        }
                    }
                }
            }

            if (isset($filePath) && !$this->skipOnError) {
                throw new InvalidParamException("The view file does not exist: $filePath");
            }
        }

        return $path;
    }

    /**
     * @return mixed
     * @throws InvalidConfigException
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * @param $value
     */
    public function setName($value)
    {
        $this->_name = $value;
    }
}