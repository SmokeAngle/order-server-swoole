<?php
namespace OrderServer\Libs\Task\Gift;

use OrderServer\Libs\Task\Base;
use OrderServer\Libs\ProcessManager;
/**
 * 计算返佣
 */
class OrderCalcCommission extends Base {
    
    const TASK_NAME = 'order_calc_commission';


    public function setConfig(array $config = array()) {
        $this->config = $config;
    }

    
    public function run() {
        ProcessManager::$logger->log("[task][" . __CLASS__ . "] start:");
        ProcessManager::$logger->log("[task][" . __CLASS__ . "] data:"  . var_export($this->data, TRUE) );
        $ret = TRUE;
        $tradeOrderTbName = $this->config[sprintf('%s.table.trade_order', $this->gameSymbol)];
        $tradeGoodsTbName = $this->config[sprintf('%s.table.trade_goods', $this->gameSymbol)];
        $qUserTbName = $this->config[sprintf('%s.table.q_user', $this->gameSymbol)];
        $oTradeChargeTbName = $this->config[sprintf('%s.table.trade_charge', $this->gameSymbol)];
        $oMemberTbName = $this->config[sprintf('%s.table.o_member', $this->gameSymbol)];
        $agentLevel = array(
               '0' =>  '区域',
               '1' =>  '一级',
               '2' =>  '二级',
               '3' =>  '三级'
           );            
        $tradeNo = $this->data['tradeNo'];
        $cashCoin = $this->data['cashCoin'];
        ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] start:");
        
