<?php

class Manager
{
	private $cache = array();
	private $sock;
	public $ip, $port;

	final public function __construct(String $Exec)
	{
		$this->ip = '127.0.0.1';
		if($Exec=='Run')
		{
			$this->Init();
			socket_close($this->sock);
			$GLOBALS['__manager_port'] = $this->port;
		}
		else
		{
			$this->port = $GLOBALS['__manager_port'];
		}
	}
	final public function __destruct()
	{
		if(is_resource($this->sock))
		{
			socket_close($this->sock);
		}
	}
	public function Worker()
	{
		if(pcntl_fork()) return true;
		if($this->Init())
		{
			$cleanup = 0;
			while(true)
			{
				if(($sock=@socket_accept($this->sock))!==false)
				{
					$len = '';
					while((@socket_recv($sock, $buffer, 1, MSG_WAITALL))!==false)
					{
						if(substr($buffer, 0, 1)==':') break 1;
						$len .= (string)$buffer;
					}
					if((@socket_recv($sock, $buffer, $len, MSG_WAITALL))!==false)
					{
						if(substr($buffer, -2)=="\r\n") $buffer = substr($buffer, 0, -2);
						elseif(substr($buffer, -2)=="\n") $buffer = substr($buffer, 0, -1);
						$json = gzdecode($buffer);
						$value = $this->Payload($json);
						//$len = strlen($value); $value = $len.':'.($value?$value:'');
						if(!is_null($value)) socket_write($sock, $value, strlen($value)).chr(0);
					}
					socket_close($sock);
				}
				if(time()>$cleanup)
				{
					clearstatcache();
					$dir = opendir(__DIR__.'/cache');
					while(($item=readdir($dir))!==false)
					{
						if(substr($item, 0, 1)=='.') continue;
						$mtime = filemtime(__DIR__.'/cache/'.$item);
						$key = substr($item, 0, -4);
						$n = substr(preg_replace('/[^0-9]/', '', $key), 0, 1);
						if(time()>$mtime)
						{
							unset($this->cache[$n][$key]);
							@unlink(__DIR__.'/cache/'.$key.'.bin');
						}
						else
						{
							if(!is_array($this->cache[$n])) $this->cache[$n] = array();
							$this->cache[$n][$key] = array('expire'=>$mtime, 'value'=>null);
						}
					}
					closedir($dir);
					$cleanup = time()+30;
				}
			}
		}
	}
	public function Push(String $bin)
	{
		$buffer = null;
		if($sock=@fsockopen($this->ip, $this->port, $errno, $errstr, 1))
		{
			$bin = strlen($bin).':'.$bin;
			fwrite($sock, $bin. strlen($bin));
			while(($_buffer=fgets($sock, 1024))!==false)
			{
				if(is_null($buffer)) $buffer = '';
				$buffer .= $_buffer;
			}
			fclose($sock);
		}
		return $buffer;
	}
	private function Payload(String $json)
	{
		$arr = json_decode($json, true);
		if($arr['method']=='add')
		{
			if($arr['expire']===0) $arr['expire'] = time()+(86400*365);
			elseif(empty($arr['expire']) || !is_numeric($arr['expire'])) $arr['expire'] = time()+5;
			else $arr['expire'] = time()+(int)$arr['expire'];

			$key = md5($arr['key']); $bin = gzencode($arr['value'], 9);
			file_put_contents(__DIR__.'/cache/'.$key.'.bin', $bin);
			touch(__DIR__.'/cache/'.$key.'.bin', $arr['expire']);

			$n = substr(preg_replace('/[^0-9]/', '', $key), 0, 1);
			if(!is_array($this->cache[$n])) $this->cache[$n] = array();
			$this->cache[$n][$key] = array('expire'=>$arr['expire'], 'value'=>$bin);
		}
		if($arr['method']=='get')
		{
			$key = md5($arr['key']);
			$n = substr(preg_replace('/[^0-9]/', '', $key), 0, 1);
			if(!is_array($this->cache[$n])) $this->cache[$n] = array();
			if($this->cache[$n][$key])
			{
				if($this->cache[$n][$key]['expire']>time())
				{
					if(!is_null($this->cache[$n][$key]['value']))
					{
						$bin = $this->cache[$n][$key]['value'];
						$value = gzdecode($bin);
						return $value;
					}
					elseif(is_file(__DIR__.'/cache/'.$key.'.bin'))
					{
						$bin = file_get_contents(__DIR__.'/cache/'.$key.'.bin');
						$this->cache[$n][$key]['value'] = $bin;
						$value = gzdecode($bin);
						return $value;
					}
				}
				else
				{
					unset($this->cache[$n][$key]);
					@unlink(__DIR__.'/cache/'.$key.'.bin');
					clearstatcache();
				}
			}
			else
			{
				if(is_file(__DIR__.'/cache/'.$key.'.bin'))
				{
					$mtime = filemtime(__DIR__.'/cache/'.$key.'.bin');
					if($mtime>time())
					{
						$bin = file_get_contents(__DIR__.'/cache/'.$key.'.bin');
						$this->cache[$n][$key] = array('expire'=>$mtime, 'value'=>$bin);
						$value = gzdecode($bin);
						clearstatcache();
						return $value;
					}
					else
					{
						@unlink(__DIR__.'/cache/'.$key.'.bin');
						clearstatcache();
					}
				}
			}
		}
		return null;
	}
	private function Init()
	{
		do
		{
			try
			{
				# Ephemeral port
				$__manager_port = ($GLOBALS['__manager_port']?$GLOBALS['__manager_port']:mt_rand(32768, 60999));

				# Create a TCP Stream socket
				if($this->sock=@socket_create(AF_INET, SOCK_STREAM, SOL_TCP))
				{
					# Bind the socket to an address/port
					if(@socket_bind($this->sock, $this->ip, $__manager_port))
					{
						# Start listening for connections
						socket_listen($this->sock);
				
						# Non block socket type
						socket_set_block($this->sock);

						$this->port = $__manager_port;
					}
					else
					{
						throw new Exception('Failed to bind to address '.$this->ip.':'.$this->port);
					}
				}
				else
				{
					throw new Exception('Failed to create socket');
				}
			}
			catch(Exception $e)
			{
				echo "Caught exception: ".$e->getMessage()."\n";
				sleep(1);
			}
		}
		while(!is_resource($this->sock));
		return true;
	}
}