<?php
/**
 * Created by PhpStorm.
 * User: egorov
 * Date: 09.02.2015
 * Time: 16:29
 */

namespace samsonphp\compressor;

/**
 * Code name space class
 * @package samsonphp\compressor
 */
class NS
{
    /** @var Namespace name */
    protected $name;

    /** @var array Collection of classes declared with USE */
    protected $uses = array();

    /** @var array Collection of ClassName => Code */
    protected $classes = array();
}
