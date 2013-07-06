<?php
namespace Samson\Compressor;

use samson\core\iModule;
use samson\core\File;
use Samson\ResourceCollector\ResourceCollector;
use Samson\ResourceCollector\ResourceType;
use samson\core\Config;
use samson\core\ConfigType;

/**
 * Класс для собирания веб-сайта 
 *
 * @package SamsonPHP
 * @author Vitaly Iegorov <vitalyiegorov@gmail.com>
 * @version 0.1
 */
// TODO: Интегрировать обратку представлений внутри шаблона а не дублировать одинаковый код
// TODO: Анализатор классов которые добавляются, а вдруг они вообще не нужны?
// TODO: Собирать "голый" код отдельно для его цельного выполнения
// TODO: Обработка NS {} c фигурными скобками
class Compressor 
{
	/** Идентификатор глобального пространства имен */
	const NS_GLOBAL = '';
	
	/** Папка гбе будет находится свернутое веб-приложение */
	public $output;
	
	/** Папка где размещается исходное веб-приложение */
	public $input;
	
	/** Указатель на текущий сворачиваемый модуль */
	public $current;
	
	/** Путь к главному файлу CSS */
	public $css_file;
	
	/** Путь к главному файлу JS */
	public $js_file;
	
	/** Коллекция уже обработанных файлов */
	public $files = array();
	
	/** Коллекция файлов шаблонов */
	public $templates = array();
	
	/** Коллекция файлов представлений */
	public $views = array();
	
	/** Коллекция файлов с PHP кодом */
	public $php = array();
	
	/** Коллекция ресурсов типа "Картинок" */
	public $r_img = array();
	
	/** Коллекция ресурсов типа "Документы" */
	public $r_doc = array();
	
	/** Коллекция ресурсов типа "Шрифты" */
	public $r_font = array();
	
	/** Коллекция ресурсов типа "PHP код" */
	public $r_php = array();
	
	/** Коллекция файлов которые необходимо игнорировать */
	public $ignore = array( 'composer.json' );
	
	/** Шаблон для поиска команды установки шаблона веб-ариложения */
	public $tmpl_marker = 's\(\s*\).*?->.*?template\s*\(\s*(\'|\")(?<path>[^\'\"]+)';
	
	/**
	 * Скопировать файлы модуля в свернутое веб-приложение
	 * 
	 * @param array 	$files	Коллекция файлов для копирования
	 * @param iModule 	$module	Модуль которому принадлежат файлы
	 */
	public function copy( $files, iModule & $module )
	{			
		// Получим полный путь к модулю
		$path = realpath( $module->path() ).__SAMSON_PATH_SLASH__;
	
		// Папка модуля в свернутом варианте - для локальных пустышка
		$module_folder = '';
		
		// Если это не локальный модуль - подставим папку модуля
		if( $module->path() != s()->path() ) $module_folder = $module->id().'/';

		// Переберем файлы
		foreach ( $files as $file )
		{
			// Проверим запрещенные файлы
			if( in_array( basename($file), $this->ignore )) continue;
			//trace($path.' - '.$file);
			// Получим относительный путь к ресурсу
			$rel_path = str_replace( $path, '', $file );

			// Сфорсируем путь к файлу в сжатой версии веб-приложения
			$this->output_file = str_replace('\\', '/', $this->output.$module_folder.$rel_path.'');
			
			// Получим папку в свернутом веб-приложении
			$this->output_dir = dirname( $this->output_file );

			// Если выходная папка не существует - создадим её
			if( !file_exists( $this->output_dir ) ) mkdir( $this->output_dir, 0755, true );
						
			// Если файл в выходной папке не существует или он устарел
			if( ! file_exists( $this->output_file ) || ( filemtime( $file ) <> filemtime( $this->output_file )) ) 
			{
				elapsed( '  -- Обновляю файл: '.$this->output_file.' ----- '.$file);				
	
				// Скопируем файл	
				copy( $file, $this->output_file );	

				// Изменим время модификации исходного файла
				touch( $file );
			}
		}
	}	
	
