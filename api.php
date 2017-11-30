<?php

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
    			return $content;
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
	public function get_matches($options = false)
	{
		$query_string = '';
		if ($options != false) {
			try {
				$query_string = $this->generate_query_string($options);
			} catch (\Exception $e) {
				return $e->getMessage();
			}
		}
		$matches_data = $this->get_json($this->base_url.'matches'.$query_string);

		if ($matches_data != false) {
			return $this->format_match_data($matches_data);
		} else {
			return 'No matches found.';
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
			//	Gather the round IDs
			$match_rounds = [];
			foreach ($match->relationships->rounds->data as $round) {
				$match_rounds[] = $round->id;
			}
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
	 * Format/link the data returned by the matches endpoint
	 * @param array of objects  
	 * @return array of objects
	 */
	private function format_match_data($matches)
	{
		//	Start by categorizing the different included structures by their type for easier handling later on
		$rounds = [];
		$rosters = [];
		$participants = [];
		$assets = [];
		foreach ($matches->included as $data_structure) {
			switch ($data_structure->type) {
				case 'round':
					$rounds[$data_structure->id] = $data_structure;
					break;
				case 'roster':
					$rosters[$data_structure->id] = $data_structure;
					break;
				case 'participant':
					$participants[$data_structure->id] = $data_structure;
					break;
				case 'asset':
					$assets[$data_structure->id] = $data_structure;
					break;
			}
		}

		//	Loop over all rosters and merge the participants into them
		foreach ($rosters as &$roster) {
			foreach ($roster->relationships->participants->data as $participant) {
				$roster->participants[] = $participants[$participant->id];
			}
			unset($roster->relationships);
		}

		//loop over all the matches and merge the rounds and rosters into them
		foreach ($matches->data as &$match) {

			foreach ($match->relationships->rounds->data as $round) {
				$match->rounds[] = $rounds[$round->id];
			}

			foreach ($match->relationships->rosters->data as $roster) {
				$match->rosters[] = $rosters[$roster->id];
			}

			foreach ($match->relationships->assets->data as $asset) {
				$match->telemetry = $this->get_telemetry($assets[$asset->id]->attributes->URL);
			}

			unset($match->relationships);
		}

		return $matches->data;
	}

	/**
	 * Generate a URL valid query string based on an array of query parameters
	 * @param key/value array of options
	 * @return string
	 */
	private function generate_query_string($options)
	{
		$possible_filters = ["page[offset]", "page[limit]", "sort", "filter[createdAt-start]", "filter[createdAt-end]", "filter[playerNames]", "filter[playerIds]", "filter[teamNames]", "filter[gameMode]"];
		$query_string = '?';
		if (isset($options) && is_array($options)) {
			foreach ($options as $option_name => $option_value) {
				if(!in_array($option_name, $possible_filters)){
					throw new \Exception("Invalid query parameter in options array.");
				}
				$query_string .= $option_name.'='.$option_value.'&';
			}
			return substr($query_string , 0, -1);
		} else {
			throw new \Exception("Query parameters have to passed as an array.");
		}
		
	}

	/**
	 * Get the telemetry data for a given url
	 * @param integer $telemetry_id
	 * @return object
	 */
	public function get_telemetry($telemetry_url)
	{
		return $this->get_json($telemetry_url);
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