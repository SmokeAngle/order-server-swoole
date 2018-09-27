<?php
namespace OrderServer\Libs\Task;

use OrderServer\Libs\Task\TaskInterface;
use OrderServer\Libs\Utils\ConnectionManagerPDO;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use OrderServer\Libs\ProcessManager;

abstract class Base implements TaskInterface  {
    public $data = array();
    /**
     * @var \PDO $db 
     */
    public $db = NULL;
    /**
     * @var \PhpAmqpLib\Connection\AMQPStreamConnection
     */
    public $rabbitMq = NULL;
    public $gameSymbol = NULL;
    public $gameId = NULL;
    public $config = NULL;
    const TASK_NAME = NULL;
    public function __construct( $data = array(), $gameSymbol = "" ) {
        $this->data = $data;
        $this->gameSymbol = $gameSymbol;            
        $commonConfig = ProcessManager::$appConfig->get('common');
        $this->gameId = $commonConfig[sprintf('%s.game_id', $this->gameSymbol)];
    }
    
    public function initConnection() {
        $pid = posix_getpid();
        $this->db = ProcessManager::$dbPools->getConnection($pid, $this->gameSymbol);
        $this->rabbitMq = ProcessManager::$rabbitMqPools->getConnection($pid, $this->gameSymbol);
    }

    public function getGameSymbol() {
        return $this->gameSymbol;
    }
    
    abstract public function setConfig(array $config = array());
}
