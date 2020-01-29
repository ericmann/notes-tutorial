<?php declare(strict_types=1);

include '../../vendor/autoload.php';

$request = Zend\Diactoros\ServerRequestFactory::fromGlobals(
	$_SERVER, $_GET, $_POST, $_COOKIE, $_FILES
);

$responseFactory = new Zend\Diactoros\ResponseFactory;

$strategy = new League\Route\Strategy\JsonStrategy($responseFactory);
$router   = new League\Route\Router;
$router->setStrategy($strategy);

$router->group('/notes', function(\League\Route\RouteGroup $router) {
	$router->get('/',        'Notes\Module3\Server::getNotes');
	$router->get('/{id}',    'Notes\Module3\Server::getNote');
	$router->post('/',       'Notes\Module3\Server::createNote');
	$router->delete('/{id}', 'Notes\Module3\Server::deleteNote');
})->middleware(new Notes\Module3\AuthMiddleware());

$router->post('/account', 'Notes\Module3\Server::register');
$router->put('/account',  'Notes\Module3\Server::changePassword');
$router->get('/login',   'Notes\Module3\Server::login');

$response = $router->dispatch($request);

// send the response to the browser
(new Zend\HttpHandlerRunner\Emitter\SapiEmitter)->emit($response);