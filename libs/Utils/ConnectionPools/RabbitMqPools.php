<?php
namespace OrderServer\Libs\Utils\ConnectionPools;

use OrderServer\Libs\Utils\ConnectionPools\BasePools;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class RabbitMqPools extends BasePools {
    
    
    public static $defaultConfig = array(
            'ip'        => '127.0.0.1',
            'port'      => 5672,
            'user'      => 'app',
            'password'  => '',
            'vhost'     => '/'
    );


    public function getConfig( $gameSymbol ) {
        $config = self::$defaultConfig;
        if(array_key_exists($gameSymbol, $this->configs) ) {
            $config = array_merge($config, $this->configs[$gameSymbol]);
        }
        return $config;
    }
    
    public function isConnectioned($connection) {
        $ret = TRUE;
        try {
            $testChannel = $connection->channel();
            $testChannel->close();
        } catch (\Exception $ex) {
            $ret = FALSE;
        }
        return $ret;
    }

    public function connection( $gameSymbol ) {
        $config     = $this->getConfig($gameSymbol);
        $serverIp   = $config['ip'];
        $serverPort = $config['port'];
        $userName   = $config['user'];
        $password   = $config['password'];
        $vhost      = $config['vhost'];
        
        $connection = new AMQPStreamConnection($serverIp, $serverPort, $userName, $password, $vhost);
        return $connection;
    }
      
    
}
