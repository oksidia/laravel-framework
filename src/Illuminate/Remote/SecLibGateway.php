<?php namespace Illuminate\Remote;

use Illuminate\Filesystem\Filesystem;
use phpseclib3\Crypt\Common\AsymmetricKey;
use phpseclib3\Crypt\RSA;
use phpseclib3\Net\SFTP;
use phpseclib3\System\SSH\Agent;

class SecLibGateway implements GatewayInterface {

	/**
	 * The host name of the server.
	 *
	 * @var string
	 */
	protected $host;

	/**
	 * The SSH port on the server.
	 *
	 * @var int
	 */
	protected $port = 22;

	/**
	 * The authentication credential set.
	 *
	 * @var array
	 */
	protected $auth;

	/**
	 * The filesystem instance.
	 *
	 * @var \Illuminate\Filesystem\Filesystem
	 */
	protected $files;

	/**
	 * The SecLib connection instance.
	 *
	 * @var SFTP
	 */
	protected $connection;

	/**
	 * Create a new gateway implementation.
	 *
	 * @param  string  $host
	 * @param  array   $auth
	 * @param  \Illuminate\Filesystem\Filesystem  $files
	 * @return void
	 */
	public function __construct($host, array $auth, Filesystem $files)
	{
		$this->auth = $auth;
		$this->files = $files;
		$this->setHostAndPort($host);
	}

	/**
	 * Set the host and port from a full host string.
	 *
	 * @param  string  $host
	 * @return void
	 */
	protected function setHostAndPort($host)
	{
		if ( ! str_contains($host, ':'))
		{
			$this->host = $host;
		}
		else
		{
			list($this->host, $this->port) = explode(':', $host);

			$this->port = (int) $this->port;
		}
	}

	/**
	 * Connect to the SSH server.
	 *
	 * @param string $username
	 * @return bool
	 * @throws \Illuminate\Filesystem\FileNotFoundException
	 */
	public function connect($username)
	{
		return $this->getConnection()->login($username, $this->getAuthForLogin());
	}

	/**
	 * Determine if the gateway is connected.
	 *
	 * @return bool
	 */
	public function connected()
	{
		return $this->getConnection()->isConnected();
	}

	/**
	 * Run a command against the server (non-blocking).
	 *
	 * @param  string  $command
	 * @return void
	 */
	public function run($command)
	{
		$this->getConnection()->exec($command, false);
	}

	/**
	 * Download the contents of a remote file.
	 *
	 * @param  string  $remote
	 * @param  string  $local
	 * @return void
	 */
	public function get($remote, $local)
	{
		$this->getConnection()->get($remote, $local);
	}

	/**
	 * Get the contents of a remote file.
	 *
	 * @param  string  $remote
	 * @return string
	 */
	public function getString($remote)
	{
		return $this->getConnection()->get($remote);
	}

	/**
	 * Upload a local file to the server.
	 *
	 * @param  string  $local
	 * @param  string  $remote
	 * @return void
	 */
	public function put($local, $remote)
	{
		$this->getConnection()->put($remote, $local, SFTP::SOURCE_LOCAL_FILE);
	}

	/**
	 * Upload a string to to the given file on the server.
	 *
	 * @param  string  $remote
	 * @param  string  $contents
	 * @return void
	 */
	public function putString($remote, $contents)
	{
		$this->getConnection()->put($remote, $contents);
	}

	/**
	 * Get the next line of output from the server.
	 *
	 * @return string|null
	 */
	public function nextLine()
	{
		$value = $this->getConnection()->read();

		return $value === true ? null : $value;
	}

	/**
	 * Get the authentication object for login.
	 *
	 * @return AsymmetricKey|Agent|string
	 * @throws \InvalidArgumentException|\Illuminate\Filesystem\FileNotFoundException
	 */
	protected function getAuthForLogin()
	{
		if ($this->useAgent()) return $this->getAgent();

		// If a "key" was specified in the auth credentials, we will load it into a
		// secure RSA key instance, which will be used to connect to the servers
		// in place of a password, and avoids the developer specifying a pass.
		elseif ($this->hasRsaKey())
		{
			return $this->loadRsaKey($this->auth);
		}

		// If a plain password was set on the auth credentials, we will just return
		// that as it can be used to connect to the server. This will be used if
		// there is no RSA key and it gets specified in the credential arrays.
		elseif (isset($this->auth['password']))
		{
			return $this->auth['password'];
		}

		throw new \InvalidArgumentException('Password / key is required.');
	}

	/**
	 * Determine if an RSA key is configured.
	 *
	 * @return bool
	 */
	protected function hasRsaKey()
	{
		$hasKey = (isset($this->auth['key']) && trim($this->auth['key']) != '');

		return $hasKey || (isset($this->auth['keytext']) && trim($this->auth['keytext']) != '');
	}

	/**
	 * Load the RSA key instance.
	 *
	 * @param array $auth
	 * @return AsymmetricKey
	 * @throws \Illuminate\Filesystem\FileNotFoundException
	 */
	protected function loadRsaKey(array $auth)
	{
		$key = $this->readRsaKey($auth);
		$passphrase = $auth['keyphrase'] ?? false;

		return RSA::load($key, $passphrase);
	}

	/**
	 * Read the contents of the RSA key.
	 *
	 * @param array $auth
	 * @return string
	 * @throws \Illuminate\Filesystem\FileNotFoundException
	 */
	protected function readRsaKey(array $auth)
	{
		if (isset($auth['key'])) return $this->files->get($auth['key']);

		return $auth['keytext'];
	}

	/**
	 * Determine if the SSH Agent should provide an RSA key.
	 *
	 * @return bool
	 */
	protected function useAgent()
	{
		return isset($this->auth['agent']) && $this->auth['agent'] === true;
	}

	/**
	 * Get a new SSH Agent instance.
	 *
	 * @return Agent
	 */
	public function getAgent()
	{
		return new Agent();
	}

	/**
	 * Get the exit status of the last command.
	 *
	 * @return int|bool
	 */
	public function status()
	{
		return $this->getConnection()->getExitStatus();
	}

	/**
	 * Get the host used by the gateway.
	 *
	 * @return string
	 */
	public function getHost()
	{
		return $this->host;
	}

	/**
	 * Get the port used by the gateway.
	 *
	 * @return int
	 */
	public function getPort()
	{
		return $this->port;
	}

	/**
	 * Get the underlying SFTP connection.
	 *
	 * @return SFTP
	 */
	public function getConnection()
	{
		if ($this->connection) return $this->connection;

		return $this->connection = new SFTP($this->host, $this->port);
	}

}