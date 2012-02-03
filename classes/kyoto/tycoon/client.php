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
     * Traps all attempts to call undefined methods on this class.
     *
     * @param   string  The name of the method being invoked.
     * @param   array   An array of arguments that were passed in.
     * @return  mixed   The result of the remote method.
     */
    public function __call($method_name, $method_parameters)
    {
        // Build the access object
        $access = (object) array(
            'type' => isset($this->_password) ? 'authentication' : 'token'
        );

        // If the password class variable is set
        if (isset($this->_password)) {
            // Add the username and password to the access object
            $access->username = $this->_username_or_token;
            $access->password = $this->_password;
        } else {
            // Add the token to the access object
            $access->token = $this->_username_or_token;
        }

        // Determine the version string to use
        $version = isset($this->_remote_api_version) ?
            $this->_remote_api_version.'_' : '';

        // Add the access object as the first parameter to the method
        array_unshift($method_parameters, $access);

        // Create the JSON-RPC request object
        $request = (object) array(
            'method' => $version.$this->_remote_class_name.'.'.$method_name,
            'params' => $method_parameters,
            'id' => sha1(uniqid(rand(), TRUE))
        );

        // Make an HTTP POST request out to the remote RPC server passing the
        // JSON-encoded request object as the POST data
        $response = $this->_http_post($this->_uri, json_encode($request),
            array('Content-Type' => 'application/json'));
           
        // If the remote web server did not respond with a success status
        if ((string) $response->status !== '200') {
            // Throw an exception
            throw new Exception('HTTP status "'.
                ((string) $response->status).'"');
        }

        // Attempt to deserialize (what should be) the JSON-encoded response
        $data = json_decode($response->data);
        
        // If the attempt to decode failed
        if ($data === NULL AND $response->data !== 'null') {
            // Throw an exception
            throw new Exception('JSON decode failure for response "'.
                ((string) $response->status).'"');
        }

        // If some kind of error occurred on the remote server, forward the
        // exception locally
        if (isset($data->error)) {
            // Grab a shortcut variable to the error member of the response
            $error = $data->error;

            // If the error value is just a string
            if ( ! is_object($error)) {
                // Throw an exception, passing the string value of the error
                throw new Exception('API SERVER ERROR: '.((string) $error));
            }

            // Grab shortcut variables to the message and code members of the
            // error response object
            $message = isset($error->message) ? $error->message : 'Error '.
                'message unavailable';
            $code = isset($error->code) ? $error->code : 0;
            $class = isset($error->class) ? $error->class : "Class unavailable";
            // Throw the exception
            throw new Exception('API SERVER ERROR: '. $class .' : '.((string) $message),
                $code);
        }

        // Return the response data
        return $data->result;
    }

    /**
     * Returns the all-lowercase version of this class instances class name.
     *
     * @return  string  The class name for this class instance, in lowercase.
     */
    protected function _get_class_name()
    {
        // Return the all-lowercase version of this class instances name
        return get_class($this);
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
