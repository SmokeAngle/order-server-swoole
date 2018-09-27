<?php
namespace OrderServer\Libs\Task\Gift;

use OrderServer\Libs\Task\Base;
use OrderServer\Libs\Utils\ConnectionManagerPDO;
use OrderServer\Libs\ProcessManager;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use OrderServer\Libs\Process\Http\Resource\Order;

/**
 * @property \PDO $db 
 * @property \PhpAmqpLib\Connection\AMQPStreamConnection $rabbitMq 
 */
class OrderCreate extends Base {
    
    const TASK_NAME = 'order_create';
    
    public function setConfig( array $config = array() ) {
        $this->config = $config;
    }
    
    public function run() {
        $ret = FALSE;
        ProcessManager::$logger->log("[task][" . __CLASS__ . "] start:");
        ProcessManager::$logger->log("[task][" . __CLASS__ . "] data:"  . var_export($this->data, TRUE) );
        $tradeGoodsTbName = $this->config[sprintf('%s.table.trade_goods', $this->gameSymbol)];
        $tradeOrderTbName = $this->config[sprintf('%s.table.trade_order', $this->gameSymbol)];
        $oMemberTbName = $this->config[sprintf('%s.table.o_member', $this->gameSymbol)];
        $qUserTbName = $this->config[sprintf('%s.table.q_user', $this->gameSymbol)];
        $configTbName = $this->config[sprintf('%s.table.o_config', $this->gameSymbol)];
        $agentUid = $this->data['agent_uid'];
        $productId = $this->data['good_id'];
        $tradeNo = $this->data['trade_no'];
        $refillUid = $this->data['refill_uid'];
        $type = intval($this->data['type']);
        
        try {  
            ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] start:");
            $productSth = $this->db->prepare('SELECT name,price,amount,bonus,gameid FROM ' . $tradeGoodsTbName .  ' WHERE autoid = :goods_id AND status = 1 AND level = 2');
            $productSth->execute(array('goods_id' => $productId));
            $productResult = $productSth->fetch(\PDO::FETCH_ASSOC);
            $productSth->closeCursor();
            $configSql = "SELECT 
                                MAX(CASE WHEN `name` = 'GIFTPAY_CONFIG_SPADMIN' THEN `value` END) AS GIFTPAY_CONFIG_SPADMIN,
                                MAX(CASE WHEN `name` = 'GIFTPAY_CONFIG_SP' THEN `value` END) AS GIFTPAY_CONFIG_SP
                            FROM $configTbName 
                            WHERE `name` IN('GIFTPAY_CONFIG_SPADMIN', 'GIFTPAY_CONFIG_SP') AND `status` = 1;";
            $configSth = $this->db->query($configSql);
            $configData = $configSth->fetch(\PDO::FETCH_ASSOC);
            ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] config:" . var_export($configData, TRUE));
            ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] product:" . var_export($productResult, TRUE));
            if( FALSE !== $productResult && FALSE !== $configData ) {
                //$agentUserSth = $this->db->prepare('SELECT u.uid, u.username, m.uid AS pagentid, m.code,m.type,m.level, m.pcode FROM ' . $oMemberTbName . ' AS m INNER JOIN '. $qUserTbName .' AS u ON u.agentid = m.uid WHERE m.uid = :uid');
                //@todo 代理给自己充值，返佣给自己， 代理给他人充值，返佣给充值账户绑定得代理
                if( $type === Order::ORDER_TYPE_GIFT_ORDER_RECHARGE_SELF ) { 
                    $agentUserSth = $this->db->prepare('SELECT qu.uid,om.uid AS pagentid,om.`code` AS pcode FROM ' . $qUserTbName . ' AS qu LEFT JOIN ' . $oMemberTbName . ' AS om ON om.uid = qu.agentid WHERE qu.uid = :uid');
                } else {
                    $agentUserSth = $this->db->prepare('SELECT qu.uid,om.uid AS pagentid,om.`code` AS pcode FROM ' . $qUserTbName . ' AS qu LEFT JOIN ' . $oMemberTbName . ' AS om ON om.uid = qu.pagentid WHERE qu.uid = :uid');
                }
                $agentUserSth->execute(array('uid' => $refillUid));
                $agentUserResult = $agentUserSth->fetch(\PDO::FETCH_ASSOC);
                $agentUserSth->closeCursor();
                ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] agentUser:" . var_export($agentUserResult, TRUE));
                
                $rechargeUserSth = $this->db->prepare('SELECT uid,username FROM ' . $qUserTbName . ' WHERE uid = :uid');
                $rechargeUserSth->execute(array( 'uid' => $refillUid ));
                $rechargeUserResult = $rechargeUserSth->fetch(\PDO::FETCH_ASSOC);
                $rechargeUserSth->closeCursor();
                ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] rechargeUser:" . var_export($rechargeUserResult, TRUE));
                
                
                if( FALSE !== $agentUserResult ) {
                    $todayOrderSth = $this->db->query("SELECT autoid,goodid FROM o_trade_order WHERE pagentid = $agentUid AND tradetype = 'AGENTPAY' AND order_status = 1 AND dtime > '" . date("Y-m-d 00:00:00", time()) . "';");
                    $todayOrderData = $todayOrderSth->fetchAll(\PDO::FETCH_ASSOC);
                    $todayOrderSth->closeCursor();
                 //   ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] productId = $productId sql: SELECT autoid,goodid FROM o_trade_order WHERE pagentid = $agentUid AND tradetype = 'AGENTPAY' AND order_status = 1 AND dtime > '" . date("Y-m-d 00:00:00", time()) . "';" );
                    ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] productId = $productId isLimited:" . var_export($todayOrderData, TRUE));
                    $isLimited = FALSE;
                    if( $type === Order::ORDER_TYPE_GIFT_ORDER_RECHARGE_SELF ) { //给自己充值限制次数, 给别人充值不限制次数
                        $isSpAdmin = $this->isSpAdmin($agentUserResult['type'], $agentUserResult['level']);
                        ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] isSpAdmin:" . var_export($isSpAdmin, TRUE));
                        $orderCheckType = $isSpAdmin ? intval($configData['GIFTPAY_CONFIG_SPADMIN']) : intval($configData['GIFTPAY_CONFIG_SP']);
                        if( $orderCheckType === 2 ) { //每天每种商品只能购买一次
                            if( count(array_filter($todayOrderData, function( $data ) use ( $productId ) { return intval($data['goodid']) === intval($productId); })) > 0 ) {
                                $isLimited = TRUE;
                            }
                        } elseif( $orderCheckType === 3 ) { //每天只能购买一种商品
                            if( !empty($todayOrderData) ) {
                                $isLimited = TRUE;
                            }
                        }   
                    }

                    ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] isLimited:" . var_export($isLimited, TRUE));
                    if( FALSE === $isLimited ) {
                        $tradeOrderSth = $this->db->prepare('INSERT INTO ' . $tradeOrderTbName . '(`uid`, `appuname`, `goodid`, `tradeno`, `tradetype`, `coin`, `pagentid`, `pcode`, `amount`) VALUES(:uid, :appuname, :goodid, :tradeno, :tradetype, :coin, :pagentid, :pcode, :amount)');
                        $ret = $tradeOrderSth->execute(array(
                            'uid'       => $rechargeUserResult['uid'],
                            'appuname'  => $rechargeUserResult['username'],
                            'goodid'    => $productId,
                            'tradeno'   => $tradeNo,
                            'tradetype' => 'AGENTPAY',
                            'coin'      => $productResult['price'],
                            'pagentid'  => empty($agentUserResult['pagentid']) ? 0 : $agentUserResult['pagentid'],
                            'pcode'     => empty($agentUserResult['pcode']) ? 0 : $agentUserResult['pcode'],
                            'amount'    => $productResult['amount'] + $productResult['bonus']
                        ));
                        $tradeOrderSth->closeCursor();
                        if( $ret ) {
                            $data = array(
                                'agentUid'  => $agentUid,
                                'tradeNo'   => $tradeNo,
                                'game'      => $this->gameSymbol,
                                'goodsId'   => $productId,
                                'cashCoin'  => $productResult['price'],
                                'refillUid' => $refillUid
                            );
                            $this->publisher($data);
                            $ret = TRUE;
                        }   
                    } else {
                        ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] order create limited:");
                    }                    
                }
            }
            ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] done:");
        } catch (\Exception $ex) {
            ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] task exception:");
            ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo]:" . $ex->getMessage());
            ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo]:" . $ex->getTraceAsString());
        }
        ProcessManager::$logger->log("[task][" . __CLASS__ . "] done:" . var_export($ret, TRUE));
        return $ret;
    }
    
    /**
     * 消息发布
     * 
     * @param array $data
     * @return void
     */
    public function publisher( array $data = array() ) {
        ProcessManager::$logger->log("[task][" . __CLASS__ . "] publishe start:");
        ProcessManager::$logger->log("[task][" . __CLASS__ . "] publishe data:" . var_export($data, TRUE));
        try {
            if( !empty($data) ) {
                $dataStr = json_encode($data);
                $exchange = $this->config[sprintf('%s.mq.order_exchange', $this->gameSymbol)];
                $queue = $this->config[sprintf('%s.mq.order_queue', $this->gameSymbol)];
                $routeKey = $this->config[sprintf('%s.mq.order_route_key', $this->gameSymbol)];
                $mqChannel = $this->rabbitMq->channel(1);
                $mqChannel->confirm_select();
                $mqChannel->exchange_declare($exchange, 'direct', FALSE, TRUE, FALSE);
                $mqChannel->queue_declare($queue, FALSE, TRUE, FALSE, FALSE);
                $mqChannel->queue_bind($queue, $exchange, $routeKey);
                $message = new AMQPMessage($dataStr, array('content_type' => 'text/plain', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));
                $mqChannel->basic_publish($message, $exchange, $routeKey);   
                ProcessManager::$logger->log("[task][" . __CLASS__ . "] publishe done");
            }     
        } catch (\Exception $ex) {
            ProcessManager::$logger->log("[task][" . __CLASS__ . "] publishe exception:");
            ProcessManager::$logger->log($ex->getMessage());
            ProcessManager::$logger->log($ex->getTraceAsString());
        }
    }
    
    /**
     * 是否代理管理员
     * 
     * @param int $type   用户类别
     * @param int $level  代理级别
     * @return bool
     */
    private function isSpAdmin( $type, $level ) {
        return intval($type) === 2 && intval($level) === 0;
    }
    
}