	/**
	 * Свернуть файл представления
	 * @param string 	$view_file 	Полный путь к файлу представления
	 * @param iModule 	$module		Указатель на модуль которому принадлежит это представление 
	 */
	public function compress_view( $view_file, iModule & $module = null )
	{
		// Получим относительный путь к ресурсу
		$rel_path  = $this->rel_path( $view_file, $module );
		
		// Прочитаем файл представления
		$view_html = file_get_contents( $view_file );
		
		// Найдем обращения к роутеру ресурсов
		$view_html = preg_replace_callback( '/(<\?php)*\s*src\s*\(\s*(\'|\")*(?<path>[^\'\"\)]+)(\s*,\s*(\'|\")(?<module>[^\'\"\)]+))*(\'|\")*\s*\)\s*;*\s*(\?>)*/uis', array( $this, 'src_replace_callback'), $view_html );
		
		// Сожмем HTML
		$view_html = Minify_HTML::minify($view_html);
		
		// Заполним специальную коллекцию содержания представлений системы
		$this->views[ $rel_path ] = $view_html;
	}
	
	/**
	 * Обработать файл шаблона веб-приложения
	 *
	 * @param string 	$tmpl_path 	Относительный путь к файлу шаблона
	 * @param iModule 	$module		Модуль которому принадлежит этот файл
	 */
	public function compress_template( $tmpl_path, $module = NULL )
	{
		// Безопасно получим модуль
		$module = m( $module );
	
		// Вережем из пути к шаблону часть пути до переданного модуля
		$tmpl_path = str_replace( $module->path(), '', $tmpl_path);
	
		// Получим полный правильный путь
		$tmpl_full_path = realpath($module->path().$tmpl_path);
	
		// Получим относительный путь к представлению в свернутом веб-приложении
		$trp = $this->rel_path( $tmpl_full_path, $module );
			
		// Прочитаем файл шаблона
		$_php = file_get_contents( $tmpl_full_path );
	
		elapsed('   ---- Обрабатываю файл шаблона:'.$module->path().$tmpl_path.'['.$trp.']');
	
		// Обработаем шаблон
		$_php = s()->generate_template( $_php, url()->base().$this->css_file, url()->base().$this->js_file );
		
		// Найдем обращения к роутеру ресурсов
		$_php = preg_replace_callback( '/(<\?php)*\s*src\s*\(\s*(\'|\")*(?<path>[^\'\"\)]+)(\s*,\s*(\'|\")(?<module>[^\'\"\)]+))*(\'|\")*\s*\)\s*;*\s*(\?>)*/uis', array( $this, 'src_replace_callback'), $_php );		
	
		// Запишем код обработанного шаблона в специальную коллекцию
		$this->templates[ $trp ] = $_php;
	}
	
	/**
	 * Свернуть модуль
	 * @param iModule $module Указатель на модуль для сворачивания
	 */
	public function compress_module( iModule & $module )
	{
		// Идентификатор модуля
		$id = $module->id();		
		
		// Сохраним указатель на текущий модуль
		$this->current = & $module;
			
		elapsed('  - Сворачиваю модуль '.$id.'[ PHP: '.sizeof( $this->r_php[ $id ] ).', IMG:'.sizeof( $this->r_img[ $id ] ).', DOC:'.sizeof( $this->r_doc[ $id ] ).']' );
		
		//trace($id.'-'.$module->path());
		
		// Если путь к модулю не существует - ничего не делаем с ним
		if( !file_exists($module->path())) return false;
		
		// Если у модуля есть картинки - скопируем их
		if( sizeof( $this->r_img[ $id ] ) ) $this->copy( $this->r_img[ $id ], $module  );
		// Если у модуля есть документы - скопируем их
		if( sizeof( $this->r_doc[ $id ] ) ) $this->copy( $this->r_doc[ $id ], $module  );
		// Если у модуля есть документы - скопируем их
		if( sizeof( $this->r_font[ $id ] ) ) $this->copy( $this->r_font[ $id ], $module  );
		
		// Соберем коллекцию кода модуля разбитую по NS/File
		$module_php = array();	
		
		// Выполним предбработчик сжатия модуля
		$module->beforeCompress( $this, $module_php );
		
		// Если у модуля есть файл подключения - используем его
		if( file_exists( $module->path().'include.php' ) )
		{						
			$this->compress_php( $module->path().'include.php', $module, $module_php );
		}		
		
		// Коллекция представлений модуля
		$views = array();
		
		// Соберем PHP код модуля
		foreach ($this->r_php[ $id ] as $php_file )
		{				
			// Если в пути есть папка VIEW считаем что это папка для представлений и не собираем её в PHP
			if( stripos( dirname($php_file), '\\view') === FALSE && stripos( dirname($php_file), '/view') === FALSE )
			{								
				// Просто соберем код файла
				$this->compress_php( $php_file, $module, $module_php );								
			}
			// Это представление - запишем в отдельную коллекцию
			else $views[] = $php_file;
			
			// Соберем весь PHP код модуля
			$php_code = $this->code_array_to_str( $module_php  );						
		
			// Найдем вызов функции по установке шаблона
			if( preg_match_all('/'.$this->tmpl_marker.'/sui', $php_code, $matches ) )
			{
				// Переберем все обращения к шаблонам в файле и обработаем их
				foreach ( $matches['path'] as $tmpl_path ) $this->compress_template( $tmpl_path, $module );
			}				
			
			// Прочитаем содержимое представления из коллекции представлений
			foreach ( $views as $php_file ) $this->compress_view( $php_file, $module );
		}		
		
		// Выполним постобработчик сжатия модуля
		$module->afterCompress( $this, $module_php );		
		
		// Установим новый правильный путь к ресурсам модуля в свернутом веб-приложении
		if( $module->path() != s()->path() ) $module->path( $id.'/');
		else $module->path('');
		
		// Установим модулю параметры с нужной конфигурацией
		if( isset( Config::$data[ $module->core_id() ] ) ) $module->init( Config::$data[ $module->core_id() ] );
		
		// Занесем весю коллекцию кода модуля в глобальное хранилище
		$this->code_array_combine( $module_php, $this->php );
	}
	
