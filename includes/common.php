<?php

/*
	This file is part of myTinyTodo.
	(C) Copyright 2009-2010,2020 Max Pozdeev <maxpozdeev@gmail.com>
	Licensed under the GNU GPL v2 license. See file COPYRIGHT for details.
*/

function htmlarray($a, $exclude=null)
{
	htmlarray_ref($a, $exclude);
	return $a;
}

function htmlarray_ref(&$a, $exclude=null)
{
	if(!$a) return;
	if(!is_array($a)) {
		$a = htmlspecialchars($a);
		return;
	}
	reset($a);
	if($exclude && !is_array($exclude)) $exclude = array($exclude);
	foreach($a as $k=>$v)
	{
		if(is_array($v)) $a[$k] = htmlarray($v, $exclude);
		elseif(!$exclude) $a[$k] = htmlspecialchars($v);
		elseif(!in_array($k, $exclude)) $a[$k] = htmlspecialchars($v);
	}
	return;
}

/*
function stop_gpc(&$arr)
{
	if (!is_array($arr)) {
		return 1;
	}

	// Since PHP v5.4.0 magic quotes config option was removed from PHP.
	// In PHP v7.4 get_magic_quotes_gpc() is deprecated, in v8.0 is removed.
	// TODO: do not use get_magic_quotes_gpc() and stop_gpc()
	if (!@get_magic_quotes_gpc()) {
		return 1;
	}

	reset($arr);
	foreach ($arr as $k=>$v)
	{
		if (is_array($arr[$k])) {
			stop_gpc($arr[$k]);
		}
		elseif (is_string($arr[$k])) {
			$arr[$k] = stripslashes($v);
		}
	}

	return 1;
}
*/

function _post($param,$defvalue = '')
{
	if(!isset($_POST[$param])) 	{
		return $defvalue;
	}
	else {
		return $_POST[$param];
	}
}

function _get($param,$defvalue = '')
{
	if(!isset($_GET[$param])) {
		return $defvalue;
	}
	else {
		return $_GET[$param];
	}
}

function _server($param, $defvalue = '')
{
	if ( !isset($_SERVER[$param]) ) {
		return $defvalue;
	}
	else {
		return $_SERVER[$param];
	}
}

class Config
{
	public static $params = array(
		'db' => array('default'=>'sqlite', 'type'=>'s'),
		'mysql.host' => array('default'=>'localhost', 'type'=>'s'),
		'mysql.db' => array('default'=>'mytinytodo', 'type'=>'s'),
		'mysql.user' => array('default'=>'user', 'type'=>'s'),
		'mysql.password' => array('default'=>'', 'type'=>'s'),
		'prefix' => array('default'=>'', 'type'=>'s'),
		'url' => array('default'=>'', 'type'=>'s'),
		'mtt_url' => array('default'=>'', 'type'=>'s'),
		'title' => array('default'=>'', 'type'=>'s'),
		'lang' => array('default'=>'en', 'type'=>'s'),
		'password' => array('default'=>'', 'type'=>'s'),
		'smartsyntax' => array('default'=>1, 'type'=>'i'),
		'timezone' => array('default'=>'UTC', 'type'=>'s'),
		'autotag' => array('default'=>1, 'type'=>'i'),
		'duedateformat' => array('default'=>1, 'type'=>'i'),
		'firstdayofweek' => array('default'=>1, 'type'=>'i'),
		'session' => array('default'=>'files', 'type'=>'s', 'options'=>array('files','default')),
		'clock' => array('default'=>24, 'type'=>'i', 'options'=>array(12,24)),
		'dateformat' => array('default'=>'j M Y', 'type'=>'s'),
		'dateformat2' => array('default'=>'n/j/y', 'type'=>'s'),
		'dateformatshort' => array('default'=>'j M', 'type'=>'s'),
		'template' => array('default'=>'default', 'type'=>'s'),
		'showdate' => array('default'=>0, 'type'=>'i'),
		'detectmobile' => array('default'=>1, 'type'=>'i')
	);

	public static $config;

	public static function loadConfig($config)
	{
		self::$config = $config;
	}

	public static function get($key)
	{
		if(isset(self::$config[$key])) return self::$config[$key];
		elseif(isset(self::$params[$key])) return self::$params[$key]['default'];
		else return null;
	}

	public static function getUrl($key)
	{
		$url = '';
		if ( isset(self::$config[$key]) ) $url = self::$config[$key];
		else if( isset(self::$params[$key]) ) $url = self::$params[$key]['default'];
		else return null;
		return str_replace( ["\r","\n"], '', $url );
	}

