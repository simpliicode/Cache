<?php

class Socket Extends Run
{
	public $value = '';

	private $sock;

	final public function __construct(String $ip, Int $port)
	{
		$this->ip = $ip;
		$this->port = $port;
		$this->M = new Manager('Socket');

		# Create a TCP Stream socket
		if($this->sock=@socket_create(AF_INET, SOCK_STREAM, SOL_TCP))
		{
			# Bind the socket to an address/port
			if(@socket_bind($this->sock, $this->ip, $this->port))
			{
				# Start listening for connections
				$this->e = 'Listening for connections on '.Bo.$this->ip.':'.$this->port.Bc;
				socket_listen($this->sock);
				
				# Non block socket type
				socket_set_block($this->sock);
				
				$sock = $client = array();
				while(true)
				{
					$key = microtime();
					if(($sock[$key]=@socket_accept($this->sock))===false)
					{
						unset($sock[$key]);
					}
					elseif($sock[$key]>0)
					{
						if(!($pid=pcntl_fork()))
						{
							$this->Talk($sock[$key]);
						}
						else
						{
							foreach($sock AS $k=>$v)
							{
								if(!isset($client[$k])) continue;
								if(pcntl_waitpid($client[$k], $status, WNOHANG)==$client[$k])
								{
									socket_close($sock[$k]);
									unset($sock[$k]); unset($client[$k]);
								}
							}
							$client[$key] = $pid;
						}
					}
					else
					{
						$this->e = socket_strerror($sock[$key]);
					}
				}
				socket_close($this->sock);
				$this->e = 'Socket closed';
				return true;
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
	private function Talk($sock)
	{
		socket_getpeername($sock, $address, $port);
		$this->e = "Connected to $address on $port";

		while(true)
		{
			if((@socket_recv($sock, $buffer, 1, MSG_WAITALL))===false)
			{
				if(socket_last_error($sock))
				{
					if(socket_last_error($sock)==11)
					{
						socket_clear_error($sock);
					}
					else
					{
						$this->e = socket_strerror(socket_last_error($sock));
						break 1;
					}
				}
			}
			else
			{
				if(strlen(''.$buffer))
				{
					while((@socket_recv($sock, $_buffer, 1, MSG_WAITALL))!==false)
					{
						if(strlen(''.$_buffer)) $buffer .= $_buffer;
						if(substr($_buffer, -1)=="\n") break 1;
					}
					if(substr($buffer, -2)=="\r\n") $buffer = substr($buffer, 0, -2);
					elseif(substr($buffer, -2)=="\n") $buffer = substr($buffer, 0, -1);
					$cache = json_decode($buffer);
					if(json_last_error())
					{
						$r = json_encode(array('status'=>'error'));
					}
					else
					{
						$this->value = ($cache->method=='add'?null:'');
						$r = json_encode(array('status'=>'success'));
						$this->Cache = gzencode($buffer, 9);
						if($cache->method=='get')
						{
							$r = json_encode(array(
								'status'=>'success',
								'key'=>$cache->key,
								'value'=>$this->value
							));
						}
					}
					socket_write($sock, $r, strlen($r)).chr(0);
					$this->e = $buffer;
				}
				else
				{
					$this->e = "Connection reset by peer";
					break 1;
				}
			}
		}
		socket_close($sock);
		$this->e = "Closing connection to $address on $port";
		exit;
	}
}