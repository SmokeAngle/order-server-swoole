<?php
namespace OrderServer\Libs\Task\Gift;

use OrderServer\Libs\Task\Base;
use OrderServer\Libs\ProcessManager;

/**
 * 订单扣款
 */
class OrderDeductMoney extends  Base {
    
    const TASK_NAME = 'order_deduct_money';
        
    public function setConfig(array $config = array()) {
        $this->config = $config;
    }

    public function setRabbitMqConnection(\PhpAmqpLib\Connection\AMQPStreamConnection $rabbitConnection) {
        $this->rabbitMq = $rabbitConnection;
    }
    
    public function setDbConnection(\OrderServer\Libs\Utils\ConnectionManagerPDO $dbConnection) {
        $this->db = $dbConnection;
    }

    public function run() {
        ProcessManager::$logger->log("[task][" . __CLASS__ . "] start:");
        ProcessManager::$logger->log("[task][" . __CLASS__ . "] data:"  . var_export($this->data, TRUE) );
        $ret = FALSE;
        $tradeGoodsTbName = $this->config[sprintf('%s.table.trade_goods', $this->gameSymbol)];
        $tradeOrderTbName = $this->config[sprintf('%s.table.trade_order', $this->gameSymbol)];
        $oMemberTbName = $this->config[sprintf('%s.table.o_member', $this->gameSymbol)];
        $qUserTbName = $this->config[sprintf('%s.table.q_user', $this->gameSymbol)];
        $tradeChargeTbName = $this->config[sprintf('%s.table.trade_charge', $this->gameSymbol)];
        
        $tradeNo = $this->data['tradeNo'];
        $agentUid = $this->data['agentUid'];
        $goodsId = $this->data['goodsId'];
        ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] start:");
        try { 
            $this->db->beginTransaction();
            $orderSth = $this->db->prepare('SELECT tradeno, coin, uid,appuname FROM ' . $tradeOrderTbName . ' WHERE tradeno = :trade_no;' );
            $orderSth->execute(array( 'trade_no' => $tradeNo ));
            $orderData = $orderSth->fetch(\PDO::FETCH_ASSOC);
            $orderSth->closeCursor();
              
            $oMemberSth = $this->db->prepare('SELECT coin FROM ' . $oMemberTbName . ' WHERE uid = :uid');
            $oMemberSth->execute(array( 'uid' => $agentUid ));
            $oMemberData = $oMemberSth->fetch(\PDO::FETCH_ASSOC);
            $oMemberSth->closeCursor();
            
            if( !empty($orderSth) && !empty($oMemberData) && $oMemberData['coin'] > $orderData['coin'] ) {
                $updateMemberSth = $this->db->prepare('UPDATE ' .$oMemberTbName . ' SET coin = :coin WHERE uid = :uid; ' );
                $updateMemberRet = $updateMemberSth->execute(array(
                    'coin'  => $oMemberData['coin'] - $orderData['coin'],
                    'uid'   => $agentUid
                ));
                $updateMemberSth->closeCursor();
                if( $updateMemberRet ) {

//                    $qUserSth = $this->db->prepare('SELECT username,uid FROM ' . $qUserTbName . ' WHERE agentid = :agentid' );
//                    $qUserSth->execute(array( 'agentid' => $agentUid));
//                    $qUserData  = $qUserSth->fetch(\PDO::FETCH_ASSOC);
//                    $qUserSth->closeCursor();

                    $tradeGoodSth = $this->db->prepare('SELECT price,description FROM ' . $tradeGoodsTbName . ' WHERE autoid = :goodid;');
                    $tradeGoodSth->execute(array( 'goodid' => $goodsId ));
                    $tradeGoodData = $tradeGoodSth->fetch();
                    $tradeGoodSth->closeCursor();


//                    if( !empty($qUserData) && !empty($tradeGoodData) ) {
                    if( !empty($tradeGoodData) ) {
                        $tradeChargeSql = 'INSERT INTO ' . $tradeChargeTbName . '(`uid`, `reuid`, `reappuname`, `serialno`, `tradeno`, `gameid`, `tradeaction`, `begincoin`, `tradecoin`, `endcoin`, `totalamount`, `tradedesc`, `type`) VALUES(:uid, :reuid, :reappuname, :serialno, :tradeno, :gameid, :tradeaction, :begincoin, :tradecoin, :endcoin, :totalamount, :tradedesc, :type)';
                        $tradeChargeSth = $this->db->prepare($tradeChargeSql);
                        $tradeChargeRet = $tradeChargeSth->execute(array(
                            'uid'           => $agentUid,
                            'reuid'         => $orderData['uid'],
                            'reappuname'    => $orderData['appuname'],
                            'serialno'      => $agentUid . $this->gameId . time(),
                            'tradeno'       => $tradeNo,
                            'gameid'        => $this->gameId,
                            'tradeaction'   => 'PAYMENT',
                            'begincoin'     => $oMemberData['coin'],
                            'tradecoin'     => -$orderData['coin'],
                            'endcoin'       => $oMemberData['coin'] - $orderData['coin'],
                            'totalamount'   => $orderData['coin'],
                            'tradedesc'     => '代理' . $agentUid . '为用户' . $orderData['uid']  . '购买' . $tradeGoodData['description'],
                            'type'          => 2
                        )); 
                        $tradeChargeSth->closeCursor();
                        if( TRUE === $tradeChargeRet ) {
                            $ret = TRUE;
                        }
                    }
                }
            } else {
                $ret = -1;  
            }
        } catch (Exception $ex) {
            ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] task exception:");
            ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo]:" . $ex->getMessage());
            ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo]:" . $ex->getTraceAsString());
        }
        
        if( TRUE === $ret ) {
            ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] transction commit:");
            $this->db->commit();
        } else {
            ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] transction rollBack:");
            $this->db->rollBack();
        }        
        ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] done:" . var_export($ret, TRUE));
        return $ret;
    }
}
