<?php
namespace Samson\Compressor;

/**
 * Класс для подключения внешнего модуля в ядро SamsonPHP
 * @author Vitaly Egorov <vitalyiegorov@gmail.com>
 */
class CompressorConnector extends \samson\core\ModuleConnector
{
	/** Идентификатор модуля */
	protected $id = 'compressor';

	/** Коллекция связей модуля */
	protected $requirements = array(		
		'resourcecollector'
	);	
}