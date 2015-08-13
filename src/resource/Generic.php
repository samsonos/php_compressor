<?php
/**
 * Created by PhpStorm.
 * User: egorov
 * Date: 13.08.2015
 * Time: 10:43
 */
namespace samsonphp\compressor\resource;

/**
 * Module compression resource management
 * @package samsonphp\compressor
 */
class Generic
{
    /** @var  \samsonphp\compressor\Compressor Parent compressing object */
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
     * @param \samsonphp\compressor\Compressor $compressor Pointer to parent compressing object
     * @param string[] $ignoredExtensions Collection of ignored file extensions
     * @param string[] $ignoredFiles Collection of ignored files
     * @param string[] $ignoredFolders Collection of ignored folders
     */
    public function __construct(\samsonphp\compressor\Compressor & $compressor, $ignoredExtensions = array(), $ignoredFiles = array(), $ignoredFolders = array())
    {
        $this->parent = & $compressor;

        // TODO: Maybe we need to fully override defaults, or merge?

        // Merge passed parameters
        $this->ignoredExtensions = array_merge($this->ignoredExtensions, $ignoredExtensions);
        $this->ignoredFiles = array_merge($this->ignoredFiles, $ignoredFiles);
        $this->ignoredFolders = array_merge($this->ignoredFolders, $ignoredFolders);
    }

    /**
     * Create folder, method use recursive approach for creating if
     * "folder/folder/.." is passed.
     * @param string $path Folder path
     * @param string $group Folder group(www-data)
     * @param int $mode Folder mode(0775)
     * @return int 1 - success, 0 - folder exists, -1 - errors
     */
    public function createFolderStructure($path, $group = 'www-data', $mode = 0775)
    {
        // If folder does not exists
        if (!file_exists($path)) {
            // Create folder with correct mode
            if (mkdir($path, $mode, true)) {
                // Change folder group
                chgrp($path, $group);

                return true;
            } else {
                return -1;
            }
        }

        // Folder already exists
        return false;
    }

    /**
     * Compress file resource
     * @param string $fromFilePath Source file path
     * @param string $toFilePath Destination file path
     */
    public function compress($fromFilePath, $toFilePath)
    {
        $this->parent->log(' - Compressing from file[##] to [##]', $fromFilePath, $toFilePath);

        // Compress only valid resources
        if ($this->isValid($fromFilePath)) {
            // Create folder structure in destination location if necessary
            $destinationFolderPath = dirname($toFilePath);
            if ($this->createFolderStructure($destinationFolderPath)) {
                $this->parent->log(' +- Created folder structure for [##] in [##]', dirname($fromFilePath), $destinationFolderPath);
            }

            // If destination file does not exists or source file has been modified
            if (!file_exists($toFilePath) || (filemtime($fromFilePath) <> filemtime($toFilePath))) {
                $this->parent->log(' +- Updated from file[##] to [##]', $fromFilePath, $toFilePath);
                copy($fromFilePath, $toFilePath);
            }

            // Sync destination file with source file
            if (is_writable($toFilePath)) {
                // Change destination file permission
                chmod($toFilePath, 0775);
                // Modify destination modification to match source
                touch($toFilePath, filemtime($fromFilePath));
            }
        }
    }

    /**
     * Define if this file is a valid resource
     * @param string $filePath File path
     * @return bool True if file is valid
     */
    protected function isValid($filePath)
    {
        $this->parent->log(' - Validating file[##]', $filePath);

        // Does this file exists
        if (file_exists($filePath)) {
            // Check if this extension is valid
            $extension = pathinfo($filePath, PATHINFO_EXTENSION);
            if (!in_array($extension, $this->ignoredExtensions)) {
                // Check if this file is not ignored
                $fileName = pathinfo($filePath, PATHINFO_FILENAME);
                if (!in_array($fileName, $this->ignoredFiles)) {
                    $this->parent->log(' +- File[##] is valid', $filePath);
                    // File is valid
                    return true;
                } else {
                    $this->parent->log(' +- File[##] is ignored', $filePath);
                }
            } else {
                $this->parent->log(' +- File[##] extension[##] is ignored', $filePath, $extension);
            }
        } else {
            $this->parent->log(' +- File[##] does not exists', $filePath);
        }

        // Not valid
        return false;
    }
}
