<?php
/*
 *	Basic example
 */

require_once '../api.php';
$b = new \Battlerite\api('your-api-key-goes-here');
$matches = $b->get_matches();
print_r($matches);