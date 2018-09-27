<?php
namespace OrderServer\Libs\Process\Http;

class Message {
    
    const API_RESPONSE_CODE_SUCCESS = 0;
    const API_RESPONSE_CODE_FAIL = -1;
    const API_RESPONSE_CODE_ENDPOINT_NOT_EXISTS = 1000;
    const API_RESPONSE_CODE_SIGN_ERROR = 1001;
    
    const API_RESPONSE_CODE_INVALID_AGENT_UID = 1002;
    const API_RESPONSE_CODE_INVALID_PRODUCTID = 1003;
    const API_RESPONSE_CODE_INVALID_GAMEID = 1004;
    const API_RESPONSE_CODE_INVALID_TIMESTAMP = 1005;
    const API_RESPONSE_CODE_INVALID_SIGN = 1006;
    
    const API_RESPONSE_CODE_ORDER_TYPE  = 1007;
    const API_RESPONSE_CODE_INVALID_REFILL_UID  = 1008;




    const API_RESPONSE_CODE_METHOD_NOT_ALLOW = 20001;


    
    private static $apiMsg = array(
        self::API_RESPONSE_CODE_SUCCESS             => 'success',
        self::API_RESPONSE_CODE_FAIL                => 'fail',
        self::API_RESPONSE_CODE_ENDPOINT_NOT_EXISTS => 'api not exists',
        self::API_RESPONSE_CODE_SIGN_ERROR          => 'sign error',
        self::API_RESPONSE_CODE_INVALID_AGENT_UID   => 'invalid agent_uid',
        self::API_RESPONSE_CODE_INVALID_REFILL_UID  => 'invalid refill_uid',
        self::API_RESPONSE_CODE_INVALID_PRODUCTID   => 'invalid good_id',
        self::API_RESPONSE_CODE_INVALID_GAMEID      => 'invalid gameid',
        self::API_RESPONSE_CODE_INVALID_TIMESTAMP   => 'invalid timestamp',
        self::API_RESPONSE_CODE_INVALID_SIGN        => 'invalid sign',
        self::API_RESPONSE_CODE_ORDER_TYPE          => 'invalid order type',
        self::API_RESPONSE_CODE_METHOD_NOT_ALLOW    => 'method is not allow',
    );
    
    public static function get( $errorCode ) {
        $errorMsg = "";
        if( array_key_exists($errorCode, self::$apiMsg) ) {
            $errorMsg = self::$apiMsg[$errorCode];
        }
        return $errorMsg;
    }
}
