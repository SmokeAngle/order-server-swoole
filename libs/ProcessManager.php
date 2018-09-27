<?php
namespace OrderServer\Libs;
use OrderServer\Libs\BaseServer;
use OrderServer\Libs\Process\Http\Process as HttpProcess;
use OrderServer\Libs\Process\Task\Process as TaskProcess;
use OrderServer\Libs\Utils\Logger;
use Swoole\Process;

class ProcessManager extends BaseServer {
   
    const SERVER_NAME = 'apiServer';
    const SERVER_DEFAULT_BIND_ADDRESS = '0.0.0.0';
    const SERVER_DEFAULT_BIND_PORT = 4000;
    const SERVER_DEFAULT_PID_FILE = 'logs/api-server.pid';
    const SERVER_DEFAULT_LOG_FILE = 'logs/api-server.log';
    
    

    /**
     * @var array 服务器默认配置
     */    
    public static $defaultConfig = array(
            'worker_num' => 2,  //base on you cpu nums 
            'task_worker_num' => 4, //better equal to worker_num, anyway you can define your own 
//            'heartbeat_check_interval' => 5, 
//            'heartbeat_idle_time' => 5, 
            'open_cpu_affinity' => 1, 
            'open_eof_check'  => 1, 
            'package_eof'   => "\r\n\r\n", 
            'package_max_length' => 1024 * 16, 
            'daemonize' => 1
    );

    public function initServer( $env, \Noodlehaus\Config $appConfig, array $serverConfig = array(), array $taskProcessConfig = array(), $serverPid = "", $serverLog = "" ) {
        try {
                $bindAddress = isset($serverConfig['host']) ? $serverConfig['host'] : self::SERVER_DEFAULT_BIND_ADDRESS;
                $bindPort = isset($serverConfig['port']) ? $serverConfig['port'] : self::SERVER_DEFAULT_BIND_PORT;
                unset($serverConfig['host'], $serverConfig['port']);
                $serverConfig['pid_file'] = !empty($serverPid) ? $serverPid : APP_ROOT . DIR_SP . self::SERVER_DEFAULT_PID_FILE ;
                $serverConfig['log_file'] = !empty($serverLog) ? $serverLog : APP_ROOT . DIR_SP . self::SERVER_DEFAULT_LOG_FILE ;
                $serverConfig = array_merge(self::$defaultConfig, $serverConfig);
                self::$env = $env;
                self::$serverConfig = $serverConfig;
                self::$appConfig = $appConfig;
                self::$taskProcessConfig = $taskProcessConfig;
                self::$dbPools = $this->getDbPools();
                self::$rabbitMqPools = $this->getRabbitMqPools();
                
                $commonConfig = self::$appConfig->get('common');
                //$logDir = APP_ROOT . DIR_SP . (isset($commonConfig['logdir']) ? $commonConfig['logdir'] : 'logs/');
                $logDir = isset($commonConfig['logdir']) ? $commonConfig['logdir'] : 'logs/';
                self::$logger = Logger::getLogger($logDir, 'application');
                self::$logger->log("[" . __CLASS__ . "] web process start");
                self::$logger->log("[" . __CLASS__ . "] web configs:" . json_encode($serverConfig));
                $this->server = new HttpProcess($bindAddress, $bindPort, $serverConfig);
                self::$logger->log("[" . __CLASS__ . "] web server bind address : http://${bindAddress}:${bindPort}");
                $games = explode(',', $commonConfig['games']);
                $perTaskWorkNum = isset(self::$taskProcessConfig['worker_num']) ? intval(self::$taskProcessConfig['worker_num']) : 2;
                foreach ( $games as $idx => $game  ) {
                    for( $i = 0; $i < $perTaskWorkNum; $i ++ ) {
                        $consumeProcess = new TaskProcess($game, $i);
                        $this->server->addProcess($consumeProcess);
                        self::$logger->log("[" . __CLASS__ . "][$idx] task process $game-process-$i add to manage process");   
                    }
                }
                self::$logger->log('start done');
        } catch (\Exception $ex) {
            self::$logger->log("[" . __CLASS__ . "] server start error:", Logger::LEVEL_ERROR);
            self::$logger->log($ex->getMessage(), Logger::LEVEL_ERROR);
            self::$logger->log($ex->getTraceAsString(), Logger::LEVEL_ERROR);
        }        
    }
    
}
