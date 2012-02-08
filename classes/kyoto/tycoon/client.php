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
                $config = Kohana::$config->load('kyototycoon')->get($name);
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
    const LF = "\n";

    // Internal constants
    const NOT_ENCODED = 0;
    const BASE64_ENCODED = 1;
    const URL_ENCODED = 2;

    // Content-Type header values
    const CONTENT_TYPE_NOT_ENCODED = 'text/tab-separated-values';
    const CONTENT_TYPE_BASE64_ENCODED = 'text/tab-separated-values; colenc=B';
    const CONTENT_TYPE_URL_ENCODED = 'text/tab-separated-values; colenc=U';


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
            'content_type' => self::CONTENT_TYPE_BASE64_ENCODED,
        ));
    }

    /**
     * Does a simple test to confirm that we get an HTTP Status 200 back from
     * the Kyoto Tycoon server.
     *
     * @return  object  A reference to this class instance, so we can do
     *                  method chaining.
     */
    public function void()
    {
        // Make the Kyoto Tycoon RPC request
        $response = $this->_rpc('void', array());

        // Return a reference to this class instance
        return $this;
    }

    /**
     * Returns a report from the Kyoto Tycoon server.
     *
     * @return  array   A set of key/value pairs.
     */
    public function report()
    {
        // Make the Kyoto Tycoon RPC request
        return $this->_rpc('report', array());
    }

    /**
     * Returns the status of the Kyoto Tycoon server.
     *
     * @return  array   A set of key/value pairs.
     */
    public function status()
    {
        // Make the Kyoto Tycoon RPC request
        return $this->_rpc('status', array());
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
        // Define the body of the request
        $request = array(
            'key' => $key,
            'value' => $value,
        );

        // If expires is set
        if (isset($expires)) {
            // Add it to the request
            $request['xt'] = $expires;
        }

        // Make the Kyoto Tycoon RPC request
        $response = $this->_rpc('set', $request);

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
        // Make the Kyoto Tycoon RPC request
        $response = $this->_rpc('get', array('key' => $key));

        // Return the value member
        return isset($response['value']) ? $response['value'] : NULL;
    }

    /**
     * Attempts to increment a single Kyoto Tycoon key/value pair.
     *
     * @param   string  The name of the key being incremented.
     * @param   int     Optional. The additional number. Defaults to 1.
     * @param   int     Optional. The origin number. Defaults to 0.
     * @return  int     The incremented number.
     */
    public function increment($key, $additional_number = 1, $origin = NULL,
        $expires = NULL)
    {
        // Define the body of the request
        $request = array(
            'key' => $key,
            'num' => $additional_number,
        );

        // If the origin number is set
        if (isset($origin)) {
            // Add it to the request
            $request['orig'] = $origin;
        }

        // If expires is set
        if (isset($expires)) {
            // Add it to the request
            $request['xt'] = $expires;
        }

        // Make the Kyoto Tycoon RPC request
        $response = $this->_rpc('increment', $request);

        // Return the incremented number
        return isset($response['num']) ? $response['num'] : NULL;
    }
    /**
     * Performs a Kyoto Tycoon RPC request over HTTP POST, encoding the passed
     * request data and decoding the response.
     *
     * @param   string   The Kyoto Tycoon RPC method to call.
     * @param   array    The table of request data to send.
     * @param   boolean  Optional. If we should assume we are encoding and
     *                   decoding key/value pairs. Defaults to TRUE.
     * @return  array    The parsed response data table.
     */
    protected function _rpc($method, $data, $key_value_pairs = TRUE)
    {
        // If we were passed key/value pairs as data
        if ($key_value_pairs) {
            // Transform the key/value pairs into a simple data table
            $data = self::_key_value_pairs_to_table($data);
        }

        // Generate the request TSV data string
        $data = self::_serialize_tab_separated_values(
            self::CONTENT_TYPE_BASE64_ENCODED, $data);

        // Make the request using the the REST Client's POST method
        $result = $this->_rest_client->post('rpc/'.$method, $data);

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
        $parsed = self::_unserialize_tab_separated_values($content_type,
            $result->body);

        // If we should transform the returned data table into key/value pairs
        if ($key_value_pairs) {
            // Transform the return data table into key/value pairs
            return self::_table_to_key_value_pairs($parsed);
        }

        // Return the parsed data
        return $parsed;
    }

    /**
     * Encodes the passed 2-dimensional array structure into a TSV formatted
     * string.
     *
     * @param   string  The value of the 'Content-Type' header, so we know
     *                  which of the 3 encodings to use.
     * @param   array   An array of arrays. Each top-level array is a row, and
     *                  each second-level array is a set of columns.
     * @return  string  The TSV formatted string for the passed data.
     */
    protected static function _serialize_tab_separated_values($content_type,
        $rows)
    {
        // Determine the encoding type to use based on the Content-Type string
        $encoding_type = self::_get_encoding_type($content_type);

        // Loop over the rows that were passed in
        foreach ($rows as &$row) {
            // Loop over each of the columns in this row
            foreach ($row as &$column) {
                // If we need to Base64 encode this column value
                if ($encoding_type === self::BASE64_ENCODED) {
                    // Parse and add this column to the parsed columns array
                    $column = base64_encode($column);
                    // Move on to the next value
                    continue;
                }

                // If we need to URL decode this column value
                if ($encoding_type === self::URL_ENCODED) {
                    // Parse and add this colum to the parsed columns array
                    $column = urlencode($column);
                    // Move on to the next value
                    continue;
                }
            }

            // Implode this row of data with a tab character between
            // each column
            $row = implode(self::TAB, $row);
        }

        // Implode all of the rows with LF between each row
        return implode(self::LF, $rows);
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
     * @param   string  The TSV formatted string to parse.
     * @return  object  A simple 2-dimensional array structure to hold the
     *                  decoded rows and columns of data.
     */
    protected static function _unserialize_tab_separated_values($content_type,
        $data)
    {
        // Determine the encoding type to use based on the Content-Type string
        $encoding_type = self::_get_encoding_type($content_type);

        // Define an empty array to hold the parsed rows
        $parsed_rows = array();

        // Trim any whitespace off the data and break the rows apart on
        // each LF sequence
        $rows = explode(self::LF, trim($data));

        // Loop over each row of returned TSV data
        while ($row = array_shift($rows)) {
            // Define an empty array to hold the parsed columns
            $parsed_columns = array();

            // Break apart the rows on any tabs
            $columns = explode(self::TAB, $row);

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

            // Add this set of columns parsed rows collection
            $parsed_rows[] = $parsed_columns;
        }

        // Return the parsed data
        return $parsed_rows;
    }

    /**
     * Converts a set of key/value pairs into a simple 2-dimensional array
     * structure representing a table with rows at the top-level, and columns
     * at the second-level.
     *
     * @param   array  A set of key/value pairs.
     * @return  array  A simple 2-dimentional array structure representing
     *                 a table.
     */
    protected static function _key_value_pairs_to_table($key_value_pairs)
    {
        // Define an empty array for the result data to go
        $result = array();

        // Loop over each key value pair
        foreach ($key_value_pairs as $key => $value) {
            // Add this key value pair to the result array as a row
            $result[] = array($key, $value);
        }

        // Return the finished result
        return $result;
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

    /**
     * Returns which encoding constant to use based on the passed
     * Content-Type string.
     *
     * @param   string  The Content-Type header value to use.
     * @retrun  int  
     */
    protected static function _get_encoding_type($content_type)
    {
        // If the response is just simple tab-separated-values
        if ($content_type === self::CONTENT_TYPE_NOT_ENCODED) {
            // There is no decoding step
            return self::NOT_ENCODED;
        // If the response is Base64-encoded tab-separated-values
        } elseif ($content_type === self::CONTENT_TYPE_BASE64_ENCODED) {
            // Base64-decoding is required
            return self::BASE64_ENCODED;
        // If the response is URL-encoded tab-separated-values
        } elseif ($content_type === self::CONTENT_TYPE_URL_ENCODED) {
            // URL-decoding is required
           return self::URL_ENCODED;
        }

        // We did not find a valid Content-Type, so throw an exception
        throw new Kyoto_Tycoon_Exception('Invalid Content-Type '.
            '":content_type".', array(':content_type' => $content_type));
    }

} // End Kyoto_Tycoon_Client