	/**
	 * Распознать ссылку по URL
	 */
	// TODO: Сделать нормальный механизм разпознования URL через ResourceRouter
	public function resolve_css_url( $text )
	{		
		// Найдем ссылки в ресурса
		if( preg_match_all( '/url\s*\(\s*(\'|\")*(?<url>[^\'\"\)]+)\s*(\'|\")*\)/i', $text, $matches ) )
		{			
			// Если мы нашли шаблон - переберем все найденные патерны
			if( isset( $matches['url']) ) for ($i = 0; $i < sizeof($matches['url']); $i++)
			{
				// Получим путь к ресурсу используя маршрутизацию
				$url = $matches['url'][$i];	

				// Если это модифицированное URL
				if( strpos( $url, 'resourcerouter') )
				{					
					// Разпознаем строку URL и выделим из нее необходимые переременные
					$params = substr( $url, strpos( $url, '?') );
					parse_str(  $params, $vars );
			
					// Определим имя модуля
					$mod = str_replace( $params, '', $url);
					$mod = explode('/', $mod);
					$mod = trim($mod[ sizeof($mod) - 1 ]);		

					// Получим реальное имя модулю
					$mod = m($mod)->id();
					
					if( m($mod)->path() == s()->path() ) $mod = '';
					else $mod .= '/';
			
					// Заменим путь в исходном файле
					$text = str_replace( $url, url()->base().$mod.$vars['?p'], $text );
				}
			}
		}
	
		return $text;
	}
	
	/**
	 * Обработчик замены роутера ресурсов
	 * @param array $matches Найденые совпадения по шаблону
	 * @return string Обработанный вариант пути к ресурсу
	 */
	public function src_replace_callback( $matches )
	{
		// Получим относительный путь к ресурсу
		$path = $matches['path'];
	
		// Путь к модуля после сжимания
		$module_path = $this->current->id().'/';
	
		// Если передана переменная мы не можем гарантировать её значение
		if( strpos( $path, '$' ) !== false ) $path = '<?php echo \''.$module_path.'\'.'.$path.'; ?>';
		// Просто строка
		else $path = $module_path.$path;
	
		return $path;
		//e('Файл представления ## - Обращение к роутеру ресурсов через переменную ##', E_SAMSON_SNAPSHOT_ERROR, array($view_path, $path));
	}
	
