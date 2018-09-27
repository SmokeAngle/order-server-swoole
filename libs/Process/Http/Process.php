<?php
namespace OrderServer\Libs\Process\Http;

use OrderServer\Libs\Task\Task;
use OrderServer\Libs\ProcessManager;
use OrderServer\Libs\Process\Http\Dispatcher;
use OrderServer\Libs\Process\Http\Router as ApiRouter;
use OrderServer\Libs\Process\Http\Response as ApiResponse;
use Swoole\Process as SwooleProcess;


class Process extends \Swoole\Http\Server {
    
    public function __construct( $host, $port, $serverConfig, $mode = SWOOLE_PROCESS, $sock_type = SWOOLE_SOCK_TCP) {      
        parent::__construct($host, $port, $mode, $sock_type);
        $this->set($serverConfig);
        
        $this->on('request', function ( \swoole_http_request $request, \swoole_http_response $response ) {
            self::onRequest($request, $response, $this);
        });
        $this->on('task', function ( $httpServer, $taskId, $fromId, $taskData ) {
            self::onTask($httpServer, $taskId, $fromId, $taskData);
        });
        $this->on('finish', function ( $data  ) {
            self::onFinish($data);
        }); 

        $this->on('WorkerStart', function ( $serv, $workerId ) {
            self::onWorkerStart($serv, $workerId);
        });
        $this->on('WorkerStop', function ( $serv, $workerId ) {
            self::onWorkerStop($serv, $workerId);
        });
        $this->on('WorkerError', function ( $serv, $workerId, $workerPid, $exitCode) {
            self::onWorkerError( $serv, $workerId, $workerPid, $exitCode );
        });
        
 
    }
    
    protected static function onWorkerStart($serv, $workerId) {        
        ProcessManager::$logger->log("[onWorkerStart][" . __CLASS__ . "] workerId = $workerId");
    }
    
    protected static function onWorkerStop($serv, $workerId) {
        ProcessManager::$logger->log("[onWorkerStop][" . __CLASS__ . "] workerId = $workerId");
    }
    
    protected static function onWorkerError($serv, $workerId, $workerPid, $exitCode) {
        ProcessManager::$logger->log("[onWorkerError][" . __CLASS__ . "] workerPid = $workerPid workerId = $workerId exitCode = $exitCode");
    }

    protected static function onRequest( \swoole_http_request $request, \swoole_http_response $response, \Swoole\Http\Server $server ) {
        if ('/favicon.ico' == $request->server['path_info'] || '/favicon.ico' == $request->server['request_uri']) {
                return $response->end();
        }
        $apiRouter = new ApiRouter();
        $apiResponse = new ApiResponse();
        $dispatcher = new Dispatcher($request, $apiResponse, $apiRouter, $server, ProcessManager::$appConfig);
        $dispatcher->dispatch();
        $data = $dispatcher->apiResponse->getResponseData();
        $responseStatus = $dispatcher->apiResponse->getResponseStatus();
        $response->header('Content-Type', 'application/json');
        $response->status($responseStatus);
        return $response->end($data);
    }
    
    protected static function onTask(  \Swoole\Http\Server $httpServer, $taskId, $fromId, $taskData) {        
            $task = Task::unSerialize($taskData);
            $task->initConnection();
            $commonConfig = ProcessManager::$appConfig->get('common');
            $task->setConfig($commonConfig);
            $task->run();            
    }
    
    protected static function onFinish( array $data = array() ) {
        
    }
    
    public function start() {
        parent::start();
        SwooleProcess::signal(SIGCHLD, function ( $sig ) {
            while ( $ret = Process::wait(FALSE) ) {
                self::$logger->log("[" . __CLASS__ . "] wait sub process stop");
            }
        });
    }
    
}
