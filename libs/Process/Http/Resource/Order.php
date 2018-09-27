<?php
namespace OrderServer\Libs\Process\Http\Resource;

use OrderServer\Libs\Process\Http\Resource\Base;
use OrderServer\Libs\Process\Http\Validation as ApiValidation;
use OrderServer\Libs\Process\Http\Message as ApiMessage;
use OrderServer\Libs\Process\Http\Response as ApiResponse;
use OrderServer\Libs\Utils\Helper;
use OrderServer\Libs\Task\Task;
use OrderServer\Libs\ProcessManager;

class Order extends Base {
    
    /**
     * @var int 礼包充值（代理自充）
     */
    const ORDER_TYPE_GIFT_ORDER_RECHARGE_SELF = 1;
    /**
     * @var int 礼包充值（代理为他人充值）
     */
    const ORDER_TYPE_GIFT_ORDER_RECHARGE_OTHER = 2;
    
    public static $allowOrderType = array(
       self::ORDER_TYPE_GIFT_ORDER_RECHARGE_SELF    => '代理自充',
       self::ORDER_TYPE_GIFT_ORDER_RECHARGE_OTHER   => '代理为他人充值',
    );

    public function __construct(\Swoole\Http\Server $server, \swoole_http_request $request, $gameSymbol, \Noodlehaus\Config $appConfig) {
        parent::__construct($server, $request, $gameSymbol, $appConfig);
    }

    public function createAction() {
        $data = $this->request->post;
        $fieldRule = array( 
            'type'      => array( 'name' => ApiValidation::RULE_NAME_REQUIRED, 'error' => ApiMessage::API_RESPONSE_CODE_ORDER_TYPE ),
            'agent_uid' => array( 'name' => ApiValidation::RULE_NAME_REQUIRED, 'error' => ApiMessage::API_RESPONSE_CODE_INVALID_AGENT_UID ),
            'refill_uid'=> array( 'name' => ApiValidation::RULE_NAME_REQUIRED, 'error' => ApiMessage::API_RESPONSE_CODE_INVALID_REFILL_UID ),
            'good_id'   => array( 'name' => ApiValidation::RULE_NAME_REQUIRED, 'error' => ApiMessage::API_RESPONSE_CODE_INVALID_PRODUCTID ),
            'timestamp' => array( 'name' => [ ApiValidation::RULE_NAME_NUMBER, ApiValidation::RULE_NAME_REQUIRED ], 'error' => ApiMessage::API_RESPONSE_CODE_INVALID_TIMESTAMP ),
            'sign'      => array( 'name' => ApiValidation::RULE_NAME_REQUIRED, 'error' => ApiMessage::API_RESPONSE_CODE_INVALID_SIGN ),
        );
        ProcessManager::$logger->log("[http][" . __CLASS__ . "] order create:" . var_export($data, TRUE) );
        $dataValidate = (new ApiValidation($data, $fieldRule));
        if( FALSE === $dataValidate->validation() ) {
            $error = $dataValidate->getError();
            return ApiResponse::toJson($error);
        } else {
            $type = $data['type'];
            if( !array_key_exists($type, self::$allowOrderType) ) {
                return ApiResponse::toArray(ApiMessage::API_RESPONSE_CODE_INVALID_REFILL_UID);   
            } else {
                $commonConfig = $this->appConfig->get('common');
                $sercertKey = $commonConfig['sercertKey'];
                $sign = $data['sign'];
                unset($data['sign']);
                if( FALSE === Helper::identifySign($data, $sign, $sercertKey) ) {
                    ProcessManager::$logger->log("[http][" . __CLASS__ . "] order create sign error, sign :" . $sign );
                    return ApiResponse::toJson(ApiMessage::API_RESPONSE_CODE_SIGN_ERROR);
                } else {
                    $prefix = sprintf('10%s', $this->gameId);
                    $tradeNo = Helper::genarateTradeNo($prefix);
                    $data['trade_no'] = $tradeNo;
                    $taskData = Task::serialize(Task::TASK_TYPE_ORDER, $data, $this->gameSymbol);
                    ProcessManager::$logger->log("[http][" . __CLASS__ . "][$tradeNo] order create add to defer task trade_no: $tradeNo" );
                    $this->server->task($taskData);
                    return ApiResponse::toJson(ApiMessage::API_RESPONSE_CODE_SUCCESS, ApiMessage::get(ApiMessage::API_RESPONSE_CODE_SUCCESS), array( 'trade_no' => $tradeNo ));     
                }       
            }
        }
    }
    
    public function infoAction() {
        
    }
    
}
