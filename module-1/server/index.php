<?php declare(strict_types=1);

include '../../vendor/autoload.php';

$request = Zend\Diactoros\ServerRequestFactory::fromGlobals(
	$_SERVER, $_GET, $_POST, $_COOKIE, $_FILES
);

$responseFactory = new Zend\Diactoros\ResponseFactory;

$strategy = new League\Route\Strategy\JsonStrategy($responseFactory);
$router   = (new League\Route\Router)->setStrategy($strategy);

$router->group('/notes', function(\League\Route\RouteGroup $router) {
	$router->get('/',        'Notes\Module1\Server::getNotes');
	$router->get('/{id}',    'Notes\Module1\Server::getNote');
	$router->post('/',       'Notes\Module1\Server::createNote');
	$router->delete('/{id}', 'Notes\Module1\Server::deleteNote');
});

$response = $router->dispatch($request);

// send the response to the browser
(new Zend\HttpHandlerRunner\Emitter\SapiEmitter)->emit($response);