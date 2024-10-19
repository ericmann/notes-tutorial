<?php declare(strict_types=1);

include '../../vendor/autoload.php';

$request = Laminas\Diactoros\ServerRequestFactory::fromGlobals(
	$_SERVER, $_GET, $_POST, $_COOKIE, $_FILES
);

$responseFactory = new Laminas\Diactoros\ResponseFactory;

$strategy = new League\Route\Strategy\JsonStrategy($responseFactory);
$router   = new League\Route\Router;
$router->setStrategy($strategy);

$router->group('/notes', function(\League\Route\RouteGroup $router) {
	$router->get('/',        'Notes\Module4\Server::getNotes');
	$router->get('/{id}',    'Notes\Module4\Server::getNote');
	$router->post('/',       'Notes\Module4\Server::createNote');
	$router->delete('/{id}', 'Notes\Module4\Server::deleteNote');
})->middleware(new Notes\Module4\AuthMiddleware());

$router->post('/account', 'Notes\Module4\Server::register');
$router->put('/account',  'Notes\Module4\Server::changePassword');
$router->get('/login',   'Notes\Module4\Server::login');
$response = $router->dispatch($request);

// send the response to the browser
(new Laminas\HttpHandlerRunner\Emitter\SapiEmitter)->emit($response);