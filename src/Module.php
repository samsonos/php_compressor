<?php
/**
 * Created by PhpStorm.
 * User: egorov
 * Date: 11.01.2015
 * Time: 9:18
 */

namespace samsonos\compressor;

use samsonphp\compressor\Code;
use samsonphp\compressor\Compressor;

/**
 * Class for automatic SamsonPHP module optimization|compression
 *
 * @package samsonos\compressor
 * @author Vitaly Iegorov <egorov@samsonos.com>
 */
class Module
{
    /** @var \samson\core\Core Core pointer */
    protected $core;

    /** @var \samson\core\CompressableExternalModule Module pointer */
    protected $module;

    /** @var  Object Logger object */
    protected $logger;

    /** @var  array Collection of module PHP code */
    protected $code = array();

    /** @var array Ignored resource extensions */
    public $ignoredExtensions = array(
        'php', 'js', 'css', 'md', 'map', 'dbs', 'vphp', 'less' , 'gz', 'lock', 'json', 'sql', 'xml', 'yml'
    );

    /**
     * @param \samson\core\Core     $core Core pointer
     * @param \samson\core\CompressableExternalModule   $module Module pointer
     * @param object $logger Logger object
     */
    public function __construct($core, $module, $logger = null)
    {
        $this->core = & $core;
        $this->module = & $module;
        $this->logger = & $logger;
    }

    /**
     * Perform module compression
     * @return bool True if module was successfully compressed
     */
    public function compress()
    {
        // Cache module ID
        $id = $this->module->id();

        $this->logger->log('  - Compressing module[##]', $id);

        // Call special method enabling module personal resource pre-management on compressing
        if ($this->module->beforeCompress($this, $this->code) !== false) {
            //$this->compressResources();
            //$this->compressView();
            //$this->compressTemplate();

            if (is_a($this->module->resourceMap, 'samson\core\ResourceMap')) {
                $sources = array_merge(
                    $this->module->resourceMap->php,
                    $this->module->resourceMap->controllers,
                    $this->module->resourceMap->models,
                    $this->module->resourceMap->globals
                );

                // Add module class files to array of sources
                foreach ($this->module->resourceMap->modules as $module) {
                    $sources = array_merge(array($module[1]), $sources);
                }

                // Create code collector
                $code = new Code($sources, $this->logger);
            }

            // Change module path, now all modules would be located at wwwroot folder
            //$this->module->path($id.'/');

            // Call special method enabling module personal resource post-management on compressing
            //$this->module->afterCompress($this, $this->code);

            return true;
        }

        $this->logger->log('  - Module[##] compression stopped', $id);

        return false;
    }

    public function compressResources()
    {
        // Build output module path
        $destination = $id == 'local' ? '' : $id.'/';

        // Build resource source path
        $source = $id == 'local' ? $this->module->path().__SAMSON_PUBLIC_PATH : $this->module->path();

        $this->log(' -> Copying resources from [##] to [##]', $module_path, $module_output_path);

        // Iterate module resources
        foreach ($this->module->resourceMap->resources as $extension => $resources) {
            // Iterate only allowed resource types
            if (!in_array( $extension , $this->ignored_extensions)) {
                foreach ( $resources as $resource ) {
                    // Get only filename
                    $filename = basename( $resource );

                    // Copy only allowed resources
                    if (!in_array( $filename, $this->ignored_resources)) {
                        // Build relative module resource path
                        $relative_path = str_replace($module_path, '', $resource);

                        // Build correct destination folder
                        $dst = $this->output.$module_output_path.$relative_path;

                        // Copy/update file if necessary
                        $this->copy_resource( $resource, $dst );
                    }
                }
            }
        }

        // Copy all module resources
        $this->copy_path_resources($this->module->resourceMap->resources, $source, $destination);
    }

    public function compressView()
    {
        // Iterate all views
        foreach ($this->module->resourceMap->views as $view) {

        }
    }

    /**
     * Recursively gather PHP code from file and gather
     * it into array, grouped by namespace
     */
    public function compressCode($file, array $code = array(), $namespace = Compressor::NS_GLOBAL)
    {

    }

    public function compressTemplate()
    {

    }

    /** @deprecated Use compressCode() instead */
    public function compress_php($file, $module, array $code = array(), $namespace = Compressor::NS_GLOBAL)
    {
        $this->logger->log(' - Compressing PHP file[##]', $file);
        return $this->compressCode($file, $code, $namespace);
    }
}
