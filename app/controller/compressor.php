<?php
use Samson\Compressor\Compressor;

/** Универсальный контроллер */
function compressor__HANDLER( $php_version = null, $noerror = true )
{
	s()->async(true);	
	
	m()->compress( $php_version, true, $noerror );
}