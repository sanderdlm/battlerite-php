<?php
/*
 *	Basic example
 */

require_once '../api.php';
$b = new \Battlerite\api('your-api-key-here');
$matches = $b->get_matches();
echo '<pre>';
print_r($matches[0]);