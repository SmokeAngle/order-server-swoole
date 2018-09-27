<?php
namespace OrderServer\Libs\Task\Gift;

use OrderServer\Libs\Task\Base;
use OrderServer\Libs\ProcessManager;
use Swoole\Coroutine\Http\Client as HttpClient;
use Swoole\Coroutine\Client;

/**
 * 增加用户余额
 */
class OrderIncrUserBalance extends Base {
    
    const TASK_NAME = 'order_incr_user_balance';
    
    public function setConfig(array $config = array()) {
        $this->config = $config;
    }
    
    public function run() {
        ProcessManager::$logger->log("[task][" . __CLASS__ . "] start:");
        ProcessManager::$logger->log("[task][" . __CLASS__ . "] data:"  . var_export($this->data, TRUE) );
        $ret = FALSE;
        $tradeNo = $this->data['tradeNo'];
        try {
            $this->db->beginTransaction();
            $tradeOrderTbName = $this->config[sprintf('%s.table.trade_order', $this->gameSymbol)];
            $tradeGoodsTbName = $this->config[sprintf('%s.table.trade_goods', $this->gameSymbol)];
            $qUserTbName = $this->config[sprintf('%s.table.q_user', $this->gameSymbol)];
            $qTradeCharge = $this->config[sprintf('%s.table.q_trade_charge', $this->gameSymbol)];
            ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] start:");            
            $tradeSth = $this->db->prepare('SELECT oto.uid,oto.appuname,oto.amount,otg.gameid,oto.pagentid,otg.level,oto.tradetype,oto.coin FROM ' . $tradeOrderTbName . ' AS oto INNER JOIN ' . $tradeGoodsTbName .  ' AS otg ON otg.autoid = oto.goodid WHERE oto.tradeno = :tradeno;');
            $tradeSth->execute(array( 'tradeno' => $tradeNo ));
            $tradeData = $tradeSth->fetch(\PDO::FETCH_ASSOC);
            $tradeSth->closeCursor();
            if( !empty($tradeData) ) {
                
                $qUserSth = $this->db->prepare('SELECT coin,sumcoin,agentid,pagentid,uid,isupuser FROM ' . $qUserTbName . ' WHERE username = :username;');
                $qUserSth->execute(array( 'username' => $tradeData['appuname'] ));
                $qUserData = $qUserSth->fetch(\PDO::FETCH_ASSOC);
                $qUserSth->closeCursor();
                
                if( !empty($qUserData) ) {
                    $updateQUserSth = $this->db->prepare('UPDATE ' . $qUserTbName . ' SET coin = :coin, sumcoin = :sumcoin WHERE username=:username');
                    $updateQUserRet = $updateQUserSth->execute(array(
                        'coin'      => $qUserData['coin'] + $tradeData['amount'],
                        'sumcoin'   => $qUserData['sumcoin'] + $tradeData['coin']/100,
                        'username'  => $tradeData['appuname']
                    ));
                    $updateQUserSth->closeCursor();
                    if( $updateQUserRet ) {
                        if( intval($tradeData['pagentid']) > 0 && FALSE === array_search($tradeData['tradetype'], array('APPLE')) ) {
                            $tradeDesc = "玩家充值";
                        } else {
                            $tradeDesc = "未绑定推荐人,玩家充值";
                        }
                        $insertQTradeChargeSth = $this->db->prepare('INSERT INTO ' . $qTradeCharge . '(`uid`, `appuname`, `serialno`, `tradeno`, `gameid`, `tradeaction`, `begincoin`, `tradecoin`, `endcoin`, `tradedesc`) '
                                . 'VALUES(:uid, :appuname, :serialno, :tradeno, :gameid, :tradeaction, :begincoin, :tradecoin, :endcoin, :tradedesc)');
                        $insertQTradeChargeRet = $insertQTradeChargeSth->execute(array(
                            'uid'           => $tradeData['uid'],
                            'appuname'      => $tradeData['appuname'],
                            'serialno'      => $tradeData['appuname'] . $tradeData['gameid'] . time(),
                            'tradeno'       => $tradeNo,
                            'gameid'        => $tradeData['gameid'],
                            'tradeaction'   => 'RECHARGE',
                            'begincoin'     => $qUserData['coin'],
                            'tradecoin'     => $tradeData['amount'],
                            'endcoin'       => $qUserData['coin'] + $tradeData['amount'],
                            'tradedesc'     => $tradeDesc
                        ));
                        $insertQTradeChargeSth->closeCursor();
                        if( TRUE === $insertQTradeChargeRet ) {
                            $ret = TRUE;
                        }
                    }      
                }
                
            }            
        } catch (Exception $ex) {
            ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] task exception:");
            ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo]:" . $ex->getMessage());
            ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo]:" . $ex->getTraceAsString());
        }
        
        if( TRUE === $ret ) {
            ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] transction commit:");            
            $this->db->commit();
            $this->notifyServer($qUserData['uid'], $qUserData['coin'], $tradeData['amount'], $tradeNo);
        } else {
            ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] transction rollBack:");            
            $this->db->rollBack();
        }
        ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] done:" . var_export($ret, TRUE));
        return $ret;
    }
    
    
    private function notifyServer( $uid, $coin, $amount, $tradeNo  ) {
            \Swoole\Coroutine::create(function () use ( $uid, $coin, $amount, $tradeNo  ) {
                ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] notifyServer start:");     
                try {
                    $envConfig = ProcessManager::$appConfig->get(ProcessManager::$env);
                    $nodeServer = $envConfig[sprintf('%s.node.httpserver', $this->gameSymbol)];
                    $nodeServerData = parse_url($nodeServer);
                    $nodeServerHost = $nodeServerData['host'];
                    $nodeServerPort = $nodeServerData['port'];
                    $httpClient = new HttpClient($nodeServerHost, $nodeServerPort);
                    $httpClient->setHeaders(array(
                        'Content-Type' => 'application/json'
                    ));
                    $payload = array(
                        'uid' => $uid,
                        'currcoin' => $coin,
                        'num'   => $amount,
                        'gameid' => $this->gameId,
                    );
                    $payloadStr = json_encode($payload);
                    ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] notifyServer: $nodeServer"); 
                    ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] notifyServer data: $payloadStr"); 
                    $httpClient->post('/api/games/reCharge', $payloadStr);
                    $responseData = $httpClient->body;
                    ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] notifyServer result: $responseData"); 
                    $httpClient->close();    
                } catch (Exception $ex) {
                    ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo] notifyServer exception:");
                    ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo]:" . $ex->getMessage());
                    ProcessManager::$logger->log("[task][" . __CLASS__ . "][$tradeNo]:" . $ex->getTraceAsString());
                }
            });        
    }
    
    
}
