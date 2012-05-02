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
		$this->settings = array_merge(array(
			'expires'		=> (1000*60*20), // session lifetime
			'name'			=> 'slim_session', // session name
		), $settings);

		// if the setting for the expire is a string typecast it as an int
		if ( is_string($this->settings['expires']) )
			$this->settings['expires'] = (Int) $this->settings['expires'];
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
	public function open( $path, $name )
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
	 * @return null
	 */
	public function read( $session_id )
	{
		return $this->redis->get($session_id);
	}

	/**
	 * write
	 *
	 * writes session data
	 *
	 * @return null
	 */
	public function write( $session_id, $session_data )
	{
		return $this->redis->set($session_id, $session_data);
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
