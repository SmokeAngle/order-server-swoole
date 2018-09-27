<?php
namespace OrderServer\Libs\Process\Http;
use FastRoute\RouteCollector;

class Router {
    
    /**
     * @var \FastRoute\RouteCollector 
     */
    public $routeCollector = NULL;
    
    public function __construct() {
        
        $this->routeCollector = new RouteCollector(  new \FastRoute\RouteParser\Std(), new \FastRoute\DataGenerator\GroupCountBased() );
        $this->initRouter();
    }
    
    public function initRouter() {
        $this->routeCollector->addGroup('/order', function ( RouteCollector $subRouteCollector ) {
            $subRouteCollector->addRoute('POST', '/create/{game:[a-z]+}', 'OrderServer\Libs\Process\Http\Resource\Order::create');
            $subRouteCollector->addRoute('POST', '/info/{game:[a-z]+}', 'OrderServer\Libs\Process\Http\Resource\Order::info');
        });
    }   
    public function getCollector() {
        return $this->routeCollector;
    }
}

