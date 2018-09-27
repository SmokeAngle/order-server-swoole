<?php
namespace OrderServer\Libs\Task\Workflow;

use OrderServer\Libs\Task\Workflow\Base;

use OrderServer\Libs\Task\Gift\OrderDeductMoney;
use OrderServer\Libs\Task\Gift\OrderUpdate;
use OrderServer\Libs\Task\Gift\OrderIncrUserBalance;
use OrderServer\Libs\Task\Gift\OrderNotifyNodeServer;
use OrderServer\Libs\Task\Gift\OrderCalcCommission;

class Gift extends Base {
    
    public $workflows = array(
        OrderDeductMoney::TASK_NAME         => array( 
            self::WORKFLOW_CLASS_KEY                => '\OrderServer\Libs\Task\Gift\OrderDeductMoney', 
            self::WORKFLOW_FLAG_NEXT                => array( TRUE ),
            self::WORKFLOW_FLAG_BREAK_ON_SUCCESS    => array( -1 ),
            self::WORK_FLAG_FAIL                    => array( FALSE ),
        ),
        OrderUpdate::TASK_NAME              => array( self::WORKFLOW_CLASS_KEY => '\OrderServer\Libs\Task\Gift\OrderUpdate', self::WORKFLOW_FLAG_NEXT => array( TRUE ) ),
        OrderIncrUserBalance::TASK_NAME     => array( self::WORKFLOW_CLASS_KEY => '\OrderServer\Libs\Task\Gift\OrderIncrUserBalance', self::WORKFLOW_FLAG_NEXT => array( TRUE ) ),
        OrderCalcCommission::TASK_NAME      => array( self::WORKFLOW_CLASS_KEY => '\OrderServer\Libs\Task\Gift\OrderCalcCommission', self::WORKFLOW_FLAG_NEXT => array( TRUE ) ),
    );
    
    
}
