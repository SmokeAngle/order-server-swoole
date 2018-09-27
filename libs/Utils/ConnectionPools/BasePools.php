<?php
namespace OrderServer\Libs\Utils\ConnectionPools;

use OrderServer\Libs\Utils\ConnectionPools\ConnectionPoolsInterface;
use OrderServer\Libs\ProcessManager;

abstract class BasePools implements ConnectionPoolsInterface {
    /**
     * @var array
     */
    public $names = [];
    /**
     * @var array
     */
    public $configs = [];
    /**
     * @var array
     */
    public $pools = [];
    /**
     *
     * @static
     */
    public static $defaultConfig = array();
    /**
     * @static
     */
    public static $defaultOptions = array();

    public function __construct( array $names = array(), array $configs = array() ) {
        $this->names = $names;
        $this->configs = $configs;
    }    
    
    abstract public function connection($gameSymbol);
    abstract public function getConfig($gameSymbol);
    
    /**
     * 
     * @param int $pid         
     * @param string $gameSymbol
     * @return mixed
     */
    public function getConnection( $pid, $gameSymbol ) {
        $connName = sprintf("%s_%s", $pid, $gameSymbol);
        ProcessManager::$logger->log(get_called_class() . 'connection  ' . $connName);
        if( array_key_exists($connName, $this->pools) ) {
            $connection = $this->pools[$connName];
        } else {
            $connection = $this->connection($gameSymbol);
            $this->pools[$connName] = $connection;
        }
        $maxRetry = 10;
        $rety = 1;
        $isConnectioned = $this->isConnectioned($connection);
        ProcessManager::$logger->log(get_called_class() . ' isConnectioned :' . var_export($isConnectioned, TRUE));
        while ( !$isConnectioned ) {
            ProcessManager::$logger->log(get_called_class() . 'connection retry ' . $rety);
            if( $rety >= $maxRetry ) {
                break;
            }
            $connection = $this->connection($gameSymbol);
            if( $this->isConnectioned($connection) ) {
                $this->pools[$connName] = $connection;
                $isConnectioned = TRUE;
                ProcessManager::$logger->log(get_called_class() . 'connection retry success');
                break;
            }
            $rety ++;
        }
        if( !$isConnectioned ) {
            ProcessManager::$logger->log(get_called_class() . 'connection retry fail');
        }
        return $connection;
    }
    
    
    abstract function isConnectioned( $connection );
    
    
}
