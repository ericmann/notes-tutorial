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
	$router->get('/',        'Notes\ModuleFinal\Server::getNotes');
	$router->get('/{id}',    'Notes\ModuleFinal\Server::getNote');
	$router->post('/',       'Notes\ModuleFinal\Server::createNote');
	$router->delete('/{id}', 'Notes\ModuleFinal\Server::deleteNote');
})->middleware(new Notes\ModuleFinal\AuthMiddleware());

$router->post('/account', 'Notes\ModuleFinal\Server::register');
$router->put('/account',  'Notes\ModuleFinal\Server::changePassword');
$router->get('/login',   'Notes\ModuleFinal\Server::login');
$response = $router->dispatch($request);

// send the response to the browser
(new Laminas\HttpHandlerRunner\Emitter\SapiEmitter)->emit($response);