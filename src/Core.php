<?php
/**
 * Created by PhpStorm.
 * User: egorov
 * Date: 12.02.2015
 * Time: 12:23
 */
namespace samsonphp\compressor;

use samsonphp\event\Event;

/**
 * SamsonPHP core compressor
 * @package samsonphp\compressor
 */
class Core
{
    /** @var string Configuration environment */
    protected $environment = 'prod';

    /** @var  Object Logger object */
    protected $logger;

    /** @var  \samson\core\Core Core pointer */
    protected $core;

    /**
     * @param \samson\core\Core $core Core pointer
     * @param string $environment Configuration environment
     * @param object $logger Logger object
     */
    public function __construct($core, $environment = 'prod', $logger = null)
    {
        $this->core = & $core;
        $this->environment = $environment;
        $this->logger = & $logger;
    }

    /**
     * Method prepare core instance removing all unnecessary loaded modules
     * from it, and configuring it.
     * @return string Base64 encoded serialized core object instance
     */
    public function compress()
    {
        $this->logger->log(' -- Compressing core');

        // Switch to production environment
        $this->core->environment($this->environment);

        // Set rendering from string variables mode
        $this->core->render_mode = \samson\core\Core::RENDER_VARIABLE;

        // Unload all modules from core that does not implement interface iModuleCompressable
        foreach ($this->core->module_stack as $id => & $m) {
            // Unload modules that is not compressable
            if (!is_a($m, '\samson\core\iModuleCompressable')) {
                $this->core->unload($id);
                $this->logger->log(' -- [##] -> Unloading module from core', $id);
            } else { // Reconfigure module
                Event::fire('core.module.configure', array(&$m, $id));
                $this->logger->log(' -- [##] -> Loading config data', $id);
            }
        }

        // Change system path to relative type
        $this->core->path('');

        // Create serialized object copy
        return base64_encode(serialize($this->core));
    }
}
