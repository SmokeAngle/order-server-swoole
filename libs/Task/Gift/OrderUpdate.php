<?php
namespace OrderServer\Libs\Task\Gift;

use OrderServer\Libs\ProcessManager;
use OrderServer\Libs\Task\Base;

/**
 * 订单扣款完成， 更新订单数据
 *
 */
class OrderUpdate extends Base {
    
    const TASK_NAME = 'order_update';
    
    public function setConfig(array $config = array()) {
        $this->config = $config;
    }
    
    public function run() {
        ProcessManager::$logger->log("[task][" . __CLASS__ . "] start:");
        ProcessManager::$logger->log("[task][" . __CLASS__ . "] data:"  . json_encode($this->data, TRUE) );
        $ret = FALSE;
        $tradeNo = $this->data['tradeNo'];
        ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] start:");            
        try {
            $tradeOrderTbName = $this->config[sprintf('%s.table.trade_order', $this->gameSymbol)];
            $orderSth = $this->db->prepare('SELECT tradeno, coin, uid, order_status FROM ' . $tradeOrderTbName . ' WHERE tradeno = :trade_no;' );
            $orderSth->execute(array( 'trade_no' => $tradeNo ));
            $orderData = $orderSth->fetch(\PDO::FETCH_ASSOC);
            $orderSth->closeCursor();
            if( !empty($orderData) && intval($orderData['order_status']) === 0 ) {
                $orderUpdateSth = $this->db->prepare('UPDATE ' . $tradeOrderTbName . ' SET order_status = 1, cash_coin = :cash_coin, time_end = :time_end, transaction_id = :transaction_id WHERE tradeno = :tradeno;');
                $orderUpdateRet = $orderUpdateSth->execute(array(
                    'cash_coin'     => $orderData['coin'],
                    'time_end'      => date('YmdHis', time()),
                    'transaction_id'=> '',
                    'tradeno'       => $tradeNo
                ));
                $orderUpdateSth->closeCursor();
                if( TRUE === $orderUpdateRet ) {
                    $ret = TRUE;
                }
            }
            
        } catch (Exception $ex) {
            ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] task exception:");
            ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo]:" . $ex->getMessage());
            ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo]:" . $ex->getTraceAsString());
        }
        ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] done:" . var_export($ret, TRUE));
        return $ret;
    }
    
}
