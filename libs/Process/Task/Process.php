<?php
namespace OrderServer\Libs\Process\Task;

use Swoole\Process as SwooleProcess;
use OrderServer\Libs\ProcessManager;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use OrderServer\Libs\Utils\ConnectionManagerPDO;


class Process extends SwooleProcess {
    
    /**
     * @var string 
     */
    public $game = NULL;
    /**
     * @var \OrderServer\Libs\Utils\ConnectionManagerPDO 
     */
    public $mysqlConn;
    
    /**
     * @var \PhpAmqpLib\Connection\AMQPStreamConnection
     */
    public $mqConn;


    public function __construct( $game, $idx, $redirectStdinStdout = false, $createPipe = true ) {
        $pid = posix_getpid();
        $this->game = $game;
        $this->mysqlConn = ProcessManager::$dbPools->getConnection($pid . '-' . $idx, $game);
        $this->mqConn = ProcessManager::$rabbitMqPools->getConnection($pid . '-' . $idx, $game);
        parent::__construct(function() {
            $this->processCall();
        }, $redirectStdinStdout, $createPipe);
    }
    
    public function processCall() {
        try {
            $commonConfig       = ProcessManager::$appConfig->get('common');
            $rabbitMqExchange   = $commonConfig[sprintf('%s.mq.order_exchange', $this->game)];
            $rabbitMqQueue      = $commonConfig[sprintf('%s.mq.order_queue', $this->game)];
            $rabbitMqRouterKey  = $commonConfig[sprintf('%s.mq.order_route_key', $this->game)];
            $taskWorkflowClass  = trim($commonConfig['task_workflow_gift']);
            
            $this->mqConn->channel(1);
            $channel = $this->mqConn->channel();
            $channel->exchange_declare($rabbitMqExchange, 'direct', false, true, false);
            $channel->queue_declare($rabbitMqQueue, false, true, false, false);
            $channel->queue_bind($rabbitMqQueue, $rabbitMqExchange, $rabbitMqRouterKey);
            $consumerTag = 'task_' . posix_getpid();
            ProcessManager::$logger->log("[task][" . __CLASS__ . "] task process basic_consume start");
            $channel->basic_consume($rabbitMqQueue, $consumerTag, false, false, false, false, function ($message) use ( $taskWorkflowClass ) {
                    $data = json_decode($message->body, TRUE);
                    ProcessManager::$logger->log("[task][" . __CLASS__ . "] task process basic_consume data: $data");
                    $gameSymbl = $data['game'];
                    unset($data['game']);
                    $ret = (new $taskWorkflowClass($gameSymbl, $data))->run();
                    if( TRUE === $ret ) {
                        $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);    
                    }
                    if ($message->body === 'quit') {
                        $message->delivery_info['channel']->basic_cancel($message->delivery_info['consumer_tag']);
                    }     
                    
                    
//                    \Swoole\Coroutine::create(function () use ( $gameSymbl,  $data, $taskWorkflowClass, $message ) {
//                        (new $taskWorkflowClass($gameSymbl, $data))->run(function( $ret) use( $message ) {
//                            if( TRUE === $ret ) {
//                                $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);    
//                            }
//                            if ($message->body === 'quit') {
//                                $message->delivery_info['channel']->basic_cancel($message->delivery_info['consumer_tag']);
//                            }                               
//                        });
//                    });                 
                    
            });
            while (count($channel->callbacks))
            {
                $channel->wait();
                $read = array($this->mqConn->getSocket()); // add here other sockets that you need to attend
                $write = null;
                $except = null;
                if (false === ($changeStreamsCount = stream_select($read, $write, $except, 60))) {
                    /* Error handling */
                } elseif ($changeStreamsCount > 0 || $channel->hasPendingMethods()) {
                    $channel->wait();
                }
            } 
        } catch (Exception $ex) {
            ProcessManager::$logger->log("[task][" . __CLASS__ . "] task process exception:");
            ProcessManager::$logger->log("[task][" . __CLASS__ . "]:" . $ex->getMessage());
            ProcessManager::$logger->log("[task][" . __CLASS__ . "]:" . $ex->getTraceAsString());
        }
  
            
    }
}
