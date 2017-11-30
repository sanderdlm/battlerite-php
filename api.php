<?php

namespace Battlerite;

require __DIR__.'../vendor/autoload.php';
use GuzzleHttp\Client;

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
    		return json_decode($body);

    	} else if ($response->getStatusCode() == 401) {
    		return (object)["error" => "Unauthorized request."];
    	} else if ($response->getStatusCode() == 400) {
    		return (object)["error" => "Malformed request."];
    	} else {
    		return false;
    	}
    }

	/*
     *  Match functions
     */

	/**
	 * Get a collection of matches
	 * @param array $options - See http://battlerite-docs.readthedocs.io/en/latest/matches/matches.html#get-a-collection-of-matches for the possible filters
	 * @return array of objects
	 */
	public function get_matches($options = false)
	{
		$query_string = '';
		if ($options != false) {
			try {
				$query_string = $this->generate_query_string($options);
			} catch (\Exception $e) {
				return ["error" => $e->getMessage()];
			}
		}
		$matches_data = $this->get_json($this->base_url.'matches'.$query_string);

		if (isset($matches_data->error)) {
			return $matches_data;
		} else if ($matches_data != false) {
			return $this->format_matches($matches_data);
		} else {
			return ["error" => "No matches found."];
		}
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
	 * Take a list of included match assets and generate an ID indexed list from them
	 * @param array of objects  
	 * @return ID indexed array of objects
	 */
	private function index_assets($assets)
	{
		$indexed_list = [];
		foreach ($assets as $asset) {
			$indexed_list[$asset->id] = $asset;
		}
		return $indexed_list;
	}

	/**
	 * Format the data of multiple matches returned by the matches endpoint
	 * @param array of objects  
	 * @return array of objects
	 */
	private function format_matches($matches)
	{
		$new_match_list = [];
		$assets = $this->index_assets($matches->included);
		foreach ($matches->data as $match) {
			$new_match_list[] = $this->format_match($match, $assets);
		}
		return $new_match_list;
	}

	/**
	 * Format the data of a single match into a single readable object
	 * @param object $match - a single [data] match object from the API | array $assets - the [included] array from the API passed through the index_assets function
	 * @return object $match - readable, formatted match data for the passed match
	 */
	private function format_match($match, $assets)
	{
		//	Loop over all rosters and merge the 'attributes' & 'stats' arrays upwards into their parent arrays
		foreach ($assets as &$asset) {
			if (isset($asset->attributes)) {
				foreach ($asset->attributes as $attribute_name => $attribute_value) {
					if ($attribute_name == "stats" && $attribute_value !== null) {
						foreach ($attribute_value as $stats_name => $stats_value) {
							$asset->{$stats_name} = $stats_value;
						}
					} else {
						$asset->{$attribute_name} = $attribute_value;
					}
				}
				unset($asset->attributes);
			}
		}

		//	Prepare a new clean object to store the match data in
		$new_match = (object)[];
		//	Add the basic stuff
		$new_match->game_type = $match->type;
		$new_match->id = $match->id;
		$new_match->link = $match->links->self;

		//	Take the match attributes and place them in the new match object
		foreach ($match->attributes as $attribute_name => $attribute_value) {
			if ($attribute_name == "stats") {
				foreach ($attribute_value as $stats_name => $stats_value) {
					$new_match->{$stats_name} = $stats_value;
				}
			} else {
				$new_match->{$attribute_name} = $attribute_value;
			}
		}

		//	Take the rounds and place them in the new match object
		foreach ($match->relationships->rounds->data as $round) {
			$new_match->rounds[] = $assets[$round->id];
		}

		//	Take the rosters and place them in the new match object
		foreach ($match->relationships->rosters->data as $roster) {
			$roster_data = $assets[$roster->id];
			
			//	But first move the participants into the roster objects
			foreach ($roster_data->relationships->participants->data as $roster_participants) {
				$participant_data = $assets[$roster_participants->id];
				unset($participant_data->relationships);
				$roster_data->participants[] = $participant_data;
			}

			unset($roster_data->relationships);
			//	Then add it to the new match boject
			$new_match->rosters[] = $roster_data;
		}

		//	Add the telemetry URL for later use
		foreach ($match->relationships->assets->data as $asset) {
			$new_match->telemetry_url = $assets[$asset->id]->URL;
		}

		//	CLean up by removing all of the relationship clutter
		unset($match->relationships);

		return $new_match;
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