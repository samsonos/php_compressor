<?php
/**
 * Created by PhpStorm.
 * User: egorov
 * Date: 09.02.2015
 * Time: 16:20
 */
namespace samsonphp\compressor;
use samson\core\Module;

/**
 * Code compressor.
 * @package samsonphp\compressor
 */
class Code implements CompressibleInterface
{
    /** Code excluding markers */
    const EXCLUDE_ST = '\/\/\[PHPCOMPRESSOR\(remove\,start\)\]';
    const EXCLUDE_EN = '\/\/\[PHPCOMPRESSOR\(remove\,end\)\]';

    /** Use statement pattern */
    const PAT_USE = '/\s*use\s+(?<class>[^ ]+)(\s+as\s+(<alias>[^;]+))*;/iu';
    /** Blank lines pattern */
    const PAT_BLANK_LINES = '/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/';

    /** @var array Collection of files that has been compressed */
    protected $files = array();

    /** @var array Collection of  NameSpace => \samsonphp\compressor\NS */
    protected $data = array();

    /** @var  Object Logger object */
    protected $logger;

    /**
     * Remove blank lines from code
     * http://stackoverflow.com/questions/709669/how-do-i-remove-blank-lines-from-text-in-php
     * @param string $code Code for removing blank lines
     * @return string Modified code
     */
    protected function removeBlankLines($code)
    {
        // New line is required to split non-blank lines
        return preg_replace(self::PAT_BLANK_LINES, "\n", $code);
    }

