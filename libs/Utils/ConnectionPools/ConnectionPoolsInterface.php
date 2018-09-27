<?php
namespace OrderServer\Libs\Utils\ConnectionPools;

interface ConnectionPoolsInterface {
    public function getConnection( $pid, $gameSymbol );
    public function connection( $gameSymbol);
    public function getConfig( $gameSymbol );
}
