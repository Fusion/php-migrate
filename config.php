<?php
/**
 * @package Lenses
 * @copyright (c) Chris F. Ravenscroft
 * @license See 'license.txt'
 */
class Config
{
	static public
			// Email address for the application's developer - use for notification
			$developer	= 'bogus@voilaweb.com',

			// Web application root path
			$path		= '/',

			// Database layer, engine, etc. as used by adodb
			$dblayer	= 'native',
			$dbengine	= 'mysql',
			$dbhost		= 'localhost',
			$dbname		= 'demo',
			$dbuser		= 'demo',
			$dbpassword	= 'demo',
			$dbprefix	= '',

			$webcli     = false,
			$webcliips	= array();
}
?>
