<?php
/**
 * Created by PhpStorm.
 * User: egorov
 * Date: 13.08.2015
 * Time: 10:43
 */
namespace samsonphp\compressor;

/**
 * Module compression resource management
 * @package samsonphp\compressor
 */
class Resource
{
    /** @var string[] Collection of ignored file extensions */
    public $ignoredExtensions = array('php', 'js', 'css', 'md', 'map', 'dbs', 'vphp', 'less' , 'gz', 'lock', 'json', 'sql', 'xml', 'yml');

    /** @var string[] Collection of ignored files */
    public $ignoredFiles = array('.project', '.buildpath', '.gitignore', '.travis.yml', 'phpunit.xml', 'thumbs.db');

    /** @var string[] Collection of ignored folders */
    public $ignoredFolders = array('vendor', 'var', 'docs');

    /**
     * Define is this file is a valid resource
     * @param string $filePath File path
     * @return bool True if file is valid
     */
    protected function isValid($filePath)
    {
        // Does this file exists
        if (file_exists($filePath)) {
            // Check if this extension is valid
            $extension = pathinfo($filePath, PATHINFO_EXTENSION);
            if (!in_array($extension, $this->ignoredExtensions)) {
                // Check if this file is not ignored
                $fileName = pathinfo($filePath, PATHINFO_FILENAME);
                if (!in_array($fileName, $this->ignoredFiles)) {
                    // File is valid
                    return true;
                }
            }
        }

        // Not valid
        return false;
    }
}
