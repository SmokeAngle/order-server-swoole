<?php
namespace OrderServer\Libs\Process\Http;

use OrderServer\Libs\Process\Http\Router as ApiRouter;
use OrderServer\Libs\Process\Http\Message as ApiMessage;
use OrderServer\Libs\Process\Http\Response as ApiResponse;
use FastRoute\Dispatcher\GroupCountBased;

class Dispatcher {
    
    /**
     * @var \swoole_http_request 
     */
    public $request = NULL;
    /**
     * @var \OrderServer\Libs\ApiResponse
     */
    public $apiResponse = NULL;
    /**
     * @var \OrderServer\Libs\ApiRouter
     */
    public $apiRouter = NULL;
    /**
     * @var \FastRoute\Dispatcher\GroupCountBased 
     */
    public $apiDispatch = NULL;
    /**
     * @var \Swoole\Http\Server 
     */
    public $server = NULL;
    
    public $appConfig = NULL;

    public function __construct( \swoole_http_request $request, ApiResponse $response, ApiRouter $apiRouter, \Swoole\Http\Server $server, \Noodlehaus\Config $appConfig ) {
        $this->request = $request;
        $this->apiResponse = $response;
        $this->apiRouter = $apiRouter;
        $this->apiDispatch = new GroupCountBased($apiRouter->getCollector()->getData());
        $this->server = $server;
        $this->appConfig = $appConfig;
    }
    
    public function dispatch() {
        $requestMethod = $this->request->server['request_method'];
        $requestUri = $this->request->server['request_uri'];
        $routeInfo = $this->apiDispatch->dispatch($requestMethod, $requestUri);
        switch ($routeInfo[0]) {
            case \FastRoute\Dispatcher::NOT_FOUND:
                $this->dispatchNotFound();
                break;
            case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                $this->dispatchMethodNotAllow();
                break;
            case \FastRoute\Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars = $routeInfo[2];
                $this->dispatchRequest($handler, $vars);
                break;
        }
    }
    
    public function dispatchRequest($handler, $vars) {
        $game = isset($vars['game']) ? $vars['game'] : "";
        list($controllerName, $action) = explode("::", $handler);
        $actionName = sprintf("%sAction", $action);
        $responseData = (new $controllerName($this->server, $this->request, $game, $this->appConfig))->{$actionName}();
        if( is_array($responseData) ) {
            $responseData = json_encode($responseData);
        }
        $this->apiResponse->setResponseStatus(200);
        $this->apiResponse->setResponseData($responseData);
    }

    public function dispatchNotFound() {
        $responseData = ApiResponse::toJson(ApiMessage::API_RESPONSE_CODE_ENDPOINT_NOT_EXISTS);
        $this->apiResponse->setResponseStatus(404);
        $this->apiResponse->setResponseData($responseData);
    }
    
    public function dispatchMethodNotAllow() {
        $responseData = ApiResponse::toJson(ApiMessage::API_RESPONSE_CODE_METHOD_NOT_ALLOW);
        $this->apiResponse->setResponseStatus(405);
        $this->apiResponse->setResponseData($responseData);
    }
    
}
