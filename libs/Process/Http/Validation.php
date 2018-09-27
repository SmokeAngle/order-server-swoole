<?php
namespace OrderServer\Libs\Process\Http;

use OrderServer\Libs\Utils\Helper;

class Validation {
    
    const RULE_NAME_REQUIRED = 'require'; 
    const RULE_NAME_NUMBER = 'number';

//    public static $rules = array(
//        self::RULE_NAME_REQUIRED => 'check',
//        self::RULE_NAME_NUMBER => '',
//    );
    
    public $data = array();
    public $rule = array();
    public $error = NULL;

    public function __construct( array $data = array(), array $rule = array() ) {
        $this->data = $data;
        $this->rule = $rule;
    }
    
    public function setError( $error ) {
        $this->error = $error;
    }
    
    public function getError() {
        return $this->error;
    }

    public function validation() {
        $ret = TRUE;
        foreach ( $this->rule as $name => $ruleData ) {
            if( isset($ruleData['name']) && isset($ruleData['error']) ) {
                $ruleNames = $ruleData['name'];
                $error = $ruleData['error'];    
                if( is_string($ruleNames) ) {
                    $ruleNames = [ $ruleNames ];
                }
                usort($ruleNames, function ( $a, $b ) {
                    return $a === self::RULE_NAME_REQUIRED ? -1 : 1;
                });
                
                foreach ( $ruleNames as $rule ) {
                    switch ( $rule ) {
                        case self::RULE_NAME_REQUIRED:
                            if( !array_key_exists($name, $this->data) || ( array_key_exists($name, $this->data) &&  empty($this->data[$name]) ) ) {
                                $ret = FALSE;
                                $this->setError($error);
                                break 2;
                            }
                            break;
                        case self::RULE_NAME_NUMBER:
                            if( array_key_exists($name, $this->data) ) {
                                if( !is_numeric($this->data[$name])) {
                                    $ret = FALSE;
                                    $this->setError($error);
                                    break 2;
                                }
                            }
                            break;
                    }
                }
            }
        }
        return $ret;
    }
    
}