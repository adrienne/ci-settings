<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * CodeIgniter Settings Library
 *
 * @author     Edward Mann <the@eddmann.com>
 * @link       http://eddmann.com/
 * @version    1.0.0
 * @license    MIT License Copyright (c) 2012 Edward Mann
 */
class Settings {

  /**
   * The current CodeIgniter instance
   *
   * @access    private
   * @var       object
   */
  private $_CI;

  /**
   * The settings stored in memory
   *
   * @access    private
   * @var       array
   */
  private $_settings = array();

  /**
   * The permitted config options for alteration
   *
   * @access    private
   * @var       array
   */
  private $_config_whitelist = array(
    'table', 'key_field', 'value_field', 'cache', 'cache_name', 'cache_ttl', 'cache_config', 'serialize'
  );

  /**
   * The settings table name
   *
   * @access    public
   * @var       string
   */
  public $table = 'settings';

  /**
   * The setting table's key field name
   *
   * @access    public
   * @var       string
   */
  public $key_field = 'key';

  /**
   * The setting table's value field name
   *
   * @access    public
   * @var       string
   */
  public $value_field = 'value';

  /**
   * Enable settings caching
   *
   * @access    public
   * @var       bool
   */
  public $cache = FALSE;

  /**
   * The name used to cache the settings
   *
   * @access    public
   * @var       string
   */
  public $cache_name = 'settings';
  
  /**
   * The TTL of the cached settings contents
   *
   * @access    public
   * @var       int
   */
  public $cache_ttl = 300;

  /**
   * The configuration options passed into CI cache driver
   *
   * @access    public
   * @var       array
   */
  public $cache_config = array('adapter' => 'file');

  /**
   * Enable serialization when storing settings
   *
   * @access    public
   * @var       bool
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
   * @access    public
   * @param     array     $config
   */
  public function __construct(array $config = array())
  {
    $this->_CI =& get_instance();

    if (is_array(config_item('settings')))
      $config = array_merge(config_item('settings'), $config);

    if ( ! empty($config))
      $this->_initialize($config);

    if ($this->cache)
      $this->_CI->load->driver('cache', $this->cache_config);

    $this->reload_settings();
  }

  /**
   * Initalize Class
   *
   * Overrides the whitelisted default config options with the
   * ones passed in as a param.
   *
   * @access    private
   * @param     array      $confg
   */
  private function _initialize(array $config = array())
  {
    foreach ($config as $key => $value) {
      if (in_array($key, $this->_config_whitelist)) {
        $this->{$key} = $value;
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
   * @access    public
   * @param     bool      $force    Force reload from the table
   * @return    bool
   */
  public function reload_settings($force = FALSE)
  {
    $this->_settings = array();

    // use the cached settings if caching is enabled, force load is disabled and a valid cache exists
    if ( ! $force && $this->cache && ($settings = $this->_CI->cache->get($this->cache_name))) {
      $this->_settings = $settings;
    }
    // reload the settings from the database
    else {
      $settings = $this->_CI->db->get($this->table)->result();

      foreach ($settings as $setting) {
        $key   = $setting->{$this->key_field};
        $value = $setting->{$this->value_field};

        // if serialization is disabled convert all specially stored 
        // types into their PHP equivalent.
        // credit: http://codeigniter.com/forums/viewthread/220472
        if ( ! $this->serialize) {
          if ($value === '|false|') $value = FALSE;
          if ($value === '|true|')  $value = TRUE;
          if ($value === '|null|')  $value = NULL;
        }
        else {
          // if serialized value is present unserialize it
          if ($this->_is_serialized($value)) {
            $value = unserialize($value);
          }
        }

        $this->_settings[$key] = $value;
      }

      // cache the settings if enabled
      if ($this->cache)
        $this->_CI->cache->save($this->cache_name, $this->_settings, $this->cache_ttl);
    }

    return TRUE;
  }

  /**
   * Get Setting
   *
   * Returns the desired settings value from the array,
   * If the setting does not exist an exception is raised.
   *
   * @access    public
   * @param     string    $key    The setting's name
   * @return    mixed
   */
  public function get($key)
  {
    if (array_key_exists($key, $this->_settings)) {
      return $this->_settings[$key];
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
   * @access    public
   * @return    array
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
   * @access    public
   * @param     string    $key      The setting's name
   * @param     mixed     $value    The setting's value
   * @return    bool
   */
  public function set($key, $value)
  {
    // throw an exception if the setting name is not a string
    if ( ! is_string($key))
      throw new Exception('Strings are the only valid setting name type.');

    if ( ! $this->serialize) {
      // throw an exception if serialization is disabled and anything but 
      // a scalar/null value type is passed in
      if ( ! is_scalar($value) && ! is_null($value))
        throw new Exception('Only scalar/null types are permitted when serialization is disabled.');

      // convert certian PHP types into specially stored equivents
      if ($value === FALSE) $value = '|false|';
      if ($value === TRUE)  $value = '|true|';
      if ($value === NULL)  $value = '|null|';
    }
    else {
      $value = serialize($value);
    }

    // update the setting in the database if the key is already present
    if (array_key_exists($key, $this->_settings)) {
      $this->_CI->db->where($this->key_field, $key)
                    ->update($this->table, array($this->value_field => $value));
    }
    // if a new setting is entered, insert it into the database
    else {
      $this->_CI->db->insert($this->table, array(
        $this->key_field   => $key,
        $this->value_field => $value
      ));
    }

    if ($this->cache) {
      // refresh the cached settings if enabled 
      $this->_CI->cache->delete($this->cache_name);
      $this->_CI->cache->save($this->cache_name, $this->_settings, $this->cache_ttl);
    }

    return TRUE;
  }
  
  /**
   * Get (Magic Method)
   * 
   * Wrapper around the defined get method, provides easier access
   * to the settings.
   *
   * @access    public
   * @param     string    $key    The setting's name
   * @return    mixed
   */
  public function __get($key)
  {
    return $this->get($key);
  }

  /**
   * Set (Magic Method)
   *
   * Wrapper around the defined set method, provides easier access
   * to the settings.
   *
   * @access    public
   * @param     string    $key      The setting's name
   * @param     string    $value    The setting's value
   * @param     mixed
   */
  public function __set($key, $value)
  {
    return $this->set($key, $value);
  }

  /**
   * Value Serialization Check
   *
   * Returns whether the passed in value looks like a serialized value
   *
   * @access    public
   * @link      http://www.php.net/manual/en/function.unserialize.php#93389
   * @param     mixed     $value    The value to check
   * @return    bool
   */
  private function _is_serialized($val) 
  {
    if ( ! is_string($val)) return FALSE; 
    if (trim($val) === "")  return FALSE;
    if (preg_match('/^(i|s|a|o|d):(.*);/si', $val) !== FALSE) return TRUE;
    
    return FALSE;
  }

}


/* End of file Settings.php */
/* Location: ./sparks/settings/1.0.0/libraries/Settings.php */