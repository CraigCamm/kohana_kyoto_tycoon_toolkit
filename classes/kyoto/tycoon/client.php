<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Manages the low-level communication with the Kyoto Tycoon server using
 * the REST Client stuff for the actual HTTP/curl logic.
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

    // Constants for Base64-encoded keywords
    const BASE64_KEY = 'a2V5';
    const BASE64_VALUE = 'dmFsdWU=';

    // Constants for tab-separated-values format
    const TAB = "\t";
    const CRLF = "\r\n";

    // Internal constants
    const NOT_ENCODED = 0;
    const BASE64_ENCODED = 1;
    const URL_ENCODED = 2;
    const DEFAULT_CONTENT_TYPE = 'text/tab-separated-values';

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
    protected $_rest_client = NULL;

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
            'content_type' => 'text/tab-separated-values; colenc=B'
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
        // Base64-encode the key
        $key = base64_encode($key);

        // Base64-encode the value
        $value = base64_encode($value);

        // If expires is set, Base64-encode it as well
        $expires = isset($expires) ? base64_encode($expires) : NULL;

        // Assemble the request string and make the request using the
        // REST client
        $result = $this->_rest_client->post('rpc/set',
            self::BASE64_KEY.self::TAB.$key.self::CRLF.
            self::BASE64_VALUE.self::TAB.$value
        );

        // If we get back anything other then a status 200
        if ($result->status !== REST_Client::HTTP_OK) {
            // Throw an exception
            throw new Kyoto_Tycoon_Exception($result->body, NULL,
                $result->status);
        }

        // Return a reference to this class instance
        return $this;
    }

    /**
     * Handles the retrieval of a single Kyoto Tycoon key/value pair.
     *
     * @param   string  The name of the key being set.
     * @return  string  The result of the Kyoto Tycoon call.
     */
    public function get($key)
    {
        // Base64-encode the key
        $key = base64_encode($key);

        // Assemble the request string and make the request using the
        // REST client
        $result = $this->_rest_client->post('rpc/get',
            self::BASE64_KEY.self::TAB.$key
        );

        // If we get back anything other then a status 200
        if ($result->status !== REST_Client::HTTP_OK) {
            // Throw an exception
            throw new Kyoto_Tycoon_Exception($result->body, NULL,
                $result->status);
        }

        // Attempt to grab the 'Content-Type' response header
        $content_type = isset($result->headers['Content-Type']) ?
            $result->headers['Content-Type'] : self::DEFAULT_CONTENT_TYPE;

        // Parse and decode the response body
        $parsed = self::_parse_tab_separated_values($content_type,
            $result->body);

        // Take the parsed table and transform it into key/value pairs
        $parsed = self::_table_to_key_value_pairs($parsed);

        // Return the value member
        return isset($parsed['value']) ? $parsed['value'] : NULL;
    }

    /**
     * Most Kyoto Tycoon responses use tab-separated-values with one of three
     * simple encodings:
     *
     *   1. Plain ASCII (no encoding)
     *   2. Base64-encoded values
     *   3. URL-encoded values
     *
     * Kyoto Tycoon decides which of these 3 encodings will yield the smallest
     * result on a request-by-request basis, so our code must know how to
     * handle all 3 possibilities.
     *
     * @param   string  The value from the 'Content-Type' header, so know
     *                  which of the 3 value decoding techniques to use.
     * @param   string  The response body to parse.
     * @return  object  A simple 2-dimensional array structure to hold the
     *                  decoded rows and columns of data.
     */
    protected static function _parse_tab_separated_values($content_type,
        $body)
    {
        // If the response is just simple tab-separated-values
        if ($content_type === 'text/tab-separated-values') {
            // There is no decoding step
            $encoding_type = self::NOT_ENCODED;
        // If the response is Base64-encoded tab-separated-values
        } elseif ($content_type === 'text/tab-separated-values; colenc=B') {
            // Base64-decoding is required
            $encoding_type = self::BASE64_ENCODED;
        // If the response is URL-encoded tab-separated-values
        } elseif ($content_type === 'text/tab-separated-values; colenc=U') {
            // URL-decoding is required
            $encoding_type = self::URL_ENCODED;
        }

        // Define an empty array to hold the parsed lines
        $parsed_lines = array();

        // Trim any whitespace off the data and break the lines apart on
        // each CRLF sequence
        $lines = explode(self::CRLF, trim($body));

        // Loop over each line of returned TSV data
        while ($line = array_shift($lines)) {
            // Define an empty array to hold the parsed columns
            $parsed_columns = array();

            // Break apart the lines on any tabs
            $columns = explode(self::TAB, $line);

            // Loop over each column
            foreach ($columns as $value) {
                // If we need to Base64 decode this column value
                if ($encoding_type === self::BASE64_ENCODED) {
                    // Parse and add this column to the parsed columns array
                    $parsed_columns[] = base64_decode($value);
                    // Move on to the next value
                    continue;
                }

                // If we need to URL decode this column value
                if ($encoding_type === self::URL_ENCODED) {
                    // Parse and add this colum to the parsed columns array
                    $parsed_columns[] = urldecode($value);
                    // Move on to the next value
                    continue;
                }

                // We dont need to decode anything
                $parsed_columns[] = $value;
            }

            // Add this set of columns parsed lines collection
            $parsed_lines[] = $parsed_columns;
        }

        // Return the parsed data
        return $parsed_lines;
    }

    /**
     * Converts a simple 2-dimensional array structure into key value pairs by
     * making the assumption that the first column in each row is unique, and
     * the second column in each row is the corresponding value.
     *
     * @param   array  A 2-dimensional array structure which represents a set
     *                 of key/value pairs.
     * @return  array  A key/value pair set.
     */
    protected static function _table_to_key_value_pairs($table)
    {
        // Define an empty array for the result data to go
        $result = array();

        // Loop over each row of data in the table
        foreach ($table as $row) {
            // The first column is the key and the second column is the
            // corresponding value for that key
            $key = array_shift($row);
            $value = array_shift($row);

            // Add this key/value pair to the result array
            $result[$key] = $value;
        }

        // Return the finished result
        return $result;
    }

} // End Kyoto_Tycoon_Client
