<?php
namespace OrderServer\Libs;

interface ServerInterface {
    public static function start( $env, \Noodlehaus\Config $appConfig, array $serverConfig = array(), array $taskProcessConfig = array(), $serverPid = "", $serverLog = "" );
    public static function stop( $pidFile );
    public static function restart( $env, \Noodlehaus\Config $appConfig, array $serverConfig = array(), array $taskProcessConfig = array(), $pidFile = "", $logFile = "" );
    public static function getPid( $pidFile );
    public static function sendSignal( $serverPid, $signal );
}
