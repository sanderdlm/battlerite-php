# battlerite-php
PHP/Guzzle wrapper for the Battlerite API. Very WIP, I'll keep updating this repo as soon as the developers update the API and new endpoints become available.

## Requirements

PHP 5.6+
Guzzle 6.0+

See composer.json

## Installation

Download or clone, run composer install, done

All the functions are documented inside the class, so take a look at api.php if you're going to be using this wrapper.

## Example

```php
<?php
require_once './api.php';
$b = new \Battlerite\api('your-api-key-here');
$options = [
	"sort" => "createdAt",
	"page[limit]" => 1
];
$matches = $b->get_matches($options);
/* Optional: also grab telemetry data 
foreach ($matches as &$match) {
	$match->telemetry = $b->get_telemetry($match->telemetry_url);
}
*/
print_r($matches);
```

### To do

* Add new endpoints
* Add Guzzle Pools

#### Version
0.0.3

### Contact
[@dreadnip](https://twitter.com/dreadnip)
