<?php
/**
 * A class to handle most common ZMQ requests
 *
 * @author Aleksei Vesnin <dizeee@dizeee.ru>
 * @version 1.1
 * @license MIT
 * @link https://github.com/dizeee/php-quick-zmq
 */
class QuickZMQ
{

	/**
	 * @var null|ZMQSocketException
	 */
	protected static $error = null;

	/**
	 * Connect to ZMQ socket
	 *
	 * @param string $address Socket address
	 * @param integer $socket_type ZeroMQ socket type. One of ZMQ::SOCKET_*
	 * @return bool|ZMQSocket Created ZMQ socket or false on error
	 */
	public static function connect($address, $socket_type = ZMQ::SOCKET_REQ)
	{
		self::$error = null;
		try
		{
			$context = new ZMQContext();
			$socket = $context->getSocket($socket_type);
			$socket->connect($address);
			return $socket;
		}
		catch (ZMQSocketException $e)
		{
			self::$error = $e;
			return false;
		}
	}

	/**
	 * Bind a ZMQ socket
	 *
	 * @param string $address Socket address
	 * @param integer $socket_type ZeroMQ socket type. One of ZMQ::SOCKET_*
	 * @return bool|ZMQSocket Created ZMQ socket or false on error
	 */
	public static function bind($address, $socket_type = ZMQ::SOCKET_REQ)
	{
		self::$error = null;
		try
		{
			$context = new ZMQContext();
			$socket = $context->getSocket($socket_type);
			$socket->bind($address);
			return $socket;
		}
		catch (ZMQSocketException $e)
		{
			self::$error = $e;
			return false;
		}
	}

	/**
	 * Send a message and return socket connected to
	 *
	 * @param string $address Socket address
	 * @param mixed $message A message to send into the queue
	 * @param integer $socket_type ZeroMQ socket type. One of ZMQ::SOCKET_*
	 * @return bool|ZMQSocket Created ZMQ socket or false on error
	 */
	public static function send($address, $message, $socket_type = ZMQ::SOCKET_REQ)
	{
		self::$error = null;
		if ($socket = self::connect($address, $socket_type))
		{
			try
			{
				$socket->send($message, ZMQ::MODE_NOBLOCK);
				return $socket;
			}
			catch (ZMQSocketException $e)
			{
				self::$error = $e;
			}
		}
		return false;
	}

	/**
	 * Push data to a socket
	 * @param string $address Socket address
	 * @param mixed $message A message to send into the queue
	 * @return bool true on success, false on error
	 */
	public static function push($address, $message)
	{
		self::$error = null;
		if ($socket = self::connect($address, ZMQ::SOCKET_PUSH))
		{
			try
			{
				$socket->send($message);
				return true;
			}
			catch (ZMQSocketException $e)
			{
				self::$error = $e;
			}
		}
		return false;
	}

	/**
	 * Send a message and get a reply
	 *
	 * @param string $address Socket address
	 * @param mixed $message A message to send into the queue
	 * @return bool|string The reply or false on error
	 */
	public static function get($address, $message)
	{
		self::$error = null;
		if ($socket = self::send($address, $message, ZMQ::SOCKET_REQ))
		{
			try
			{
				return $socket->recv();
			}
			catch (ZMQSocketException $e)
			{
				self::$error = $e;
			}
		}
		return false;
	}

	/**
	 * Send a message and try to get a reply for a specified amount of time and a number of retries
	 *
	 * @param string $address Socket address
	 * @param mixed $message A message to send into the queue
	 * @param integer $retry_timeout Timeout between retries in milliseconds
	 * @param integer $max_retries Maximum number of retries
	 * @return mixed|bool|null The reply on success, false on error, or null when timeout is exceeded
	 */
	public static function getNoBlock($address, $message, $retry_timeout = 5, $max_retries = 20)
	{
		self::$error = null;
		if ($socket = self::connect($address, ZMQ::SOCKET_REQ))
		{
			try
			{
				$retries = 0;

				// Start a loop
				$sending = true;
				do
				{
					try
					{
						// Try to send / receive
						if ($sending)
						{
							$socket->send($message, ZMQ::MODE_NOBLOCK);
							$sending = false;
						}
						else
						{
							$response = $socket->recv(ZMQ::MODE_NOBLOCK);
							if ($response !== false)
							{
								return $response;
							}
							else
							{
								//echo " - Got EAGAIN, retrying ($retries)\n";
							}
						}
					}
					catch (ZMQSocketException $e)
					{
						// EAGAIN means that the operation would have blocked, retry
						if ($e->getCode() === ZMQ::ERR_EAGAIN)
						{
							//echo " - Got EAGAIN, retrying ($retries)\n";
						}
						else
						{
							self::$error = $e;
							return false;
						}
					}
					// Sleep a bit between operations
					usleep($retry_timeout * 1000);
				}
				while (($retries++) < $max_retries);

			}
			catch (ZMQSocketException $e)
			{
				self::$error = $e;
				return false;
			}
			return null;
		}
		return false;
	}

	/**
	 * Get a ZMQSocketException thrown during the last method call
	 *
	 * @return null|ZMQSocketException
	 */
	public static function getError()
	{
		return self::$error;
	}

}
