<?php

/**
 *
 *
 * @file
 *
 * PublishThis constants definition
 */

//Get settings variable for publishThis
global $pt_settings_value;
$pt_settings_value = unserialize( variable_get( 'pt_settings' ) );

// Set api URL
if(!defined("PT_API_URL_3_0")) define( "PT_API_URL_3_0", "http://api.publishthis.com/rest" );

// Set additional constants
if(!defined("CURATED_LOGO_PATH")) define( "CURATED_LOGO_PATH", "http://img.publishthis.com/images/ptbuttons/" );
if(!defined("NODE_NO_TITLE")) define( "NODE_NO_TITLE", "no title" );