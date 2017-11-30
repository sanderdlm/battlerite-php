<?php
/*
 *	Basic example
 */

require_once './api.php';
$b = new \Battlerite\api('your-api-key-here');
$options = [
	"sort" => "createdAt",
	"page[limit]" => 1
];
$matches = $b->get_matches($options);
print_r($matches);