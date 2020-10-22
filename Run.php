#!/usr/bin/php
<?php

ini_set('date.timezone', 'America/Toronto');
ini_set('error_reporting', 'E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE');
ini_set('memory_limit', '1G');

set_time_limit(0);

if($_SERVER['argc']==1 || in_array('-h', $_SERVER['argv']) || in_array('--help', $_SERVER['argv']))
{
	print "Usage: ".__FILE__." [OPTION]...\n\n";
	print "Options:\n";
	print "\t-i,\t--ip=IP\t\tip address to listen to.\n";
	print "\t-p,\t--port=PORT\tthe port to open for tcp traffic.\n";
	print "\n\n";
	exit;
}
define("BGo", "\x1b[7m");
define("BGc", "\x1b[0m\033[K");
define("Bo", "\033[1m");
define("Bc", "\033[0m");

if(!function_exists('classAutoLoader'))
{
	function classAutoLoader($class)
	{
		$classFile = __DIR__.'/'.$class.'.php';
		if(is_file($classFile) && !class_exists($class)) include $classFile;
	}
}
spl_autoload_register('classAutoLoader');
if(!is_dir(__DIR__.'/cache')) mkdir(__DIR__.'/cache', 0755);

class Run
{
	public $ip, $port;
	public $debug = false;
	public $M;
	
	private $Cache;

	public function __construct()
	{
		$this->M = new Manager('Run');
		if(!function_exists('socket_create'))
		{
			throw new Exception("'Socket Functions' are required. Compile PHP with 'Socket Functions' enabled.");
		}
	}
	public function __set($name, $value)
	{
		if($name=='e')
		{
			$message = (string)$value;
			echo "[".date("Y-m-d H:i:s")."] - $message\n";
		}
		if($name=='ip')
		{
			if(!filter_var($value, FILTER_VALIDATE_IP))
			{
				throw new Exception('Error: a valid ip address is required');
			}
		}
		if($name=='port')
		{
			if(empty($value) || !is_numeric($value))
			{
				throw new Exception('Error: a valid port (1-33000) is required');
			}
		}
		if($name=='Cache')
		{
			$v = $this->M->Push($value);
			if(!is_null($this->value))
			{
				$this->value = $v;
			}
		}
	}
}
try
{
	$R = new Run;
	for($i=1;isset($_SERVER['argv'][$i]);$i++)
	{
		if($_SERVER['argv'][$i]=='-i')
		{
			$value = $_SERVER['argv'][$i+1];
			if(filter_var($value, FILTER_VALIDATE_IP))
			{
				$R->ip = $value;
				$i++;
			}
		}
		elseif(substr($_SERVER['argv'][$i], 0, 5)=='--ip=')
		{
			$value = substr($_SERVER['argv'][$i], 5);
			if(filter_var($value, FILTER_VALIDATE_IP))
			{
				$R->ip = $value;
			}
		}
		elseif($_SERVER['argv'][$i]=='-p')
		{
			$value = $_SERVER['argv'][$i+1];
			if(is_numeric($value) && (int)$value>0 && (int)$value==$value && (int)$value<33001)
			{
				$R->port = (int)$value;
				$i++;
			}
		}
		elseif(substr($_SERVER['argv'][$i], 0, 7)=='--port=')
		{
			$value = substr($_SERVER['argv'][$i], 7);
			if(is_numeric($value) && (int)$value>0 && (int)$value==$value && (int)$value<33001)
			{
				$R->port = (int)$value;
			}
		}
		elseif($_SERVER['argv'][$i]=='-d' || $_SERVER['argv'][$i]=='--debug')
		{
			$R->debug = true;
		}
	}
}
catch(Exception $e)
{
	$R->e = "Caught exception: ".$e->getMessage();
	exit;
}
if(pcntl_fork()) exit;
posix_setsid();

try
{
	$R->M->Worker();
	$obj = new Socket($R->ip, $R->port);
}
catch(Exception $e)
{
	$R->e = "Caught exception: ".$e->getMessage();
	exit;
}