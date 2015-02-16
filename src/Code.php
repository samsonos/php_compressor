<?php
/**
 * Created by PhpStorm.
 * User: egorov
 * Date: 09.02.2015
 * Time: 16:20
 */
namespace samsonphp\compressor;

/**
 * Code compressor.
 * @package samsonphp\compressor
 */
class Code
{
    /** Code excluding markers */
    const EXCLUDE_ST = '\/\/\[PHPCOMPRESSOR\(remove\,start\)\]';
    const EXCLUDE_EN = '\/\/\[PHPCOMPRESSOR\(remove\,end\)\]';

    /** Use statement pattern */
    const PAT_USE = '/\s*use\s+(?<class>[^ ]+)(\s+as\s+(<alias>[^;]+))*;/iu';

    /** @var array Collection of files that has been compressed */
    protected $files = array();

    /** @var array Collection of  NameSpace => \samsonphp\compressor\NS */
    protected $data = array();

    /** @var  Object Logger object */
    protected $logger;

    /**
     * @param \samson\core\Module $module Module which code is being gathered
     */
    public function __construct(array $files, $logger = null)
    {
        $this->logger = & $logger;

        // Gather all module code resource files
        foreach ($files as $file) {
            // Compress this files into code array
            $this->collect($file);
        }
    }

    /**
     * Remove all use statements in a file and replace class/function calls
     * to full syntax names.
     */
    public function removeUSE($code)
    {
        // Find all uses in file
        $matches = array();
        if (preg_match_all(self::PAT_USE, $code, $matches)) {
            for ($i = 0,$size = sizeof($matches[1]); $i < $size; $i++) {
                // Get full class name
                $fullClassName = $matches['class'][$i];
                // Get class name without namespace
                $className = substr(strrchr($fullClassName, '\\'), 1);

                // Prepend global namespace sign
                if ($fullClassName{0} !== '\\') {
                    $fullClassName = '\\'.$fullClassName;
                }

                // Determine marker in code for thi use, alias or just class name
                $replace = isset($matches['alias'][$i]{0}) ?
                    $matches['alias'][$i] : // Use alias name
                    $className; // Use class name without namespace

                // Remove use statement
                $code = str_replace($matches[0][$i], '', $code);

                // Check class existence
                if (class_exists($fullClassName) || interface_exists($fullClassName)) {

                    $this->logger->log(' Removing USE statement for [##]', $fullClassName);

                    // Replace class static call
                    $code = preg_replace(
                        '/([^\\\a-z])' . $replace . '::/i',
                        '$1' . $fullClassName . '::',
                        $code
                    );

                    // Replace class implements calls
                    $code = preg_replace(
                        '/implements\s+(.*)' . $replace . '/i',
                        'implements $1' . $fullClassName,
                        $code
                    );

                    // Replace class extends calls
                    $code = preg_replace(
                        '/extends\s+' . $replace . '/i',
                        'extends ' . $fullClassName,
                        $code
                    );

                    // Replace class hint calls
                    $code = preg_replace(
                        '/(\(|\s|\,)\s*' . $replace . '\s*(&|$)/i',
                        '$1' . $fullClassName . ' $2',
                        $code
                    );

                    // Replace class creation call
                    $code = preg_replace(
                        '/new\s+' . $replace . '\s*\(/i',
                        'new ' . $fullClassName . '(',
                        $code
                    );

                } else {
                    $this->logger->log(
                        'Cannot remove use statement[##] - Class or interface does not exists',
                        $matches[0][$i]
                    );
                }
            }
        }

        return $code;
    }

    /**
     * Remove excluded code blocks
     * @param string $code Code to exclude
     * @return string Code cleared from excluded blocks
     */
    public function removeExcluded($code)
    {
        // Remove excluded code blocks
        return preg_replace('/'.self::EXCLUDE_ST.'.*?'.self::EXCLUDE_EN.'/uis', '', $code);
    }

    public function collect($file)
    {
        if (file_exists($file)) {
            // Make full real path to file
            $file = realpath($file);
            // If we have not processed this file yet
            if (!in_array($file, $this->files)) {
                $this->files[] = $file;

                $this->logger->log('Compressing PHP code file[##]', $file);

                // Read file contents
                $code = $this->removeUSE($this->removeExcluded(file_get_contents($file)));

            } else {
                $this->logger->log('PHP code file[##] already compressed', $file);
            }
        } else {
            $this->logger->log('PHP code file[##] does not exists', $file);
        }
    }
}
