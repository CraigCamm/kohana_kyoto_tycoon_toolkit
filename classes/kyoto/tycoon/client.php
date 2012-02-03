<?php
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

	/**
	 * Makes a simple POST HTTP request out to the the remote RPC server.
	 *
	 * @param   string  The URI to make the HTTP request against.
	 * @param   string  The raw POST data to send up.
	 * @param   array   An array of key value pairs to transform into headers.
	 * @return  object  A object with status and data members.
	 */
	protected function _http_post($uri = NULL, $post = NULL, $headers = NULL)
    {
		// Initialize the CURL library
		$curl_request = curl_init();

		// No matter what type of request this is we always need the URI
		curl_setopt($curl_request, CURLOPT_URL, $uri);

        // Set this request up as a POST request
        curl_setopt($curl_request, CURLOPT_POST, TRUE);

        // Set the POST data to send up
        curl_setopt($curl_request, CURLOPT_POSTFIELDS, $post);

		// Make sure that we get data back when we call exec
		curl_setopt($curl_request, CURLOPT_RETURNTRANSFER, TRUE);

		// If we have headers that we need to send up with the request
		if ($headers !== NULL)
		{
			// Loop over the headers that were passed in
			foreach ($headers as $key => $value)
			{
				// Collapse the key => value pair into one line
				$simple_headers[] = $key.': '.$value;
			}

			// Set the headers we want to send up
			curl_setopt($curl_request, CURLOPT_HTTPHEADER, $simple_headers);
		}

		// Run the request, get the status, close the request
		$data = curl_exec($curl_request);
		$status = curl_getinfo($curl_request, CURLINFO_HTTP_CODE);

        // Return the HTTP status code and the data
		return (object) array(
            'status' => $status,
            'data' => $data
        );
	}

} // End Kyoto_Tycoon_Client
