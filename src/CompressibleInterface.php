<?php
/**
 * Created by PhpStorm.
 * User: egorov
 * Date: 13.08.2015
 * Time: 14:27
 */
namespace samsonphp\compressor;

/**
 * Compressible logic
 */
interface CompressibleInterface
{
    /**
     * Take input and compress it to output
     * @param mixed $input Input data
     * @param mixed $output Output data
     * @return mixed Compressed data
     */
    public function compress($input, $output);
}
