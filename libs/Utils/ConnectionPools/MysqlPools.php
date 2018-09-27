<?php
namespace OrderServer\Libs\Utils\ConnectionPools;

use OrderServer\Libs\Utils\ConnectionManagerPDO;
use OrderServer\Libs\Utils\ConnectionPools\BasePools;

class MysqlPools extends BasePools {
    
    public static $defaultConfig = array(
            'host'      => 'localhost',
            'port'      => 3306,
            'user'      => 'root',
            'password'  => '',
            'database'  => '',
            'charset'   => 'utf8',
    );
    
    public static $defaultOptions = array(
        \PDO::ATTR_PERSISTENT               => FALSE,
        \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => TRUE,
        \PDO::MYSQL_ATTR_INIT_COMMAND       => 'SET NAMES utf8;',
        \PDO::ATTR_ERRMODE                  => \PDO::ERRMODE_EXCEPTION,  
//        \PDO::ATTR_TIMEOUT                  => 1
    );

    public function getConfig($gameSymbol) {
        $config = self::$defaultConfig;
        if(array_key_exists($gameSymbol, $this->configs) ) {
            $config = array_merge($config, $this->configs[$gameSymbol]);
        }
        return $config;
    }

    public function connection($gameSymbol ) {
        $dbConfig = $this->getConfig($gameSymbol);
        $userName = $dbConfig['user'];
        $password = $dbConfig['password'];
        unset($dbConfig['user'], $dbConfig['password']);
        $dsn = $this->buildDsn($dbConfig);
        ini_set("default_socket_timeout", 1);
        $dbConnection = new \PDO($dsn, $userName, $password, self::$defaultOptions);
        return $dbConnection;
    }
    
    public function isConnectioned($connection) {
        $ret = TRUE;
        try {
            $connection->getAttribute(\PDO::ATTR_SERVER_INFO);
        } catch (\PDOException $e) {
            $ret = FALSE;
        }
        return $ret;
    }

    private function buildDsn( array $dbConfig = array() ) {
        return sprintf('mysql:host=%s;port=%d;dbname=%s', $dbConfig['host'], $dbConfig['port'], $dbConfig['database']);
    }
    
}