	public static function set($key, $value)
	{
		if ($key == "prefix" && $value !== "" && !preg_match("/^[a-zA-Z0-9_]+$/", $value)) {
			throw new Exception("Incorrect table prefix. Can contain only latin letters, digits and underscore character.");
		}
		self::$config[$key] = $value;
	}

	public static function save()
	{
		$s = '';
		foreach(self::$params as $param=>$v)
		{
			if(!isset(self::$config[$param])) $val = $v['default'];
			elseif(isset($v['options']) && !in_array(self::$config[$param], $v['options'])) $val = $v['default'];
			else $val = self::$config[$param];
			if($v['type']=='i') {
				$s .= "\$config['$param'] = ".(int)$val.";\n";
			}
			else {
				$s .= "\$config['$param'] = '".str_replace(array("\\","'"),array("\\\\","\\'"),$val)."';\n";
			}
		}
		$f = fopen(MTTPATH. 'db/config.php', 'w');
		if($f === false) throw new Exception("Error while saving config file");
		fwrite($f, "<?php\n\$config = array();\n$s?>");
		fclose($f);

		//Reset Zend OPcache
		//opcache_get_status() sometimes crashes
		//TODO: save config in database!
		if (function_exists("opcache_invalidate") && 0 != (int)opcache_get_configuration()["directives"]["opcache.enable"]) {
			opcache_invalidate(MTTPATH. 'db/config.php', true);
		}

	}
}

function formatDate3($format, $ay, $am, $ad, $lang)
{
	# F - month long, M - month short
	# m - month 2-digit, n - month 1-digit
	# d - day 2-digit, j - day 1-digit
	$ml = $lang->get('months_long');
	$ms = $lang->get('months_short');
	$Y = $ay;
	$YC = 100 * floor($Y/100); //...1900,2000,2100...
	if ($YC == 2000) $y = $Y < $YC+10 ? '0'.($Y-$YC) : $Y-$YC;
	else $y = $Y;
	$n = $am;
	$m = $n < 10 ? '0'.$n : $n;
	$F = $ml[$am-1];
	$M = $ms[$am-1];
	$j = $ad;
	$d = $j < 10 ? '0'.$j : $j;
	return strtr($format, array('Y'=>$Y, 'y'=>$y, 'F'=>$F, 'M'=>$M, 'n'=>$n, 'm'=>$m, 'd'=>$d, 'j'=>$j));
}

function url_dir($url, $onlyPath = 1)
{
	if (false !== $p = strpos($url, '?')) {
		$url = substr($url, 0, $p); # to avoid parse errors on strange query strings
	}
	if ($onlyPath) {
		$url = parse_url($url, PHP_URL_PATH);
	}
	if ($url == '') {
		return '/';
	}
	if (substr($url, -1) == '/') {
		return $url;
	}
	if (false !== $p = strrpos($url, '/')) {
		return substr($url, 0, $p+1);
	}
	return '/';
}

function escapeTags($s)
{
	if ($s == '') {
		return '';
	}
	$c1 = chr(1);
	$c2 = chr(2);
	$s = preg_replace("~<b>([\s\S]*?)</b>~i", "${c1}b${c2}\$1${c1}/b${c2}", $s);
	$s = preg_replace("~<i>([\s\S]*?)</i>~i", "${c1}i${c2}\$1${c1}/i${c2}", $s);
	$s = preg_replace("~<u>([\s\S]*?)</u>~i", "${c1}u${c2}\$1${c1}/u${c2}", $s);
	$s = preg_replace("~<s>([\s\S]*?)</s>~i", "${c1}s${c2}\$1${c1}/s${c2}", $s);
	$s = str_replace(array($c1, $c2), array('<','>'), htmlspecialchars($s));
	return $s;
}

function removeNewLines($s)
{
	return str_replace( ["\r","\n"], '', $s );
}

/* found in comments on http://www.php.net/manual/en/function.uniqid.php#94959 */
function generateUUID()
{
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
      mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
      mt_rand(0, 0x0fff) | 0x4000,
      mt_rand(0, 0x3fff) | 0x8000,
      mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

class DBConnection
{
	protected static $instance;

	public static function init($instance)
	{
		self::$instance = $instance;
		return $instance;
	}

	public static function instance()
	{
        if (!isset(self::$instance)) {
			//$c = __CLASS__;
			$c = 'DBConnection';
			self::$instance = new $c;
        }
		return self::$instance;
	}
}

?>