	/** Конструктор */
	public function __construct( $php_version = NULL )
	{
		// Внутренняя коллекция для собирания кода 
		$this->php = array( self::NS_GLOBAL => array() );
		
		// Выгрузим из ядра системы все модули который не наследуют интерфейс сжатия iModuleCompressable
		foreach ( s()->module_stack as $id => $m ) if ( !($m instanceof \samson\core\iModuleCompressable) ) s()->unload( $id );	
									
		// Загрузим продакшн конфигурацию для модулей
		Config::load( ConfigType::PRODUCTION );
		
		// Подготовим папку для "свернутой" версии сайта, с учетом текущего веб-приложения
		$this->output = str_replace( $_SERVER['HTTP_HOST'], 'final.'.$_SERVER['HTTP_HOST'], $_SERVER['DOCUMENT_ROOT']).url()->base();
		// Входная папка
		$this->input = getcwd().'/';	
		
		// Создадим папку для свернутого сайта
		if( !file_exists($this->output)) mkdir( $this->output, 0775, true );

		elapsed('Собираю веб-приложение из: '.$this->input);
		
		// Скопируем директивы для сервера
		copy( $this->input.'.htaccess', $this->output.'.htaccess' );
		
		// Сохраним имя главного CSS ресурса
		$this->css_file = ResourceCollector::$collected['css'][ 'name' ];
		// Сохраним имя главного JS ресурса
		$this->js_file = ResourceCollector::$collected['js'][ 'name' ];		
				
		// Переберем основные типы ресурсов
		$output_resources = array();
		foreach ( File::dir( $this->output, array('css','js'), '', $output_resources, 0 ) as $file ) File::clear( $file );					
		// Запишем новые файлы ресурсов
		file_put_contents( $this->output.$this->css_file, $this->resolve_css_url(file_get_contents( ResourceCollector::$collected[ 'css' ][ 'path' ]))); 
		file_put_contents( $this->output.$this->js_file, file_get_contents( ResourceCollector::$collected[ 'js' ][ 'path' ]) );						
		
		// Соберем все PHP ресурсы
		$this->r_php = ResourceCollector::gather( ResourceType::$PHP );
		// Соберем все DOC ресурсы
		$this->r_doc = ResourceCollector::gather( ResourceType::$DOC );
		// Соберем все IMG ресурсы
		$this->r_img = ResourceCollector::gather( ResourceType::$IMG );	
		// Соберем все IMG ресурсы
		$this->r_font = ResourceCollector::gather( ResourceType::$FONT );		
		
		// Прочитаем главный шаблон веб-приложения
		$this->compress_template( s()->template(), m('local') );	
			
		// Соберем пусковой файл
		$this->compress_php( $this->input.'index.php', m('local'), $this->php );	
		
		// Заполним коллекции модулей
		foreach ( s()->module_stack as $id => & $m )
		{
			//trace($id.'-'.$m->core_id().'-'.$m->id());
			// Модуль вывода пропустим
			if( $m->core_id() == '_output') continue;
			
			// Сожмем модуль
			$this->compress_module( $m );			
		}
		
		// Сериализируем ядро
		$core_code = serialize(s());	

		// Код для представлений и глобальных переменных
		$view_php = '';
		
		// Отключим вывод ошибок
		if( $php_version != '5.2' ) 
		{
			$view_php .= "\n".'\samson\core\Error::$OUTPUT = true;';
			$view_php .= "\n".'$GLOBALS["__compressor_mode"] = false;';
		}
		else 
		{	
			// Найдем все описания классов
			if( preg_match_all('/O:(?<length>\d+):\"(?<class>[^\"]+)\"/', $core_code, $matches ))
			{				
				// Очистим NS из сериализированных объектов в ядре
				for ( $i = 0; $i < sizeof($matches[0]); $i++ )
				{
					$class = basename($matches[ 'class' ][ $i ]);
										
					$core_code = str_ireplace( $matches[0][$i], 'O:'.strlen($class).':"'.$class.'"', $core_code);
				}				
			}
			
			
			// Укажем режим работы компрессора
			$view_php .= "\n".'$GLOBALS["__compressor_mode"] = true;';
			$view_php .= "\n".'Error::$OUTPUT = true;';					
		}			
		
		// Код представлений
		$view_php .= "\n".'$GLOBALS["__compressor_files"] = array();';	

		// Получим отпечаток ядра системы
		//$view_php = "\n".'$GLOBALS["__CORE_SNAPSHOT"] = \''.base64_encode($core_code).'\';'.$view_php;
				
		// Обработаем представления веб-приложения
		foreach( $this->views as $rel_path => $c )
		{				
			// Если это шаблон веб-приложения - получим его содержимое из обработанной коллекции
			if( isset( $this->templates[ $rel_path ] )) $c = $this->templates[ $rel_path ];			
			
			// Пустые представления не включаем
			if( ! isset($c{0}) ) e('Файл представления ## - пустой', E_SAMSON_SNAPSHOT_ERROR, $rel_path );		
			// Создадим PHP код для хранения представления в специальной коллекции
			else 
			{		
				// Старые версии PHP - копируем файлі представлений
				if( $php_version == '5.2' )
				{
					// Путь к представлению модуля
					$module_path = explode( '/', $rel_path );
					
					// Определим какому модулю принадлежит представление
					if( $module_path[0] == 'app' ) $view_path = $this->output.'local/';
					else $view_path = $this->output.$module_path[0].'/';
					
					// Если папка с представлениями модуля не создана - создадим
					if( !file_exists( $view_path ) ) mkdir( $view_path, 0755, true );

					// Добавим имя самого файла
					$view_path .= basename($rel_path);					
					
					// Запишем сам файл
					file_put_contents( $view_path, $c );					
					
					// Создадим ссылку в коде
					$view_php .= "\n".'$GLOBALS["__compressor_files"]["'.$rel_path.'"] = "'.$view_path.'";';
				}				
				// Болле новые версии PHP c поддержкой inline text
				else $view_php .= "\n".'$GLOBALS["__compressor_files"]["'.$rel_path.'"] ='."<<<'EOT'"."\n".$c."\n"."EOT;";
			}
		}
		$view_php .= "\n";		
		
		// Добавим объявление глобальных переменных в начало глобального NS
		$this->php[ self::NS_GLOBAL ]['views'] = $view_php;	
		
		// Перенесем глобальное пространство имен в конец файла
		$global_ns = $this->php[ self::NS_GLOBAL ];		
		unset( $this->php[ self::NS_GLOBAL ] );		
		$this->php[ self::NS_GLOBAL ] = $global_ns;		
		
		// Исправим порядок следования файлов в модуле на правильный
		// т.к. в PHP описание классов должно идти строго по порядку 
		$classes = array();
		
		// Соберем коллекцию загруженных интерфейсов их файлов по пространствам имен
		$this->classes_to_ns_files( get_declared_interfaces(), $classes ); 
		
		// Соберем коллекцию загруженных классов их файлов по пространствам имен
		$this->classes_to_ns_files( get_declared_classes(), $classes );
		
		// Исправим порядок файлов
		foreach ( $this->php as $ns => & $files )
		{			
			// Изменим порядок элементов в массиве файлов на правильный для конкретного NS
			if( isset( $classes [ $ns ] ) ) $files = array_merge( $classes [ $ns ], $files );			 			
		}	
		
		// Соберем весь PHP код в один файл
		$index_php = $this->code_array_to_str( $this->php, $php_version == '5.2' );			
		
		// Старый PHP
		if( $php_version == '5.2' )
		{
			// Переберем все NS 
			foreach ( $this->php as $ns => $files )
			{				
				if( $ns == self::NS_GLOBAL ) continue;
				
				elapsed('Очищаем пространство имен: '.$ns);
				
				// Очистим все NS из кода т.к. код у нас в одном файле и в правильном порядке
				$index_php = str_ireplace( array( '\\'.$ns.'\\', $ns.'\\'), '', $index_php );				
			}
		}	
		
		// Если это новая версия откроем NS
		if( $php_version != '5.2' ) $index_php .= "\n".'namespace {';
		
		// Установим код ядра
		$index_php .= "\n".'$GLOBALS["__CORE_SNAPSHOT"] = \''.base64_encode($core_code).'\';';
		
		// Обработаем пути к основным сущностям системы т.к. теперь все они в одном файле
		// Установим новый путь к фреймворку SamsonPHP
		if( preg_match('/define\s*\(\s*\'__SAMSON_PATH__\'\s*,\s*([^\)]+)\s*\)/i', $index_php, $matches ) ) $index_php = str_replace( $matches[ 1 ], "''", $index_php );
		// Уберем изменение маршрута к файлам приложения т.к. мы все собираем в один файл
		if( preg_match('/s\s*\(\s*\)->path\((?<path>[^)]*)\);/i', $index_php, $matches ) ) $index_php = str_replace( $matches[ 0 ], ';', $index_php );
		
		// Найдем главный загрузчик ядра
		if( preg_match_all('/\s*s\s*\(\s*\)\s*->(load|import|handler)[^;]*/i', $index_php, $matches ) )
		{	
			// Найдем имя модуля по умолчанию
			$main_model = '';		
			
			// Уберем загрузку модулей и прочие обращения к ядру
			foreach ( $matches[0] as $match ) 
			{
				// Очистим обращение к ядру
				$index_php = str_replace( $match, '', $index_php );

				// Поищем имя модуля по умолчанию
				if( !isset($main_model{0}) && preg_match('/start\(\s*(\'|\")(?<default>[^\'\"]+)/i', $match, $matches2 )) $main_model = $matches2['default'];
			}					
			
			// Установим обращение к системе			
			$index_php .= "\n".'s()->start("'.$main_model.'");';		
		}	
		
		// Если это новая версия закроем NS
		if( $php_version != '5.2' ) $index_php .= "\n".'}';
				
		// Запишем пусковой файл
		file_put_contents( $this->output.'index.php', '<?php '.$index_php."\n".'?>' );		
		
		// Уберем пробелы, новые строки и комментарии из кода
		//$php = php_strip_whitespace( $this->output.'index.php' );
		//file_put_contents( $this->output.'index.php', $php );
	}	
	
