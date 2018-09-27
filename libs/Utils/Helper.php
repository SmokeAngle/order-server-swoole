<?php
namespace OrderServer\Libs\Utils;

class Helper {
    
    /**
     * 生成签名
     * 
     * @param array $data   签名数据
     * @param string $key   签名key
     * @return string
     */
    public static function makeSign( $data = array(), $key = "" ) {
        ksort($data);
        $dataStr = http_build_query($data);
        $signStr = $dataStr . "&key=" . $key;
        
        return md5($signStr);
    }
    
    /**
     * 校验签名
     * 
     * @param array $data       校验数据
     * @param string $signStr   校验签名
     * @param string $key       签名key
     * @return boolean
     */
    public static function identifySign( $data = array(), $signStr = "", $key = "" ) {
        if( self::makeSign($data, $key) === $signStr ) {
            return TRUE;
        }
        return FALSE;
    }
    /**
     * 生成订单流水号
     * 
     * @param string $prefix 流水号前缀
     * @return string
     */
    public static function genarateTradeNo( $prefix = "" ) {
        $mtime=explode(' ',microtime());
        $time=($mtime[1]*1000+(int)($mtime[0]*1000));
        $tradeno=$time.rand(11, 99);
        $tradeno = $prefix . $tradeno;
        return $tradeno;
    }
    
    public static function mkdir($path, $mode=0777, $recursive=true) {
        if (!is_dir($path)) {
            mkdir($path, $mode, $recursive);
        }
    }   
}
