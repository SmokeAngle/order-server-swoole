<?php
namespace OrderServer\Libs\Process\Http;

use OrderServer\Libs\Process\Http\Message as ApiMessage;

class Response {
    
    private $data = NULL;
    
    private $status = 200;
    
    public function setResponseStatus( $status  ) {
        $this->status = $status;
    }
    
    public function getResponseStatus() {
        return $this->status;
    }

    public function setResponseData( $data ) {
        $this->data = $data;
    }
    
    public function getResponseData() {
        return $this->data;
    }
    
    public static function toJson( $code, $msg = "", array $data = array() ) {
        $ret = self::toArray($code, $msg, $data);
        return json_encode($ret);
    }
    
    public static function toArray( $code, $msg = "", array $data = array() ) {
        $ret = array(
            'ret'   => intval($code),
            'msg'   => empty($msg) ? ApiMessage::get($code) : $msg
        );
        if( !empty($data)) {
            $ret['data'] = $data;
        }
        return $ret;
    }
    
}