    /**
     * @param \samson\core\Module $module Module which code is being gathered
     */
    public function __construct(array $files, $logger = null)
    {
        $this->logger = & $logger;

        // Gather all module code resource files
        foreach ($files as $file) {
            // Compress this files into code array
            $this->compress($file);
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
                $fullClassName = $fullClassName{0} !== '\\' ? '\\'.$fullClassName : $fullClassName;

                // Determine marker in code for this use, alias or just class name
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

    public function compress($file, $output)
    {
        if (file_exists($file)) {
            // Make full real path to file
            $file = realpath($file);
            // If we have not processed this file yet
            if (!in_array($file, $this->files)) {
                $this->files[] = $file;

                $this->logger->log('Compressing PHP code file[##]', $file);

                // Read and compress file contents
                $this->data[$file] = $this->removeUSE(
                    $this->removeExcluded(
                        $this->removeBlankLines(
                            file_get_contents($file)
                        )
                    )
                );

            } else {
                $this->logger->log('PHP code file[##] already compressed', $file);
            }
        } else {
            $this->logger->log('PHP code file[##] does not exists', $file);
        }
    }

    /**
     * Reccurent PHP code parser
     *
     * @param string $path Abcolute path to php file
     * @param Module  $module Pointer to file owning module object
     * @param array  $code Collection where we need to gather parsed PHP code
     * @param string $namespace Module namespace
     *
     * @return array
     */
    protected function parse( $path, $module = NULL, & $code = array(), $namespace = self::NS_GLOBAL )
    {
        $_path = $path;
        $path = normalizepath(realpath($path));

        // Если мы уже подключили данный файл или он не существует
        if( isset( $this->files[ $path ])  ) 	return $this->log('    ! Файл: [##], already compressed', $path);
        else if( !is_file($path) )				return $this->log('    ! Файл: [##], не существует', $_path );
        else if(strpos($path, 'vendor/autoload.php') !== false) return $this->log('    Ignoring composer autoloader [##]', $path);
        else if(in_array(basename($path), $this->ignoredFiles)) { return $this->log('    Ignoring file[##] by configuration', $path);}

        $this->log(' - Parsing file [##]', $path);

        // Load file once, if it's not have been loaded before
        require_once($path);

        // Сохраним файл
        $this->files[ $path ] = $path;

        // Прочитаем php файл
        $fileStr = file_get_contents( $path );

        $main_code = "\n".'// Модуль: '.m($module)->id().', файл: '.$path."\n";

        // Создадим уникальную коллекцию алиасов для NS
        if( !isset($code[ $namespace ][ 'uses' ] ) ) $code[ $namespace ][ 'uses' ] = array();

        // Установим ссылку на коллекцию алиасов
        $uses = & $code[ $namespace ][ 'uses' ];

        // Local file uses collection
        $file_uses = array();

        // Получим константы документа
        $consts = get_defined_constants();

        // Маркеры для отрезания специальных блоков которые не нужны в PRODUCTION
        $rmarker_st = '\/\/\[PHPCOMPRESSOR\(remove\,start\)\]';
        $rmarker_en = '\/\/\[PHPCOMPRESSOR\(remove\,end\)\]';

        // Найдем все "ненужные" блоки кода и уберем их
        $fileStr = preg_replace('/'.$rmarker_st.'.*?'.$rmarker_en.'/uis', '', $fileStr );

        // Разберем код программы
        $tokens = token_get_all($fileStr);
        for ($i = 0; $i < sizeof($tokens); $i++)
        {
            // Получим следующий жетон из кода программы
            $token = $tokens[$i];

            // Если просто строка
            if ( is_string( $token ) ) $main_code .= $token;
            // Если это специальный жетон
            else
            {
                // token array
                list( $id, $text ) = $token;

                // Перебирем тип комманды
                switch ($id)
                {
                    case T_COMMENT: // Пропускаем все комментарии
                    case T_DOC_COMMENT:
                    case T_CLOSE_TAG: // Начало,конец файла
                    case T_OPEN_TAG: break;

                    case T_WHITESPACE:	$main_code .= $text; /*$main_code .= ' ';*/ break;

                    // Обработаем алиасы
                    case T_USE:

                        $_use = '';

                        // Переберем все что иде после комманды алиаса
                        for ($j = $i+1; $j < sizeof($tokens); $j++)
                        {
                            // Получим идентификатор метки и текстовое представление
                            $id = isset( $tokens[ $j ][0] ) ? $tokens[ $j ][0] : '';
                            $text = isset( $tokens[ $j ][1] ) ? $tokens[ $j ][1] : '';

                            //trace('"'.$id.'" - "'.$text.'"');

                            // Если use используется в функции
                            if( $id == '(' ) { $j--; break; }

                            // Если это закрывающая скобка - прекратим собирание пути к файлу
                            if( $id == ';' ) break;

                            // Все пробелы игнорирую
                            if( $id == T_WHITESPACE ) continue;

                            // Если у метки есть текстовое представление
                            if( isset( $text ) )
                            {
                                // Если єто константа
                                if( isset( $consts[ $text ])) $_use .= $consts[ $text ];
                                // Если это путь
                                else $_use .= $text;
                            }
                        }

                        // Если это не use в inline функции - добавим алиас в коллекцию
                        // для данного ns с проверкой на уникальность
                        if( $id !== '(' )
                        {
                            // Нижний регистр
                            //$_use = strtolower($_use);

                            // Преведем все use к одному виду
                            if( $_use{0} !== '\\') $_use = '\\'.$_use;

                            // Add local file uses
                            $file_uses[] = $_use;

                            // TODO: Вывести замечание что бы код везде был одинаковый
                            if( !in_array( $_use, $uses ) )
                            {


                                $uses[] = $_use;
                            }
                        } else {
                            $main_code .= ' use ';
                        }

                        // Сместим указатель чтения файла
                        $i = $j;

                        break;

                    case T_NAMESPACE:

                        // Определим временное пространство имен
                        $_namespace = '';

                        // Переберем все что иде после комманды подключения файла
                        for ($j = $i+1; $j < sizeof($tokens); $j++)
                        {
                            // Получим идентификатор метки и текстовое представление
                            $id = isset( $tokens[ $j ][0] ) ? $tokens[ $j ][0] : '';
                            $text = isset( $tokens[ $j ][1] ) ? $tokens[ $j ][1] : '';

                            //trace('"'.$id.'" - "'.$text.'"');

                            // Если это закрывающая скобка - прекратим собирание пути к файлу
                            if( $id == ')' || $id == ';' ||  $id == '{' ) break;

                            // Все пробелы игнорирую
                            if( $id == T_WHITESPACE ) continue;

                            // Если у метки есть текстовое представление
                            if( isset( $text ) )
                            {
                                // Если єто константа
                                if( isset( $consts[ $text ])) $_namespace .= $consts[ $text ];
                                // Если это путь
                                else $_namespace .= $text;
                            }
                        }

                        // Если найденный NS отличается от текущего - установим переход к новому NS
                        if( $namespace !== $_namespace )
                        {
                            // Сохраним новый как текущий
                            $namespace = strtolower($_namespace);

                            //trace('               #'.$i.' -> Изменили NS с '.$namespace.' на '.$_namespace);

                            // Если мы еще не создали данный NS
                            if( !isset($code[ $namespace ]) ) $code[ $namespace ] = array();
                            // Создадим уникальную коллекцию алиасов для NS
                            if( !isset($code[ $namespace ][ 'uses' ] ) ) $code[ $namespace ][ 'uses' ] = array();
                            // Установим ссылку на коллекцию алиасов
                            $uses = & $code[ $namespace ][ 'uses' ];
                        }

                        // Сместим указатель чтения файла
                        $i = $j;

                        break;

                    // Выделяем код подключаемых файлов
                    case T_REQUIRE :
                    case T_REQUIRE_ONCE :
                        //case T_INCLUDE :
                    case T_INCLUDE_ONCE:
                    {
                        // Получим путь к подключаемому файлу
                        $file_path = '';

                        // Переберем все что иде после комманды подключения файла
                        for ($j = $i+1; $j < sizeof($tokens); $j++)
                        {
                            // Получим идентификатор метки и текстовое представление
                            $id = isset( $tokens[ $j ][0] ) ? $tokens[ $j ][0] : '';
                            $text = isset( $tokens[ $j ][1] ) ? $tokens[ $j ][1] : '';

                            //trace('"'.$id.'" - "'.$text.'"');

                            // Если это закрывающая скобка - прекратим собирание пути к файлу
                            if( $id == ';' ) break;

                            // Все пробелы игнорирую
                            if( $id == T_WHITESPACE ) continue;

                            // Если у метки есть текстовое представление
                            if( isset( $text ) )
                            {
                                // Если єто константа
                                if( isset( $consts[ $text ])) $file_path .= $consts[ $text ];
                                // Если это путь
                                else $file_path .= $text;
                            }
                        }

                        // Если указан путь к файлу
                        if( isset($file_path{1}) )
                        {
                            // Уберем ковычки
                            $file_path = str_replace(array("'",'"'), array('',''), $file_path );

                            // Если это не абсолютный путь - попробуем относительный
                            if( !file_exists( $file_path ) ) $file_path = pathname($path).$file_path;

                            // Если файл найден - получим его содержимое
                            if( file_exists( $file_path ) )
                            {
                                //trace('Углубляемся в файл:'.$file_path.'('.$namespace.')');

                                // Углубимся в рекурсию
                                $this->compress_php( $file_path, $module, $code, $namespace );

                                // Измением позицию маркера чтения файла
                                $i = $j + 1;
                            }
                        } else {
                            $main_code .= $text;
                        }

                    }
                        break;

                    // Собираем основной код программы
                    default: $main_code .= $text; break;
                }
            }
        }

        //trace(' - Вышли из функции:'.$path.'('.$namespace.')');
        //trace('');

        // Replace all class shortcut usage with full name
        if (sizeof($file_uses)) {
            $main_code = $this->removeUSEStatement($main_code, $file_uses);
        }

        // Запишем в коллекцию кода полученный код
        $code[ $namespace ][ $path ] = $main_code;

        return $main_code;
    }
}
