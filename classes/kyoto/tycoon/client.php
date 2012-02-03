<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Manages the low-level communication with the Kyoto Tycoon server.
 *
 * @package    Kohana/Kyoto Tycoon Client
 * @category   Extension
 * @author     Kohana Team
 * @copyright  (c) 2011-2012 Kohana Team
 * @license    http://kohanaphp.com/license
 */
class Kyoto_Tycoon_Client {

	/**
	 * @var  string  The default instance name.
	 */
	public static $default = 'default';

	/**
	 * @var  array  References to all of the client instances.
	 */
	public static $instances = array();

	/**
	 * Get a singleton object instance of this class. If configuration is not
	 * specified, it will be loaded from the kyoto configuration file using
	 * the same group as the provided name.
	 *
	 *     // Load the default client instance
	 *     $client = Kohana_Tycoon_Client::instance();
	 *
	 *     // Create a custom configured instance
	 *     $client = Kohana_Tycoon_Client::instance('custom', $config);
	 *
	 * @param   string   instance name
	 * @param   array    configuration parameters
	 * @return  Kyoto_Tycoon_Client
	 */
	public static function instance($name = NULL, $config = NULL)
	{
		if ($name === NULL)
		{
			// Use the default instance name
			$name = self::$default;
		}

		if ( ! isset(self::$instances[$name]))
		{
            // If a configuration array was passed in
            if (is_array($config)) {
                // Define a default set of configuration options
                $defaults = array(
                    'host' => '127.0.0.1',
                    'port' => '1978'
                );

                // Overlay the passed configuration information on top of
                // the defaults
                $config = array_merge($defaults, $config);
            }

            // If no configuration options were passed in
			if ($config === NULL) {
				// Load the configuration for this client
				$config = Kohana::$config->load('kyoto')->get($name);
			}

			// Create the client instance
			new Kyoto_Tycoon_Client($name, $config);
		}

		return self::$instances[$name];
	}

	/**
     * @var  string  Holds the instance name.
     */
	protected $_instance = NULL;

	/**
     * @var  array  Holds the configuration settings for the remote Kyoto
     *              Tycoon server host and port.
     */
	protected $_config = array();

    /**
     * @var  object  Holds a reference to the REST_Client class instance we
     *               use to do HTTP communication with Kyoto Tycoon.
     */
    protected $_rest = NULL;

	/**
	 * Stores the client configuration locally and names the instance.
	 *
	 * [!!] This method cannot be accessed directly, you must use [Kyoto_Tycoon_Client::instance].
	 *
	 * @return  void
	 */
	protected function __construct($name, array $config)
	{
		// Set the instance name
		$this->_instance = $name;

		// Store the config locally
		$this->_config = $config;

		// Store this client instance
		self::$instances[$name] = $this;

        // Create a new REST_Client to do the HTTP communication with the
        // Kyoto Tycoon server
        $this->_rest_client = REST_Client::instance($name, array(
            'uri' => 'http://'.$config['host'].':'.$config['port'].'/',
            'content_type' => 'text/tab-separated-values'
        ));
	}

    /**
     * Handles the setting of a single Kyoto Tycoon key/value pair.
     *
     * @param   string  The name of the key being set.
     * @param   string  The value to assign to the key.
     * @param   int     The number of seconds the key should exist before it
     *                  automatically expires. Defaults to NULL, or forever.
     * @return  object  A reference to this class instance, so we can do
     *                  method chaining.
     */
    public function set($key, $value, $expires = NULL)
    {
        // Grab a reference to the rest client 
    }

    /**
     * Handles the retrieval of a single Kyoto Tycoon key/value pair.
     *
     * @param   string  The name of the key being set.
     * @return  string  The result of the Kyoto Tycoon call.
     */
    public function get($key)
    {
    }

} // End Kyoto_Tycoon_Client
