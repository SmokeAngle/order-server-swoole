<?php
namespace OrderServer\Libs\Task\Workflow;
use OrderServer\Libs\ProcessManager;

class Base {
    
    const WORK_FLAG_FAIL = 'fail';
    
    const WORK_FALG_SUCCESS = 'success';

    const WORKFLOW_FLAG_NEXT = 'next';
    
    const WORKFLOW_FLAG_BREAK_ON_SUCCESS = 'break_on_ok';
    
    const WORKFLOW_FLAG_FAIL = 'fail';

    const WORKFLOW_CLASS_KEY = 'class';
    
    public $gameSymbol = NULL;
    
    public $data = NULL;

    public $workflows = array(
        
    );
    
    public function __construct( $gameSymbol, $data ) {
        $this->gameSymbol = $gameSymbol;
        $this->data = $data;
    }


    public function run() {
        $ret = TRUE;
        try {
            ProcessManager::$logger->log("[workflow][" . get_called_class() . "] start");
            ProcessManager::$logger->log("[workflow][" . get_called_class() . "] data : " . var_export($this->data, TRUE));
            $isValidWork = $this->checkWork();
            ProcessManager::$logger->log("[workflow][" . get_called_class() . "] checkWork : " . var_export($isValidWork, TRUE));
            if( $isValidWork ) {
                $commonConfig = ProcessManager::$appConfig->get('common');
                foreach ( $this->workflows as  $work =>$workConfig ) {
                    $nextCondiions = $workConfig[self::WORKFLOW_FLAG_NEXT];
                    $taskClassName = $workConfig[self::WORKFLOW_CLASS_KEY];
                    $task = (new $taskClassName($this->data, $this->gameSymbol));
                    $task->setConfig($commonConfig);
                    $task->initConnection();
                    $ret = $task->run();
                    
                    if( isset($workConfig[self::WORKFLOW_FLAG_FAIL]) ) {
                        if(count(array_filter($workConfig[self::WORKFLOW_FLAG_FAIL], function ( $val ) use ($ret) { return $val === $ret;})) > 0){
                            $ret = FALSE;
                            break;   
                        }
                    }
                    if( isset($workConfig[self::WORKFLOW_FLAG_BREAK_ON_SUCCESS]) ) {
                        if(count(array_filter($workConfig[self::WORKFLOW_FLAG_BREAK_ON_SUCCESS], function ( $val ) use ($ret) { return $val === $ret;})) > 0){
                            $ret = TRUE;
                            break;
                        }
                    }
                    if( !in_array($ret, $nextCondiions) ) {
                        break;
                    }
                }
            }
        } catch (Exception $ex) {
            ProcessManager::$logger->log("[task][" . get_called_class() . "] task exception:");
            ProcessManager::$logger->log("[task][" . get_called_class() . "]:" . $ex->getMessage());
            ProcessManager::$logger->log("[task][" . get_called_class() . "]:" . $ex->getTraceAsString());
        }
        ProcessManager::$logger->log("[workflow][" . get_called_class() . "] done : " . var_export($ret, TRUE));
        return $ret;
    }
    
    
    public function checkWork() {
        $ret = TRUE;
        foreach ( $this->workflows as $work =>$workConfig ) {
            if( !array_key_exists(self::WORKFLOW_CLASS_KEY, $workConfig) || !array_key_exists(self::WORKFLOW_FLAG_NEXT, $workConfig) ) {
                $ret = FALSE;
                break;
            } else {
                $taskClassName = $workConfig[self::WORKFLOW_CLASS_KEY];
                if( !class_exists($taskClassName) ) {
                    $ret = FALSE;
                    break;
                } else {
                    if( !in_array('run', get_class_methods($taskClassName)) ) {
                        $ret = FALSE;
                        break;
                    }
                }
            }
        }
        return $ret; 
    }
    
    
}
