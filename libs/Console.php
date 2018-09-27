<?php
namespace OrderServer\Libs;

use League\CLImate\CLImate;
use Noodlehaus\Config;
use OrderServer\Libs\ProcessManager;
use OrderServer\Libs\TaskServer;

class Console {
    
    const CONSOLE_CMD_START = 'start';
    const CONSOLE_CMD_RESTART = 'restart';
    const CONSOLE_CMD_STOP = 'stop';
    
    const ENV_DEV = 'dev';
    const ENV_STG = 'stg';
    const ENV_PROD = 'prod';

    /**
     * @var array   支持得command
     */
    public static $allowCommand = array(
        self::CONSOLE_CMD_START     => '启动服务',
        self::CONSOLE_CMD_RESTART   => '重启服务',
        self::CONSOLE_CMD_STOP      => '停止服务',
    );
    
    /**
     * @var array 支持运行环境
     */
    public static $allowEnvs = array(
        self::ENV_DEV   => '开发环境',
        self::ENV_STG   => 'STG环境',
        self::ENV_PROD  => '生产环境'
    );


    /**
     * @var League\CLImate\CLImate
     */
    public $climate = NULL;
    /**
     * @var Noodlehaus\Config
     */
    public $serverConfig = NULL;
    /**
     * @var string
     */
    public $appConfig = NULL;

    public function __construct( $serverConfigFile, $appConfigFile ) {
        $this->climate = new CLImate(); 
        $this->serverConfig = new Config($serverConfigFile);
        $this->appConfig = new Config($appConfigFile);
    }

    public function run() {
        $this->runOpt();
    }
    
    public function setRunOpt() {
        $this->climate->arguments->add([
            'command' => [
                'description' =>  'start: 启动服务 '
                                . 'restart: 重启服务 '
                                . 'stop: 停止服务 '
            ],
//            'service_name' => [
//                'description' => '启动服务名, 支持服务为：apiServer, taskServer',
//            ],
            'pid_file' => [
                'longPrefix'  => 'pid_file',
                'description' => '设置启动pid',
                'defaultValue'=> NULL,
            ],
            'log_file' => [
                'longPrefix'  => 'log_file',
                'description' => '设置log',
                'defaultValue'=> NULL,
            ],
            'env' => [
                'longPrefix'  => 'env',
                'description' => '运行环境：[dev, stg, prod]',
                'defaultValue'=> NULL,
            ],
            'help' => [
                'longPrefix'  => 'help',
                'description' => 'Prints a usage statement',
                'noValue'     => true,
            ],
        ]);
        $this->climate->arguments->parse();
    }

    public function runOpt() {
        $this->setRunOpt();
        $isHelp = $this->climate->arguments->get('help');
        $command = $this->climate->arguments->get('command');
        $env = $this->climate->arguments->get('env');
        if( $isHelp ) {
            $this->climate->usage();
            exit(1);
        } elseif( !array_key_exists($command, self::$allowCommand) ||    ( !empty ($env) && !array_key_exists($env, self::$allowEnvs) ) ) {
            $this->climate->error("command 或者 service_name 不合法");
            $this->climate->usage();
            exit(1);
        } else {
            $serverOpts = array();
            foreach ( [ 'pid_file', 'log_file' ] as $serverOptName ) {
                $serverOptVal = $this->climate->arguments->get($serverOptName);
                if( !empty($serverOptVal) ) {
                    $serverOpts[$serverOptName] = $serverOptVal;
                }
            }
            $env = empty($env) ? self::ENV_DEV : $env;
            switch ( $command ) {
                case self::CONSOLE_CMD_START:
                    $this->start($env, $serverOpts);
                    break;
                case self::CONSOLE_CMD_RESTART:
                    $this->restart($env, $serverOpts);
                    break;
                case  self::CONSOLE_CMD_STOP:
                    $this->stop($serverOpts);
                    break;
            }
            
        }
    }
    
    public function start( $env = "", array $options = array()) {
        $iniConfig = $this->serverConfig->get('server');
        $taskProcessConfig = $this->serverConfig->get('task');
        $serverConfig = array_merge($iniConfig, $options);
        $pidFile = isset($serverConfig['pid_file']) ? $serverConfig['pid_file'] : NULL;
        $logFile = isset($serverConfig['log_file']) ? $serverConfig['log_file'] : NULL;
        unset($serverConfig['pid_file'], $serverConfig['log_file']);
        $appConfig = $this->appConfig;
        
        ProcessManager::start($env, $appConfig, $serverConfig, $taskProcessConfig, $pidFile, $logFile);
    }
    
    public function restart( $env = "", array $options = array() ) {
        $iniConfig = $this->serverConfig->get('server');
        $taskProcessConfig = $this->serverConfig->get('task');
        $serverConfig = array_merge($iniConfig, $options);
        $pidFile = isset($serverConfig['pid_file']) ? $serverConfig['pid_file'] : NULL;
        $logFile = isset($serverConfig['log_file']) ? $serverConfig['log_file'] : NULL;
        unset($serverConfig['pid_file'], $serverConfig['log_file']);
        $appConfig = $this->appConfig;
        ProcessManager::restart($env, $appConfig, $serverConfig, $taskProcessConfig, $pidFile, $logFile);     
    }
    
    public function stop( array $options = array() ) {
        $iniConfig = $this->serverConfig->get('server');
        $serverConfig = array_merge($iniConfig, $options);
        $pidFile = isset($serverConfig['pid_file']) ? $serverConfig['pid_file'] : NULL;
        ProcessManager::stop($pidFile);               
    }
    
    
}
