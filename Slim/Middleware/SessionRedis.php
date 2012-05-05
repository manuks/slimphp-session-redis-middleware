<?php
class Slim_Middleware_SessionRedis extends Slim_Middleware
{
	// stores settings
	protected $settings;

	// stores redis object
	protected $redis;

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
			'expires'			=> ini_get('session.gc_maxlifetime'),
			'name'				=> 'slim_session',
			'cookie.lifetime'	=> 0,
			'cookie.path'		=> '/',
			'cookie.domain'		=> '',
			'cookie.secure'		=> false,
			'cookie.httponly'	=> true
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
		$this->redis->connect('127.0.0.1');
		return true;
	}

	/**
	 * close
	 *
	 * @return true
	 */
	public function close()
	{
		return true;	
	}

	/**
	 * regenerate_id
	 *
	 * regenerates the session id and destroys the previous one
	 *
	 * @return void
	 */
	public function regenerate_id()
	{
		$session_id = session_id();
		session_regenerate_id();
		$this->destroy($session_id);
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
		// if our session has expired we don't wish to return the data so return the destroy method
		if ( $this->redis->hGet($session_id, 'expires') < time() ) {
			return $this->redis->destroy($session_id);
		}
		// retrieve the data for our session
		return $this->redis->hGet($session_id, 'data');
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
		// if our key exists, and it has expired, we return the destroy method
		if ( $this->redis->exists($session_id)  && $this->redis->hGet($session_id, 'expires') < time() ) {
			return $this->redis->destroy($session_id);
		}
		
		// if cookie.lifetime is set to 0 and there is no existing key in the database we set the expire time to 1 year
		// think autologin
		if ( $this->settings['cookie.lifetime'] == 0 && !$this->redis->exists($session_id) ) {
			$this->redis->hSet( $session_id, 'expires', (time() + 100000) );
		// else if the cookie lifetime is NOT endless, we set the expire time to now + settings defined lifetime
		} else if ( $this->settings['cookie.lifetime'] != 0 ) {
			$this->redis->hSet( $session_id, 'expires', (time() + $this->settings['expires']) );
		}
		
		// finally we set the data for our session		
		return $this->redis->hSet( $session_id, 'data', $session_data );
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
		$this->redis->delete($session_id);
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
