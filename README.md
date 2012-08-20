ci-settings
===========

A CodeIgniter library to make it easier to access/modify key-value settings stored in a database.

**What's Included:**
- Multiple ways to access settings found in a defined database table, using both functions and `__get`/`__set` methods.
- Ability to cache the returned values, using CodeIgniter's built-in caching driver.
- Functionality to store serialized values in the setting's table, i.e. objects/arrays.

Usage
-----

If you are using the Sparks package manager:
    
    $this->load->spark('settings/x.x.x');

Or if you are still rocking it old school:

    $this->load->library('settings');

The setting's table schema (i.e. key/value field names) can be altered inside the configuration file, along with the caching and serialization options.

To retrieve a setting's value, you can either call the provided function.

    $this->settings->get('version');

Or use the `__get` magic method.

    $this->settings->version;

All the available setting's can be retrieved as a associative array.

    $settings = $this->settings->get_all();
    echo $settings['version'];

To update or add a new setting to the table.

    $this->settings->version = 1.0.0;
    // or
    $this->settings->set('version', 1.0.0);

Providing that serialization is enabled, you can also store values without losing their type and structure.

    $this->settings->details = array('hello', 'world');

Finally, to force reload the settings values from the table and expire any present cache.

    $this->settings->reload_settings(TRUE);