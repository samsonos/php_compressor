<?php
/**
 * Created by PhpStorm.
 * User: nikita
 * Date: 15.08.16
 * Time: 22:14
 */

namespace samsonphp\compressor;


use samsonframework\filemanager\FileManagerInterface;

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

        if ($type == 'css'){
            if (preg_match_all('/url\s*\(\s*(\'|\")*(?<url>[^\'\"\)]+)\s*(\'|\")*\)/i', $content, $matches)) {
                // Если мы нашли шаблон - переберем все найденные патерны
                if (isset($matches['url'])) {
                    foreach ($matches['url'] as $url) {
                        $module = '';
                        $path = '';
                        // Получим путь к ресурсу используя маршрутизацию
                        if ($this->parseURL($url, $module, $path)) {
                            // Always remove first public path /www/
                            $path = ltrim(str_replace(__SAMSON_PUBLIC_PATH, '', $path), '/');
                            // Заменим путь в исходном файле
                            $content = str_replace($url, url()->base() . ($module == 'local' ? '' : $module . '/www/') . $path, $content);
                        }
                    }
                }
            }
        }



        $this->fileManager->write($output.$fileName, $content);

        return $fileName;
    }

    public  function parseURL($url, & $module = null, &$path = null)
    {
        // If we have URL to resource router
        if (preg_match('/'.STATIC_RESOURCE_HANDLER.'\/\?p=(((\/src\/|\/vendor\/samson[^\/]+\/)(?<module>[^\/]+)(?<path>.+))|((?<local>.+)))/ui', $url, $matches)) {
            if (array_key_exists('local', $matches)) {
                $module = 'local';
                $path = $matches['local'];
            } else {
                $module = $matches['module'];
                $path = $matches['path'];
            }
            return true;
        } else {
            return false;
        }
    }
}