<?php
/*
 *	Basic example
 */

//	Include the api file
require_once '../api.php';

//	Generate a new instance of the Api class with a valid API key
$b = new \Battlerite\api('your-api-key-here');

//	Set the options for which matches you want to pull
$options = [
	"sort" => "createdAt",
	"page[limit]" => 1
];

//	Make the request
$matches = $b->get_matches($options);

//	Optional: also grab telemetry data 
/*
foreach ($matches as &$match) {
	$match->telemetry = $b->get_telemetry($match->telemetry_url);
}
*/

//	Pretty print it in your browser or CLI
echo '<pre>';
print_r($matches);