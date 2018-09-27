<?php
namespace OrderServer\Libs;

use OrderServer\Libs\BaseServer;
use Swoole\Process;

class TaskServer extends BaseServer {
    
    const SERVER_NAME = 'orderServer';
    const SERVER_DEFAULT_PID_FILE = 'logs/order-server.pid';
    const SERVER_DEFAULT_LOG_FILE = 'logs/order-server.log';

    /**
     * @var array 服务器默认配置
     */    
    public static $defaultConfig = array(
            'worker_num' => 4,  //base on you cpu nums 
            'open_cpu_affinity' => 1, 
            'open_eof_check'  => 1, 
            'daemonize' => 1
    );

    
    public static $logger = NULL;

    public function initServer( $env, \Noodlehaus\Config $appConfig, array $serverConfig = array(), $serverPid = "", $serverLog = "" ) {
        
        self::$env = $env;
        self::$serverConfig = $serverConfig;
        self::$appConfig = $appConfig;
        self::$dbPools = $this->getDbPools();
        self::$rabbitMqPools = $this->getRabbitMqPools();
        self::$logger = Logs::getLogger('./logs/');
        $this->server = new Process(function() {
            
            try {
                $pid = posix_getpid();
                $gameSymbol = "consumer";
                $connection = self::$rabbitMqPools->getConnection($pid, $gameSymbol);

            
                $exchange = 'order_exchange';
                $queue = 'order_queue';
                $consumerTag = 'consumer';

    //            $connection = new AMQPStreamConnection(HOST, PORT, USER, PASS, VHOST);
                $channel = $connection->channel();
                $channel->queue_declare($queue, false, true, false, false);
                $channel->exchange_declare($exchange, 'direct', false, true, false);
                $channel->queue_bind($queue, $exchange);
                self::$logger->log(11111111111111111);
                function process_message($message)
                {
                    echo "\n--------\n";
                    echo $message->body;
                    echo "\n--------\n";
                    $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
                    // Send a message with the string "quit" to cancel the consumer.
                    if ($message->body === 'quit')
                    {
                        $message->delivery_info['channel']->basic_cancel($message->delivery_info['consumer_tag']);
                    }
                }
                $channel->basic_consume($queue, $consumerTag, false, false, false, false, 'process_message');
                while (count($channel->callbacks))
                {
                    $channel->wait();
                } 
            } catch (\Exception $ex) {
                self::$logger->log("error====>" . $ex->getMessage());
            }

            
        });
        
        
        
        
        
    }
    

    
    
}
