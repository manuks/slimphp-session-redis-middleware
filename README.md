slimphp-redis-session-middleware
================================

Middleware to use Redis as your PHP Session store in SlimPHP

use
================================

$app->add($session = new Slim_Middleware_SessionRedis());

$app->get('/', function()
use ($app, $session){
	if ( !$_SESSION ) {
		// session fixation is bad!
		$session->regenerate_id();
		$_SESSION['key'] = 'value';
	}
	print_r($_SESSION);
});
