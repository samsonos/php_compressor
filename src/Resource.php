<?php
/**
 * Created by PhpStorm.
 * User: nikita
 * Date: 15.08.16
 * Time: 22:14
 */

namespace samsonphp\compressor;


use samsonframework\filemanager\FileManagerInterface;
use samsonphp\event\Event;

class Resource
{
    /** @var FileManagerInterface File system manager */
    protected $fileManager;

    public function __construct(FileManagerInterface $fileManager)
    {
        $this->fileManager = $fileManager;
    }

    public function compress(array $urls, $type, $output)
    {
        $content = '';
        $fileName ='';
        foreach ($urls as $url)
        {
            if ($this->fileManager->exists($url)) {
                $fileName .= $url . $this->fileManager->lastModified($url);
                $content .= $this->fileManager->read($url);
            }
        }
        $fileName = md5($fileName);

        $fileName = $fileName.'.'.$type;

        Event::fire(Compressor::E_RESOURCE_COMPRESS, array($type, &$content));



        $this->fileManager->write($output.$fileName, $content);

        return $fileName;
    }
}