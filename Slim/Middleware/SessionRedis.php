<?php
/**
 * Session Redis
 *
 * This class provides a session store for SlimPHP using Redis
 * memory data storage.
 *
 * This is still in it's early stages so it doesn't do much beyond basic functionality
 * nor does it perform garbage collection, yet.
 *
 * @package  Slim
 * @author   importlogic
 * @depends  phpredis(https://github.com/nicolasff/phpredis)
 * @version  0.1
 */
class Slim_Middleware_SessionRedis extends Slim_Middleware
{
	// stores settings
	protected $settings;

	// stores redis object
	protected $redis;

	protected $session_stat = array();

	/**
	 * Constructor
	 *
	 * sets the settings to their new values or uses the default values
	 *
	 * @param (Array) $settings
	 * @return void
	 */
	public function __construct( $settings = array() )
	{
		// A neat way of doing setting initialization with default values
		$this->settings = array_merge(array(
			'expires'		=> ini_get('session.gc_maxlifetime'),
			'name'			=> 'slim_session',
			'cookie.lifetime'	=> 0,
			'cookie.path'		=> '/',
			'cookie.domain'		=> '',
			'cookie.secure'		=> false,
			'cookie.httponly'	=> true,
			'sessionid'		=> ''
		), $settings);

		// if the setting for the expire is a string convert it to an int
		if ( is_string($this->settings['expires']) )
			$this->settings['expires'] = intval($this->settings['expires']);

		// cookies blah!
		session_name($this->settings['name']);
		session_set_cookie_params(
			$this->settings['cookie.lifetime'],
			$this->settings['cookie.path'],
			$this->settings['cookie.domain'],
			$this->settings['cookie.secure'],
			$this->settings['cookie.httponly']
		);

		// overwrite the default session handler to use this classes methods instead
		session_set_save_handler(
			array($this, 'open'),
			array($this, 'close'),
			array($this, 'read'),
			array($this, 'write'),
			array($this, 'destroy'),
			array($this, 'gc')
		);
		register_shutdown_function('session_write_close');
	}

	/**
	 * call
	 *
	 * slim imposed method, must call $this->next->call() or the middleware will stop in its tracks
	 *
	 * @return void
	 */
	public function call()
	{

		session_id($this->settings['sessionid']);

		// start our session
		session_start();
		// tell slim it's ok to continue!
		$this->next->call();
	}

	/**
	 * open
	 *
	 * creates a new connection with our redis server
	 *
	 * @return true
	 */
	public function open( $session_path, $session_name )
	{
		$this->redis = new Redis();
		$this->redis->pconnect('127.0.0.1', 6379, 2);
		//$this->redis->select($session_name);
		
		return true;
	}

	/**
	 * close
	 *
	 * @return true
	 */
	public function close()
	{
		$this->redis = null;
		return true;
	}


	/**
	 * read
	 *
	 * reads session data
	 *
	 * @return Array
	 */
	public function read( $session_id )
	{
		$key = session_name().":".$session_id;

		$sess_data = $this->redis->get($key);
		if ($sess_data === NULL)
		{
			return "";
		}
		$this->redis->session_stat[$key] = md5($sess_data);
	
		return $sess_data;
	}

	/**
	 * write
	 *
	 * writes session data
	 *
	 * @return True|False
	 */
	public function write( $session_id, $session_data )
	{
		$key = session_name().":".$session_id;
		$lifetime = $this->settings['expires'];//ini_get("session.gc_maxlifetime");

		//check if anything changed in the session, only send if has changed
		if (!empty($this->redis->session_stat[$key]) && $this->redis->session_stat[$key] == md5($session_data)) {
			//just sending EXPIRE should save a lot of bandwidth!
			$this->redis->setTimeout($key, $lifetime);
		} else {
			$this->redis->setex($key, $lifetime, $session_data);
		}

	}

	/**
	 * destroy
	 *
	 * destroys session
	 *
	 * @return true
	 */
	public function destroy( $session_id )
	{
		$this->redis->delete(session_name().":".$session_id);
		return true;
	}

	/**
	 * gc
	 *
	 * @return true
	 */
	public function gc()
	{
		// Take out the trash
		return true;
	}

	/**
	 * Destructor
	 *
	 * do things
	 *
	 * @return void
	 */
	public function __destruct()
	{
		session_write_close();
	}
}
?>