        try {
            $this->db->beginTransaction();
            $tradeSql = 'SELECT oto.appuname,oto.amount,otg.gameid,oto.pagentid,otg.level,oto.tradetype,qu.agentid,qu.uid,qu.isupuser
               FROM ' . $tradeOrderTbName . ' AS oto 
               INNER JOIN ' . $tradeGoodsTbName . ' AS otg ON otg.autoid = oto.goodid 
               INNER JOIN ' . $qUserTbName . ' AS qu ON qu.username = oto.appuname
               WHERE oto.tradeno = :tradeno;';
           $tradeSth = $this->db->prepare($tradeSql);
           $tradeSth->execute(array( 'tradeno' => $tradeNo ));
           $tradeData = $tradeSth->fetch(\PDO::FETCH_ASSOC);
           $tradeSth->closeCursor();
           if( !empty($tradeData) ) {
               if( intval($tradeData['pagentid']) && FALSE === array_search($tradeData['tradetype'], array('APPLE')) ) {
                   $pagentId = $tradeData['pagentid'];
                   $childCommissionRate = 0;
                   $isOwner = TRUE;
                   $associateAgentUserId = NULL;
                   $isOldAgent = FALSE;
                   while ( !empty($pagentId) && $ret === TRUE ) {
                       
                       $oMemberSth = $this->db->query('SELECT uid,puid,level,commission,oldpcode,tooldcom,coin,sumcoin FROM ' . $oMemberTbName . ' WHERE uid = ' . $pagentId);
                       $memberData = $oMemberSth->fetch(\PDO::FETCH_ASSOC);
                       $oMemberSth->closeCursor();
                       //顶级牛 $memberData['level'] < 0 0==> 总代  1=> 一级代理  2=> 2级代理
                       $minCommissionLevelConfigKey = sprintf('%s.commission.min_level', $this->gameSymbol);
                       $minCommissionLevel = array_key_exists($minCommissionLevelConfigKey, $this->config) ? intval($this->config[$minCommissionLevelConfigKey]) : 1;
                       ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] minCommissionLevel = $minCommissionLevel");
                       if( empty($memberData) || is_null($memberData['level']) || intval($memberData['level']) < $minCommissionLevel ) {
                            ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] level = " . $memberData['level'] . ' break');    
                           break;
                       }
                       $oldPcode = $memberData['oldpcode'];
                       $tooldcomRate = $memberData['tooldcom'];
                       $agentUserLevel= intval($memberData['level']);
                       $parentUid = $memberData['puid'];
                       $agentCommissionRate = $memberData['commission'];

                       if( TRUE === $isOwner && $agentUserLevel === 2 && $oldPcode > 0 && $tooldcomRate > 0 && intval($tradeData['isupuser']) === 0 ) {
                           $oMemberUpAgentSth = $this->db->query('SELECT uid FROM ' . $oMemberTbName . ' WHERE code = "' . $oldPcode . '"');
                           $oMemberUpAgentData = $oMemberUpAgentSth->fetch(\PDO::FETCH_ASSOC);
                           if( !empty($oMemberUpAgentData) ) {
                               $agentCommissionRate -= $tooldcomRate;
                               $parentUid = $oMemberUpAgentData['uid'];
                               $associateAgentUserId = $oMemberUpAgentData['uid'];
                               $isOldAgent = TRUE;
                           }   
                       }

                       $actualCommissionRate = $agentCommissionRate - $childCommissionRate;
                       ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] $agentLevel[$agentUserLevel]代理-$pagentId actualCommissionRate:$actualCommissionRate");
                       if( $actualCommissionRate > 0 ) {
                           $actualCommissionAmount = round($cashCoin * $actualCommissionRate);
                           $childCommissionRate = $agentCommissionRate;
                           $finallyCoin = $memberData['coin'] + $actualCommissionAmount;
                           $finallySumCoin = $memberData['sumcoin'] + $actualCommissionAmount;

                           $updateMemberSth = $this->db->prepare('UPDATE ' . $oMemberTbName . ' SET coin = :coin, sumcoin = :sumcoin WHERE uid = :uid');
                           $updateMemberRet = $updateMemberSth->execute(array(
                               'coin'      => $finallyCoin,
                               'sumcoin'   => $finallySumCoin,
                               'uid'       => $pagentId
                           ));
                           $updateMemberSth->closeCursor();
                           if( TRUE ===  $updateMemberRet ) {
                               $insertOTradeChargeSql = 'INSERT INTO ' . $oTradeChargeTbName . '(`uid`, `reappuname`, `serialno`, `tradeno`, `gameid`, `tradeaction`, `begincoin`, `tradecoin`, `endcoin`, `totalamount`, `commrate`, `tradedesc`, `type`) '
                                       . 'VALUES(:uid, :reappuname, :serialno, :tradeno, :gameid, :tradeaction, :begincoin, :tradecoin, :endcoin, :totalamount, :commrate, :tradedesc, :type)';
                               $insertOTradeChargeSth = $this->db->prepare($insertOTradeChargeSql);
                               $insertOTradeChargeRet = $insertOTradeChargeSth->execute(array(
                                   'uid'           => $pagentId,
                                   'reappuname'    => $tradeData['appuname'],
                                   'serialno'      => $pagentId . $tradeData['gameid'] . time(),
                                   'tradeno'       => $tradeNo,
                                   'gameid'        => $tradeData['gameid'],
                                   'tradeaction'   => 'REBATE',
                                   'begincoin'     => $finallyCoin - $actualCommissionAmount,
                                   'tradecoin'     => $actualCommissionAmount,
                                   'endcoin'       => $finallyCoin,
                                   'totalamount'   => $cashCoin,
                                   'commrate'      => $actualCommissionRate,
                                   'tradedesc'     => sprintf("%s%s%s返佣", $associateAgentUserId == $pagentId ? "老" : "", $agentLevel[$agentUserLevel], "代理"),
                                   'type'          => TRUE === $isOwner ? 0 : 1
                               ));

                               if( FALSE === $insertOTradeChargeRet ) {
                                   $ret = FALSE;
                               } else {
                                   ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] $agentLevel[$agentUserLevel]代理-$pagentId done");
                               }
                           } else {
                               $ret = FALSE;
                           }
                       } else {
                           $ret = FALSE;
                       }

                       $pagentId = $memberData['puid'];
                       $isOwner = FALSE;
                   }
                   
               }
           }   
        } catch (Exception $ex) {
            ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] task exception:");
            ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo]:" . $ex->getMessage());
            ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo]:" . $ex->getTraceAsString());
            $ret = FALSE;
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
