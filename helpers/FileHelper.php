<?php

namespace ease\helpers;


use Yii;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use yii\base\ErrorException;
use yii\base\InvalidParamException;

class FileHelper extends \yii\helpers\FileHelper
{
    /**
     * Registers a path alias.
     *
     * @param $alias
     * @param $path
     */
    public static function setAlias($alias, $path)
    {
        Yii::setAlias($alias, $path);
    }

    /**
     * Translates a path alias into an actual path.
     *
     * @param $alias
     * @param bool $throwException
     *
     * @return bool|string
     */
    public static function getAlias($alias, $throwException = true)
    {
        return Yii::getAlias($alias, $throwException);
    }

    /**
     * @param string $path
     * @param string $ds
     *
     * @return string
     */
    public static function normalizePath($path, $ds = DIRECTORY_SEPARATOR): string
    {
        // Is this a UNC network share path?
        $isUnc = (strpos($path, '//') === 0 || strpos($path, '\\\\') === 0);

        // Normalize the path
        $path = parent::normalizePath($path, $ds);

        // If it is UNC, add those slashes back in front
        if ($isUnc) {
            $path = $ds . $ds . ltrim($path, $ds);
        }

        return $path;
    }

    /**
     * @inheritdoc
     */
    public static function copyDirectory($src, $dst, $options = [])
    {
        if (Yii::$app !== null) {
            if (!isset($options['fileMode'])) {
                $options['fileMode'] = Yii::$app->getConfig()->get('defaultFileMode');
            }

            if (!isset($options['dirMode'])) {
                $options['dirMode'] = Yii::$app->getConfig()->get('defaultDirMode');
            }
        }

        parent::copyDirectory($src, $dst, $options);
    }

    /**
     * @inheritdoc
     */
    public static function createDirectory($path, $mode = null, $recursive = true)
    {
        if ($mode === null && Yii::$app !== null) {
            $mode = Yii::$app->getConfig()->get('defaultDirMode');
        } else {
            $mode = 0775;
        }

        return parent::createDirectory($path, $mode, $recursive);
    }

