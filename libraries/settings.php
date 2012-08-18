<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * CodeIgniter Settings Library
 *
 * @category   Settings
 * @package    CodeIgniter
 * @subpackage Libraries
 * @author     Edward Mann <the@eddmann.com>
 * @release    1.0.0
 * @license    MIT License Copyright (c) 2012 Edward Mann
 * @link       http://eddmann.com/
 */
class Settings {

	/**
	 * The current CodeIgniter instance.
	 *
	 * @var object
	 * @access private
	 */
	private $_ci;

	/**
	 * The settings stored in memory.
	 *
	 * @var array
	 * @access private
	 */
	private $_settings = array();

	/**
	 * The permitted config options for alteration.
	 *
	 * @var array
	 * @access private
	 */
	private $_config_whitelist = array(
		'table', 'key_field', 'value_field', 'cache', 'cache_name', 'cache_ttl', 'cache_config', 'serialize'
	);

	/**
	 * The settings table name.
	 *
	 * @var string
	 * @access public
	 */
	public $table = 'settings';

	/**
	 * The setting table's key field name.
	 *
	 * @var string
	 * @access public
	 */
	public $key_field = 'key';

	/**
	 * The setting table's value field name.
	 *
	 * @var string
	 * @access public
	 */
	public $value_field = 'value';

	/**
	 * Enable settings caching.
	 *
	 * @var boolean
	 * @access public
	 */
	public $cache = FALSE;

	/**
	 * The name used to cache the settings.
	 *
	 * @var string
	 * @access public
	 */
	public $cache_name = 'settings';
	
	/**
	 * The TTL of the cached settings contents.
	 *
	 * @var integer
	 * @access public
	 */
	public $cache_ttl = 300;

	/**
	 * The configuration options passed into CI cache driver.
	 *
	 * @var array
	 * @access public
	 */
	public $cache_config = array('adapter' => 'file');

	/**
	 * Enable serialization when storing settings.
	 *
	 * @var boolean
	 * @access public
	 */
	public $serialize = FALSE;

	/**
	 * Constructer
	 *
	 * Retrieves the CI instance in use and then attempts to
	 * override the config options based on the config file,
	 * then passed in options. Once set it, then moves onto
	 * loading the cache driver if enabled and then reloading the
	 * settings.
	 *
	 * @param array $config Any config options to override
	 * 	 
	 * @access public
	 */
	public function __construct(array $config = array())
	{
		$this->_ci =& get_instance();

		if (is_array(config_item('settings')))
			$config = array_merge(config_item('settings'), $config);

		if ( ! empty($config))
			$this->_initialize($config);

		if ($this->cache)
			$this->_ci->load->driver('cache', $this->cache_config);

		$this->reload_settings();
	}

	/**
	 * Initalize Class
	 *
	 * Overrides the whitelisted default config options with the
	 * ones passed in as a param.
	 *
	 * @param array $config Any config options to override
	 *
	 * @return void
	 * @access private
	 */
	private function _initialize(array $config = array())
	{
		foreach ($config as $c_key => $c_value) {
			if (in_array($c_key, $this->_config_whitelist)) {
				$this->{$c_key} = $value;
			}
		}
	}

	/**
	 * Reload Settings
	 *
	 * Reloads the stored settings either from a valid cache or
	 * the defined table. Passing in TRUE will force the reload to
	 * ignore any current cache and reload the contents from the database.
	 *
	 * @param boolean $force Force reload from the table
	 * 
	 * @return boolean
	 * @access public
	 */
	public function reload_settings($force = FALSE)
	{
		$this->_settings = array();

		// use the cached settings if caching is enabled, force load is disabled and a valid cache exists
		if ( ! $force AND $this->cache AND ($settings = $this->_CI->cache->get($this->cache_name))) {
			$this->_settings = $settings;
		}
		// reload the settings from the database
		else {
			$settings = $this->_ci->db->get($this->table)->result();

			foreach ($settings as $setting) {
				$setting_key   = $setting->{$this->key_field};
				$setting_value = $setting->{$this->value_field};

				// if serialization is disabled convert all specially stored 
				// types into their PHP equivalent.
				// credit: http://codeigniter.com/forums/viewthread/220472
				if ( ! $this->serialize) {
					if ($setting_value === '|false|') $setting_value = FALSE;
					if ($setting_value === '|true|')  $setting_value = TRUE;
					if ($setting_value === '|null|')  $setting_value = NULL;
				}
				else {
					// if serialized value is present unserialize it
					if ($this->_is_serialized($value))
						$setting_value = unserialize($setting_value);
				}

				$this->_settings[$setting_key] = $setting_value;
			}

			// cache the settings if enabled
			if ($this->cache)
				$this->_ci->cache->save($this->cache_name, $this->_settings, $this->cache_ttl);
		}

		return TRUE;
	}

