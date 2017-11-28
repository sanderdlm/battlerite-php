<?php
/*
-------------- Ralph  ---------------
An interface for Runescape web data

    Copyright (c) 2017 @dreadnip
            License: MIT
https://github.com/dreadnip/ralph
*/

namespace Battlerite;

require __DIR__.'../vendor/autoload.php';
use GuzzleHttp\Client;
use GuzzleHttp\Pool;

Class api
        {
	//	Session/login variables
	private $api_key = null;
    //	Default Guzzle client & base URIs
	private $client = null;
	private $base_url = 'https://api.dc01.gamelockerapp.com/shards/global/';
	private $telemetry_url = 'https://cdn.gamelockerapp.com/stunlock-studios-battlerite/global/2017/11/22/15/37/';
	//	Debug output
    public $request_info = [];

	/*
     *  Constructor
     */

	function __construct($api_key = false)
	{
		if (!$api_key) {
			throw new \InvalidArgumentException("Passing an API key to the class is mandatory.");
		} else {
			$this->client =  new Client(['http_errors' => false]);
			$this->api_key = $api_key;
		}
	}

    public function get_json($url)
    {
    	$headers = [
    		'Accept' => 'application/vnd.api+json',
    		'Authorization' => $this->api_key,
    		'Accept-Encoding' => 'gzip',
    	];

		//perform the request
    	$response = $this->client->request('GET', $url, [
			'headers' => $headers
		]);

		//check for a good HTTP code
    	if ($response->getStatusCode() == 200) {

			//get the response content
    		$body = $response->getBody()->getContents();
		    //decode the content
    		$content = json_decode($body);

		    //if there are errors, return them as a properties of an object
    		if (isset($content->errors)) {
    			return (object)["errors" => $content->errors];
    		} else {
    			return $content->data;
    		}
    	} else {
    		return false;
    	}
    }

	/*
     *  Match functions
     */

	/**
	 * Get a collection of matches
	 * @param array $filters - See http://battlerite-docs.readthedocs.io/en/latest/matches/matches.html#get-a-collection-of-matches for the possible filters
	 * @return array of objects
	 */
	public function get_matches($filters = false)
	{
		if (!$filters) {
			return $this->get_json($this->base_url.'matches');
		} else {
			$filter_query_string = '';
			foreach ($filters as $filter_name => $filter_value) {
				$filter_query_string .= 'filter['.$filter_name.']='.$filter_value;
			}
			return $this->get_json($this->base_url.'matches?'.$filter_query_string);
		}
	}

	/**
	 * Get a collection of matches + their telemetry data
	 * @param array $filters - See http://battlerite-docs.readthedocs.io/en/latest/matches/matches.html#get-a-collection-of-matches for the possible filters
	 * @return array of objects
	 */
	public function get_full_matches($filters = false)
	{
		$matches = $this->get_matches($filters);
		foreach ($matches as &$match) {
			if (isset($match->relationships->assets->data[0])) {
				$match->telemetry = $this->get_telemetry($match->relationships->assets->data[0]->id);
			}
		}
		return $matches;
	}

	/**
	 * Get a single match by ID
	 * @param integer $match_id
	 * @return object
	 */
	public function get_match($match_id)
	{
		return $this->get_json($this->base_url.'matches/'.$match_id);
	}

	/**
	 * Get the telemetry data for a given id
	 * @param integer $telemetry_id
	 * @return object
	 */
	public function get_telemetry($telemetry_id)
	{
		return $this->get_json($this->telemetry_url.$telemetry_id.'-telemetry.json');
	}

	/*
     *  Player functions
     */

	/**
	 * Get a single player by ID
	 * @param integer $player_id
	 * @return object
	 */
	public function get_player($player_id)
	{
		return $this->get_json($this->base_url.'players/'.$player_id);
	}
}