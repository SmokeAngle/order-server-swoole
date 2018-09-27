<?php
namespace OrderServer\Libs;
use OrderServer\Libs\AbstractServer;
use OrderServer\Libs\Console;
use OrderServer\Libs\Utils\ConnectionPools\MysqlPools;
use OrderServer\Libs\Utils\ConnectionPools\RabbitMqPools;

class BaseServer extends AbstractServer {
    
    const SERVER_NAME = '';
    const SERVER_DEFAULT_PID_FILE = '';
    const SERVER_DEFAULT_LOG_FILE = '';

    /**
     * @var array 服务器默认配置
     */    
    public static $defaultConfig = array();
    /**
     *
     * @var OrderServer\Libs\ApiServer;
     */
    protected static $instance = NULL;

    protected $server = NULL;
    /**
     * @var array
     */
    public static $serverConfig = NULL;
    /**
     * @var array
     */
    public static $taskProcessConfig = NULL;

    /**
     * @static \Noodlehaus\Config
     */
    public static $appConfig = NULL;
    /**
     * @var string
     */
    public static $env = Console::ENV_DEV;
    /**
     *
     * @var \OrderServer\Libs\Utils\ConnectionPools\MysqlPools
     */
    public static $dbPools = NULL;
    /**
     * @var \OrderServer\Libs\Utils\ConnectionPools\RabbitMqPools 
     */
    public static $rabbitMqPools = NULL;
    /**
     * @var \OrderServer\Libs\Utils\Logger
     */
    public static $logger = NULL;


    private function __construct( $env, \Noodlehaus\Config $appConfig, array $serverConfig = array(), array $taskProcessConfig = array(), $serverPid = "", $serverLog = "" ) {
        $this->initServer($env, $appConfig, $serverConfig, $taskProcessConfig, $serverPid, $serverLog);
    }
    
    public function initServer( $env, \Noodlehaus\Config $appConfig, array $serverConfig = array(),  array $taskProcessConfig = array(), $serverPid = "", $serverLog = "" ) {
        
    }

    public static function getPid( $pidFile = "" ) {
        $pid = NULL;
        if( file_exists($pidFile) ) {
            $pid = file_get_contents($pidFile);
        }
        return $pid;
    }
    public static function sendSignal( $serverPid, $signal = SIGTERM ) {
        $ret = FALSE;
        //尝试5次发送信号
        $i=0;
        do {
            ++ $i;
            if (!\Swoole\Process::kill($serverPid, 0)) {
                 $ret = TRUE;
                 break;
            }
            \Swoole\Process::kill($serverPid, $signal);
            sleep(1);
        } while ($i <= 5);
        return $ret;
    }
    
    protected function getDbPools() {
        $commonConfigs = self::$appConfig->get('common');
        $envConfig = self::$appConfig->get(self::$env);
        $games = isset($commonConfigs['games']) ? $commonConfigs['games'] : '';
        $gamesArr = explode(',', $games);
        
        $dbConfigs = array();
        
        foreach ( $gamesArr as $game ) {
            $hostKey = sprintf("%s.mysql_server.host", $game);
            $portKey = sprintf("%s.mysql_server.port", $game);
            $userKey = sprintf("%s.mysql_server.user", $game);
            $passwordKey = sprintf("%s.mysql_server.password", $game);
            $databaseKey = sprintf("%s.mysql_server.database", $game);
            $charsetKey = sprintf("%s.mysql_server.charset", $game);
            $dbConfigs[$game] = array(
                'host'      => $envConfig[$hostKey],
                'port'      => $envConfig[$portKey],
                'user'      => $envConfig[$userKey],
                'password'  => $envConfig[$passwordKey],
                'database'  => $envConfig[$databaseKey],
                'charset'   => $envConfig[$charsetKey],
            );
        }
        
        return new MysqlPools($gamesArr, $dbConfigs);
    }
    
    
    protected function getRabbitMqPools() {
        $envConfig = self::$appConfig->get(self::$env);
        $commonConfigs = self::$appConfig->get('common');
        
        $serverIp = $envConfig['rabbitmq_server.ip'];
        $serverPort = $envConfig['rabbitmq_server.port'];
        $userName = $envConfig['rabbitmq_server.user'];
        $password = $envConfig['rabbitmq_server.password'];
        
        $mqConfigs = array();
        $games = isset($commonConfigs['games']) ? $commonConfigs['games'] : '';
        $gamesArr = explode(',', $games);
        foreach ( $gamesArr as $game ) { 
            $vHost = $commonConfigs[sprintf('%s.mq.vhost', $game)];
            $mqConfigs[$game] = array(
                'ip'        => $serverIp,
                'port'      => $serverPort,
                'user'      => $userName,
                'password'  => $password,
                'vhost'     => $vHost
            );
        }
        return new RabbitMqPools($gamesArr, $mqConfigs);
    }
    
    /**
     *  实例化apiServer对象
     * 
     * @return \OrderServer\Libs\OrderServer
     */
    public static function getInstance( $env, $serverName, \Noodlehaus\Config $appConfig , array $serverConfig = array(), array $taskProcessConfig = array(),  $serverPid = "", $serverLog = "" ) {
        if( !(self::$instance instanceof self) ) {
            self::$instance = new $serverName( $env, $appConfig, $serverConfig, $taskProcessConfig, $serverPid, $serverLog );
        }
        return self::$instance;
    }
    
    private function __clone() {
        
    }
    
    public static function start($env, \Noodlehaus\Config $appConfig, array $serverConfig = array(), array $taskProcessConfig = array(),  $serverPid = "", $serverLog = "") {
        echo self::SERVER_NAME . ' start success' . PHP_EOL;
        $serverName = get_called_class();
        self::getInstance( $env, $serverName, $appConfig, $serverConfig, $taskProcessConfig,  $serverPid, $serverLog )->server->start();
    }
    
    public static function stop($pidFile) {
        $serverPid = self::getPid($pidFile);
        if( empty($serverPid) ) {
            echo self::SERVER_NAME . ' pid is null' . PHP_EOL;
        } else {
            if( self::sendSignal($serverPid, SIGTERM) ) {
                echo self::SERVER_NAME . ' status is stopped' . PHP_EOL;
            } else {
                echo self::SERVER_NAME . ' stop fail' . PHP_EOL;
            }
        }
    }
    
    public static function restart( $env, \Noodlehaus\Config $appConfig, array $serverConfig = array(),  array $taskProcessConfig = array(), $pidFile = "", $logFile = "" ){
        self::stop($pidFile);
        sleep(3);
        self::start($env, $appConfig, $serverConfig, $taskProcessConfig, $pidFile, $logFile);
    }
    
}