	/**
	 * Get Setting
	 *
	 * Returns the desired settings value from the array,
	 * If the setting does not exist an exception is raised.
	 *
	 * @param string $setting_key The setting's name
	 *
	 * @throws Exception If invalid setting key supplied
	 * @return mixed
	 * @access public
	 */
	public function get($setting_key)
	{
		if (array_key_exists($setting_key, $this->_settings)) {
			return $this->_settings[$setting_key];
		}
		else {
			throw new Exception('Invalid setting key specified.');
		}
	}

	/**
	 * Get All Settings
	 *
	 * Returns the private settings array for public use.
	 *
	 * @return array
	 * @access public
	 */
	public function get_all()
	{
		return $this->_settings;
	}

	/**
	 * Set Setting
	 *
	 * Either creates or updates the provided Setting name string with
	 * the defined value. If serialization is disabled an exception
	 * is thrown if anything but a scalar/null type is passed in for
	 * storing.
	 *
	 * @param string $setting_key   The setting's name
	 * @param mixed  $setting_value The setting's value
	 *
	 * @throws Exception If a none string setting key is passed in
	 * @throws Exception If serialization disabled and none scalar/null val entered
	 * @return boolean
	 * @access public
	 */
	public function set($setting_key, $setting_value)
	{
		// throw an exception if the setting name is not a string
		if ( ! is_string($setting_key))
			throw new Exception('Strings are the only valid setting name type.');

		if ( ! $this->serialize) {
			// throw an exception if serialization is disabled and anything but 
			// a scalar/null value type is passed in
			if ( ! is_scalar($setting_value) AND ! is_null($setting_value))
			throw new Exception('Only scalar/null types are permitted when serialization is disabled.');

			// convert certian PHP types into specially stored equivents
			if ($setting_value === FALSE) $setting_value = '|false|';
			if ($setting_value === TRUE)  $setting_value = '|true|';
			if ($setting_value === NULL)  $setting_value = '|null|';
		}
		else {
			$setting_value = serialize($value);
		}

		// update the setting in the database if the key is already present
		if (array_key_exists($setting_key, $this->_settings)) {
			$this->_ci->db
				->where($this->key_field, $setting_key)
				->update($this->table, array($this->value_field => $setting_value));
		}
		// if a new setting is entered, insert it into the database
		else {
			$this->_ci->db->insert($this->table, array(
				$this->key_field   => $setting_key,
				$this->value_field => $setting_value
			));
		}

		if ($this->cache) {
			// refresh the cached settings if enabled 
			$this->_ci->cache->delete($this->cache_name);
			$this->_ci->cache->save($this->cache_name, $this->_settings, $this->cache_ttl);
		}

		return TRUE;
	}
	
	/**
	 * Get (Magic Method)
	 * 
	 * Wrapper around the defined get method, provides easier access
	 * to the settings.
	 *
	 * @param string $setting_key The setting's name
	 *
	 * @return mixed
	 * @access public
	 */
	public function __get($setting_key)
	{
		return $this->get($setting_key);
	}

	/**
	 * Set (Magic Method)
	 *
	 * Wrapper around the defined set method, provides easier access
	 * to the settings.
	 *
	 * @param string $setting_key   The setting's name
	 * @param string $setting_value The setting's value
	 * 
	 * @return mixed
	 * @access public
	 */
	public function __set($setting_key, $setting_value)
	{
		return $this->set($setting_key, $setting_value);
	}

	/**
	 * Value Serialization Check
	 *
	 * Returns whether the passed in value looks like a serialized value
	 *
	 * @param mixed $value The value to check
	 * 
	 * @link http://www.php.net/manual/en/function.unserialize.php#93389
	 * @return boolean
	 * @access private
	 */
	private function _is_serialized($value) 
	{
		if ( ! is_string($value)) return FALSE; 
		if (trim($value) === '')  return FALSE;
		if (preg_match('/^(i|s|a|o|d):(.*);/si', $value) !== FALSE) return TRUE;
		
		return FALSE;
	}

}


/* End of file settings.php */
/* Location: ./sparks/settings/1.0.0/libraries/settings.php */