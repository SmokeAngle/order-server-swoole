<?php
namespace OrderServer\Libs\Task;

class Task {
    
    const TASK_TYPE_ORDER = 'order_create';
    
    public static $taskMaps = array(
        self::TASK_TYPE_ORDER => '\\OrderServer\\Libs\\Task\\Gift\\OrderCreate'
    );

    public static function serialize( $type, $data, $gameSymbol ) {
        return serialize(new self::$taskMaps[$type]($data, $gameSymbol));
    }
    
    public static function unSerialize ( $data ) {
        return unserialize($data);
    }
    
    
    
    public static function transaction() {
        
    }
    
}