    /**
     * @param string $path
     * @param null $permission
     *
     * @return bool
     */
    public static function setPermission(string $path, $permission = null): bool
    {
        if (is_dir($path) || is_file($path)) {
            if ($permission === null) {
                $permission = Yii::$app->getConfig()->get('defaultFileMode');
            }
            try {
                if (chmod($path, octdec($permission))) {
                    return true;
                };
            } catch (\Exception $e) {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Sanitizes a filename.
     *
     * @param string $filename the filename to sanitize
     * @param array $options   options for sanitization. Valid options are:
     *
     * - asciiOnly: bool, whether only ASCII characters should be allowed. Defaults to false.
     * - separator: string|null, the separator character to use in place of whitespace. defaults to '-'. If set to null, whitespace will be preserved.
     *
     * @return string The cleansed filename
     */
    public static function sanitizeFilename(string $filename, array $options = []): string
    {
        $asciiOnly = $options['asciiOnly'] ?? false;
        $separator = array_key_exists('separator', $options) ? $options['separator'] : '-';
        $disallowedChars = [
            'â€”',
            'â€“',
            '&#8216;',
            '&#8217;',
            '&#8220;',
            '&#8221;',
            '&#8211;',
            '&#8212;',
            '+',
            '%',
            '^',
            '~',
            '?',
            '[',
            ']',
            '/',
            '\\',
            '=',
            '<',
            '>',
            ':',
            ';',
            ',',
            '\'',
            '"',
            '&',
            '$',
            '#',
            '*',
            '(',
            ')',
            '|',
            '~',
            '`',
            '!',
            '{',
            '}'
        ];

        // Replace any control characters in the name with a space.
        $filename = preg_replace("/\\x{00a0}/iu", ' ', $filename);

        // Strip any characters not allowed.
        $filename = str_replace($disallowedChars, '', strip_tags($filename));

        if ($separator !== null) {
            $filename = preg_replace('/(\s|' . preg_quote($separator, '/') . ')+/u', $separator, $filename);
        }

        // Nuke any trailing or leading .-_
        $filename = trim($filename, '.-_');

        $filename = $asciiOnly ? StringHelper::toAscii($filename) : $filename;

        return $filename;
    }

    /**
     * Returns whether a given directory is empty (has no files) recursively.
     *
     * @param string $dir the directory to be checked
     *
     * @return bool whether the directory is empty
     * @throws InvalidParamException if the dir is invalid
     * @throws ErrorException in case of failure
     */
    public static function isDirectoryEmpty(string $dir): bool
    {
        if (!is_dir($dir)) {
            throw new InvalidParamException("The dir argument must be a directory: $dir");
        }

        if (!($handle = opendir($dir))) {
            throw new ErrorException("Unable to open the directory: $dir");
        }

        // It's empty until we find a file
        $empty = true;

        while (($file = readdir($handle)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_file($path) || !static::isDirectoryEmpty($path)) {
                $empty = false;
                break;
            }
        }

        closedir($handle);

        return $empty;
    }

    /**
     * Tests whether a file/directory is writable.
     *
     * @param string $path the file/directory path to test
     *
     * @return bool whether the path is writable
     * @throws ErrorException in case of failure
     */
    public static function isWritable(string $path): bool
    {
        // If it's a directory, test on a temp sub file
        if (is_dir($path)) {
            return static::isWritable($path . DIRECTORY_SEPARATOR . uniqid('test_writable', true) . '.tmp');
        }

        // Remember whether the file already existed
        $exists = file_exists($path);

        if (($f = @fopen($path, 'ab')) === false) {
            return false;
        }

        @fclose($f);

        // Delete the file if it didn't exist already
        if (!$exists) {
            static::removeFile($path);
        }

        return true;
    }

    /**
     * Writes contents to a file.
     *
     * @param string $file     the file path
     * @param string $contents the new file contents
     * @param array $options   options for file write. Valid options are:
     *
     * - createDirs: bool, whether to create parent directories if they do
     *   not exist. Defaults to true.
     * - append: bool, whether the contents should be appended to the
     *   existing contents. Defaults to false.
     * - lock: bool, whether a file lock should be used. Defaults to the
     *   "useWriteFileLock" config setting.
     *
     * @throws InvalidParamException if the parent directory doesn't exist and options[createDirs] is false
     * @throws ErrorException in case of failure
     */
    public static function writeToFile(string $file, string $contents, array $options = [])
    {
        $file = static::normalizePath($file);
        $dir = dirname($file);

        if (!is_dir($dir)) {
            if (!isset($options['createDirs']) || $options['createDirs']) {
                static::createDirectory($dir);
            } else {
                throw new InvalidParamException("Cannot write to \"{$file}\" because the parent directory doesn't exist.");
            }
        }

        if (isset($options['lock'])) {
            $lock = (bool)$options['lock'];
        } else {
            $lock = Yii::$app->getConfig()->getUseFileLocks();
        }

        if ($lock) {
            Yii::$app->getMutex()->acquire($file);
        }

        $flags = 0;
        if (!empty($options['append'])) {
            $flags |= FILE_APPEND;
        }

        if (file_put_contents($file, $contents, $flags) === false) {
            throw new ErrorException("Unable to write new contents to \"{$file}\".");
        }

        if ($lock) {
            Yii::$app->getMutex()->release($file);
        }
    }

    /**
     * Removes a file.
     *
     * @param string $file the file to be deleted
     *
     * @throws ErrorException in case of failure
     */
    public static function removeFile($file)
    {
        // Copied from [[removeDirectory()]]
        try {
            unlink($file);
        } catch (ErrorException $e) {
            if (DIRECTORY_SEPARATOR === '\\') {
                // last resort measure for Windows
                $lines = [];
                exec("DEL /F/Q \"$file\"", $lines, $deleteError);
            } else {
                throw $e;
            }
        }
    }

    /**
     * Removes all of a directory’s contents recursively.
     *
     * @param string $dir    the directory to be deleted recursively.
     * @param array $options options for directory remove. Valid options are:
     *
     * - traverseSymlinks: bool, whether symlinks to the directories should be traversed too.
     *   Defaults to `false`, meaning the content of the symlinked directory would not be deleted.
     *   Only symlink would be removed in that default case.
     *
     * @return void
     * @throws InvalidParamException if the dir is invalid
     * @throws ErrorException in case of failure
     */
    public static function clearDirectory($dir, array $options = [])
    {
        if (!is_dir($dir)) {
            throw new InvalidParamException("The dir argument must be a directory: $dir");
        }

        // Copied from [[removeDirectory()]] minus the root directory removal at the end
        if (!($handle = opendir($dir))) {
            return;
        }
        while (($file = readdir($handle)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                static::removeDirectory($path, $options);
            } else {
                static::removeFile($path);
            }
        }
        closedir($handle);
    }

    /**
     * Returns the last modification time for the given path.
     *
     * If the path is a directory, any nested files/directories will be checked as well.
     *
     * @param string $path the directory to be checked
     *
     * @return int Unix timestamp representing the last modification time
     */
    public static function lastModifiedTime($path)
    {
        if (is_file($path)) {
            return filemtime($path);
        }

        $times = [filemtime($path)];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $p => $info) {
            $times[] = filemtime($p);
        }

        return max($times);
    }
}