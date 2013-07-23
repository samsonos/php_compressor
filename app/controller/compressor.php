<?php
use Samson\Compressor\Compressor;

/** Универсальный контроллер */
function compressor__HANDLER( $php_version = null )
{
	s()->async(true);	
	
	m()->compress( $php_version, true, false );
}