	/**
	 * Получить относителый путь к модулю в свернутом веб-приложении
	 * 
	 * @param string $full_path	Полный путь к файлу
	 * @param string $module	Модуль которому принадлежит файл
	 * @return mixed	Относительный путь к файлу в свернутом веб-приложении
	 */
	public function rel_path( $full_path, $module = null )
	{
		// Безопасно получим модуль
		$module = m( $module );		
		
		// Полный путь к модулю в правильном формате
		$module_path = realpath( $module->path() ).__SAMSON_PATH_SLASH__;		
		
		// Получим реальный путь в правильном формате
		$full_path = realpath( $full_path );	
		
		// Папка модуля в свернутом варианте - для локальных пустышка
		$module_folder = '';
			
		// Если это не локальный модуль - подставим папку модуля
		if( $module->path() != s()->path() ) $module_folder = $module->id().'/';			
					
		// Получим относительный путь к ресурсу внутри модуля
		$rel_path = str_replace( $module_path, '', $full_path );
		
		// Сформируем относительный путь внутри свернутого веб-приложения
		return str_replace('\\', '/', $module_folder.$rel_path.'');
	}
	
	/**
	 * Преобразовать коллекцию полученного кода в виде NS/Files в строку
	 * с правильными NS
	 * 
	 * @param array $code Коллекция кода полученная функцией @see compress_php()
	 * @param boolean $no_ns Флаг убирания NS из кода
	 * @return string Правильно собранный код в виде строки
	 */
	public function code_array_to_str( array $code, $no_ns = false )
	{			
		// Соберем весь PHP код модуля
		$php_code = '';
		foreach ( $code as $ns => $files ) 
		{
			if( !$no_ns )$php_code .= "\n".'namespace '.$ns.'{';
			
			
			// Сначала вставим use 
			if( !$no_ns ) foreach ( $files['uses'] as $use ) 
			{
				$php_code .= "\n".'use '.$use.';';
			}		
			
			// Вставим код файлов
			foreach ( $files as $file => $php ) 
			{
				if( $file == 'uses' ) continue;
								
				$php_code .= $php; 
			}
			
			if( !$no_ns ) $php_code .= "\n".'}';
		}
		
		return $php_code;
	}
	
