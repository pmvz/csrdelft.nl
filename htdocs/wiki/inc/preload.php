<?php
/**
 * This is an example for a farm setup. Simply copy this file to preload.php and
 * uncomment what you need. See http://www.dokuwiki.org/farms for more information.
 * You can also use preload.php for other things than farming, e.g. for moving
 * local configuration files out of the main ./conf directory.
 */

// set this to your farm directory
//if(!defined('DOKU_FARMDIR')) define('DOKU_FARMDIR', '/var/www/farm');

// include this after DOKU_FARMDIR if you want to use farms
//include(fullpath(dirname(__FILE__)).'/farm.php');

// you can overwrite the $config_cascade to your liking
//$config_cascade = array(
//);


//settings specific for use of the authcsr authentication plugin
define ('DOKU_SESSION_NAME', "PHPSESSID");
define ('DOKU_SESSION_LIFETIME', 1036800);
define ('DOKU_SESSION_PATH', '/');

$sessiepath = fullpath(dirname(__FILE__) . '/../../../') . '/sessie';
session_save_path($sessiepath);