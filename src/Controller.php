<?php
/**
 * Created by PhpStorm.
 * User: egorov
 * Date: 12.02.2015
 * Time: 11:33
 */
namespace samsonphp\compressor;

use samson\core\Service;
use samsonphp\event\Event;

/**
 * UI module controller
 * @package samsonphp\compressor
 * @author Vitaly Iegorov <egorov@samsonos.com>
 */
class Controller extends Service
{
    /** Module identifier */
    protected $id = 'compressor';

    /** Output path for compressed web application */
    public $output = 'out/';

    /** Module init */
    public function init(array $params = array())
    {
        //Event::subscribe('core.rendered', array($this, 'panelRenderer'));
    }

    /**
     * Panel render handler
     * @param string    $output HTML output
     * @param array     $data   View variables collection
     * @param \samson\core\Module $module Active module pointer
     */
    public function panelRenderer(&$output, $data, $module)
    {
        //$output .= $this->view('panel/index')->output();
    }

    /**
     * Compress web-application
     * @param boolean   $debug 	        Disable errors output
     * @param string    $environment 	Configuration environment
     * @param string    $phpVersion 	PHP version to support
     */
    public function __handler($debug = false, $environment = 'prod', $phpVersion = PHP_VERSION)
    {
        $compressor = new Compressor($this->output, $debug, $environment, $phpVersion);
        $compressor->compress($debug, $environment, $phpVersion);
    }

    /** Controller action for compressing debug version of web-application */
    public function __debug($environment = '')
    {
        $this->__HANDLER(true, $environment);
    }
}
