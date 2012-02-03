<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Manages the low-level communication with the Kyoto Tycoon server.
 *
 * @package    kohana_kyoto_tycoon_toolkit
 * @author     See included AUTHORS.md file.
 * @copyright  (c) 2012 Kohana Team.
 * @license    See included LICENSE.md file.
 */
class Kyoto_Tycoon_Client {

    /**
     * @var  string  The IP or DNS name of the Kyoto Tycoon server.
     */
    protected $_host = NULL;

    /**
     * @var  string  The port the remote Kyoto Tycoon server is listening on.
     */
    protected $_port = NULL;

    /**
     * Configures this class instance for making calls against a specific
     * class on the remote API server.
     *
     * @param   string  Optional. The IP address or DNS name of the Kyoto
     *                  Tycoon server. Defaults to 'localhost'.
     */
    public function __construct($host = 'localhost', $port = 1978) {
        // Store the host and port parameters that were passed in
        $this->_host = $host;
        $this->_port = $port;
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
        // 
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
