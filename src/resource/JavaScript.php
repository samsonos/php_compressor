<?php
/**
 * Created by PhpStorm.
 * User: egorov
 * Date: 13.08.2015
 * Time: 10:43
 */
namespace samsonphp\compressor\resource;

/**
 * Module compression JavaScript resource management
 * @package samsonphp\compressor
 */
class JavaScript extends Generic
{
    /** @var string[] Collection of ignored file extensions */
    protected $ignoredExtensions = array('php', 'css', 'md', 'map', 'dbs', 'vphp', 'less' , 'gz', 'lock', 'json', 'sql', 'xml', 'yml');
}
