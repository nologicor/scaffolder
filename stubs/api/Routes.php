/*
|--------------------------------------------------------------------------
| Scaffolder routes
|--------------------------------------------------------------------------
*/

$api = app('Dingo\Api\Routing\Router');

$api->version('v1', function($api)
{
{{routes}}
});