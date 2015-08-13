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
    /** @var  samsonphp\compressor\Compressor Parent compressing object */
    protected $parent;

    /** @var string[] Collection of ignored file extensions */
    protected $ignoredExtensions = array('php', 'js', 'css', 'md', 'map', 'dbs', 'vphp', 'less' , 'gz', 'lock', 'json', 'sql', 'xml', 'yml');

    /** @var string[] Collection of ignored files */
    protected $ignoredFiles = array('.project', '.buildpath', '.gitignore', '.travis.yml', 'phpunit.xml', 'thumbs.db');

    /** @var string[] Collection of ignored folders */
    protected $ignoredFolders = array('vendor', 'var', 'docs');

    /** @var string[] Collection of module paths for creating beautiful structure */
    protected $moduleFolders = array();

    /**
     * @param Compressor $compressor Pointer to parent compressing object
     * @param array $ignoredExtensions Collection of ignored file extensions
     * @param array $ignoredFiles Collection of ignored files
     * @param array $ignoredFolders Collection of ignored folders
     */
    public function __construct(Compressor & $compressor, $ignoredExtensions = array(), $ignoredFiles = array(), $ignoredFolders = array())
    {
        $this->parent = & $compressor;

        // TODO: Maybe we need to fully override defaults, or merge?

        // Merge passed parameters
        $this->ignoredExtensions = array_merge($this->ignoredExtensions, $ignoredExtensions);
        $this->ignoredFiles = array_merge($this->ignoredFiles, $ignoredFiles);
        $this->ignoredFolders = array_merge($this->ignoredFolders, $ignoredFolders);
    }

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