	public function code_array_combine( array & $source, array & $target )
	{
		foreach ( $source as $ns => $files) 
		{			
			// Если в целевом массиве нет нужного NS - создадим
			if( !isset( $target[ $ns ] ) ) $target[ $ns ] = array();

			// Запишем содержание NS/Files
			foreach ( $files as $file => $php ) 
			{ 
				if( isset( $target[ $ns ][ $file ] ) && is_string($php)) $target[ $ns ][ $file ] .= $php;
				else if ( isset( $target[ $ns ][ $file ] ) && is_array( $php ) ) $target[ $ns ][ $file ] = array_unique(array_merge( $target[ $ns ][ $file ], $php ));
				else $target[ $ns ][ $file ] = $php;
			}
		}
	}
	
	/**
	 * Выполнить рекурсивное "собирание" файла
	 *
	 * @param string $path Абсолютный путь к файлу сайта
	 */
	// TODO: Довести до ума разпознование require - убрать точку с зяпятоц которая остается
	// TODO: Убрать пустые линии
	// TODO: Анализатор использования функция и переменных??
	public function compress_php( $_path, $module = NULL, & $code = array(), $namespace = self::NS_GLOBAL )
	{				
		// Получим реальный путь к файлу
		$path = realpath( $_path );		
		
		//trace(' + Вошли в функцию:'.$path.'('.$namespace.')');
	
		// Если мы уже подключили данный файл или он не существует
		if( isset( $this->files[ $path ])  ) 	return elapsed('    ! Файл: '.$_path.', уже собран' );
		else if( !is_file($path) )				return elapsed('    ! Файл: '.$_path.', не существует' );		
	
		elapsed('  -- Собираю PHP код из файла: '.$path );
	
		//trace('Чтение файла: '.$path );
	
		// Сохраним файл
		$this->files[ $path ] = $path;
	
		// Относительный путь к файлу
		if(isset($rel_path)) $this->files[ $rel_path ] = $path;
			
		// Прочитаем php файл
		$fileStr = file_get_contents( $path );
		
		// Если в файле нет namespace - считаем его глобальным 
		if( strpos( $fileStr, 'namespace' ) === false )
		
			$file_dir = '';
		// Вырежим путь к файлу
		$file_dir = (pathinfo( $path, PATHINFO_DIRNAME ) == '.' ? '' : pathinfo( $path, PATHINFO_DIRNAME ).'/');
	
		// Сюда соберем код программы
		$main_code = "\n".'// Модуль: '.m($module)->id().', файл: '.$path."\n";
		
		// Создадим уникальную коллекцию алиасов для NS
		if( !isset($code[ $namespace ][ 'uses' ] ) ) $code[ $namespace ][ 'uses' ] = array();
		
		// Установим ссылку на коллекцию алиасов
		$uses = & $code[ $namespace ][ 'uses' ];		
	
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
							$_use = strtolower($_use);
							
							// TODO: Вывести замечание что бы код везде был одинаковый
							if( !in_array( $_use, $uses ) )
							{								
								// Преведем все use к одному виду
								if( $_use{0} !== '\\') $_use = '\\'.$_use;
								
								$uses[] = $_use;
							}
						}
						else $main_code .= ' use ';
						
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
							$namespace = $_namespace;
							
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
			
