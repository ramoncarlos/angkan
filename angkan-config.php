<?php

// include the angkan-config.php in the directory above and if i's not there, just use a generic config

if ( !defined('ANGKAN_CONFIG')) {

	$configFileName = dirname(dirname(__FILE__)) . '/angkan-config.php';
	if ( file_exists( $configFileName )) {
		require $configFileName;
	} else {
		define ('DBHOST','xxxx' );
		define ('DBNAME','xxxx' );
		define ('DBUSERNAME','xxxx');
		define ('DBPASSWORD','xxxx' );
		define ('TRACKINGCODE','xxxx' );	// Google Analytics or other tracking code.
	}
	define( 'ANGKAN_CONFIG', 1);
}
