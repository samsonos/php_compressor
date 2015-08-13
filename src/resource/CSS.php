<?php
/**
 * Created by PhpStorm.
 * User: egorov
 * Date: 13.08.2015
 * Time: 10:43
 */
namespace samsonphp\compressor\resource;
use samsonphp\compressor\Compressor;
use samson\core\Module;

/**
 * Module compression resource management
 * @package samsonphp\compressor
 */
class CSS extends Generic
{
    /** CSS url() matching pattern */
    const PAT_URL = '/url\s*\(\s*(\'|\")*(?<url>[^\'\"\)]+)\s*(\'|\")*\)/i';

    /** @var Module URL resolving module  */
    protected $resolver;

    /** @var string[] Collection of ignored file extensions */
    protected $ignoredExtensions = array('php', 'js', 'md', 'map', 'dbs', 'vphp', 'less', 'gz', 'lock', 'json', 'sql', 'xml', 'yml');

    /**
     * @param Compressor $compressor Pointer to parent compressing object
     * @param Module $compressor URL resolving module
     * @param string[] $ignoredExtensions Collection of ignored file extensions
     * @param string[] $ignoredFiles Collection of ignored files
     * @param string[] $ignoredFolders Collection of ignored folders
     */
    public function __construct(
        Compressor & $compressor,
        Module $resolver,
        $ignoredExtensions = array(),
        $ignoredFiles = array(),
        $ignoredFolders = array())
    {
        $this->resolver = $resolver;

        parent::__construct($compressor, $ignoredExtensions, $ignoredFiles, $ignoredFolders);
    }

    /**
     * Update file resource
     * @param string $fromFilePath Source file path
     * @param string $toFilePath Destination file path
     */
    protected function update($fromFilePath, $toFilePath)
    {
        // Read source file
        $text = file_get_contents($fromFilePath);

        // Найдем ссылки в ресурса
        if (preg_match_all(self::PAT_URL, $text, $matches)) {
            // Если мы нашли шаблон - переберем все найденные патерны
            if (isset($matches['url'])) {
                foreach ($matches['url'] as $url) {
                    $module = '';
                    $path = '';
                    // Получим путь к ресурсу используя маршрутизацию
                    if ($this->resolver->parseURL($url, $module, $path)) {
                        //trace($matches['url'][$i].'-'.url()->base().$module.'/'.$path);
                        // Заменим путь в исходном файле
                        $text = str_replace($url, url()->base() . ($module == 'local' ? '' : $module . '/') . $path, $text);
                    }
                }
            }
        }

        // Write destination file
        file_put_contents($toFilePath, $text);
    }
}