						//elapsed('Найден подключаемый файл');
			
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
							if( !file_exists( $file_path ) ) $file_path = $file_dir . $file_path;
									
							// Если файл найден - получим его содержимое
							if( file_exists( $file_path ) )
							{	
								//trace('Углубляемся в файл:'.$file_path.'('.$namespace.')');
								
								// Углубимся в рекурсию 
								$this->compress_php( $file_path, $module, $code, $namespace );

								// Измением позицию маркера чтения файла
								$i = $j;
							}
						}
						else $main_code .= $text;
						
					}
					break;
		
					// Собираем основной код программы
					default: $main_code .= $text; break;
				}
			}
		}		
		
		//trace(' - Вышли из функции:'.$path.'('.$namespace.')');
		//trace('');			
		
		// Запишем в коллекцию кода полученный код
		$code[ $namespace ][ $path ] = $main_code;
	}
	
	/**
	 * Преобразовать коллекцию имен классов в коллекцию 
	 * [Namespace][ ClassFileName ]
	 * 
	 * @param array $collection Коллекция имен классов
	 * @param array $classes	Коллекция для возврата результатов
	 */
	private function classes_to_ns_files( $collection, & $classes = array() )
	{		
		// Соберем коллекцию загруженных интерфейсов их файлов по пространствам имен
		foreach ( $collection as $class )
		{
			$ac = new \ReflectionClass( $class );
			
			$ns = $ac->getNamespaceName();
				
			if( $ns != '')
			{
				if( !isset( $classes[ $ns ]) ) $classes[ $ns ] = array();
					
				$classes[ $ns ][ $ac->getFileName() ] = '';
			}
		}
	}
}