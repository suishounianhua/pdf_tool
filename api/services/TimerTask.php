<?php
/**
 * Created by PhpStorm.
 * User: peterpang
 * Date: 2017/11/27
 * Time: 下午3:48
 */

//use Phalcon\Di;
use Phalcon\Di;
use Phalcon\Mvc\Model\Resultset;
use Phalcon\Events\Event;
use Phalcon\Events\Manager as EventsManager;

class TimerTask {
    private $serv;

    protected $timer_tasks_used;
    protected $id;

    const TIMERTASK = 'timer_task';

    private $taskConfig = array(
        'start_time' => 'Y-m-d 00:00:00',
        'end_time' => 'Y-m-d 03:00:00',
        'task_name' => 'TestTask',
        'interval_time' => '3600');

//    private $mysqlConfig = array(
//        'host' => '127.0.0.1',
//        'user' => 'ERPDev',
//        'password' => '3lo-erpdev',
//        'database' => 'EstateERPDBV2',
//    );

//    private $mysqlConfig = array(
//        'host' => '127.0.0.1',
//        'user' => 'root',
//        'password' => 'ecube868wb',
//        'database' => 'zhitianErpV2FromV1',
//    );

    private $mysqlConfig = array(
        'host' => '',
        'user' => '',
        'password' => '',
        'database' => '',
    );



    public function __construct() {
        $this->serv = new swoole_server("0.0.0.0", 9502);
        $this->serv->set(array(
            'worker_num' => 8,
            'daemonize' => 1,  //守护进程化。设置`daemonize => 1`时，程序将转入后台作为守护进程运行。长时间运行的服务器端程序必须启用此项。
            'open_eof_check' => true,        //打开EOF检测
            'package_eof' => "\r\n\r\n", //设置EOF
            'open_eof_split' => true,        //启用EOF自动分包
            'debug_mode' => 0,
            'max_request' => 10000,
            'dispatch_mode' => 3,  //在`1.7.8`以上版本可用 > `dispatch_mode=1/3`时，底层会屏蔽`onConnect`/`onClose`事件，原因是这2种模式下无法保证
        ));
        $this->serv->on('Start', array($this, 'onStart'));
        $this->serv->on('Connect', array($this, 'onConnect'));
        $this->serv->on('Receive', array($this, 'onReceive'));
        $this->serv->on('Close', array($this, 'onClose'));
        $this->serv->on('WorkerStart', array($this, 'onWorkerStart'));
        $this->serv->start();
    }

    private static function connectMySql($mysqlConfig)
    {
        static $link = NULL;
        if ($link == NULL) {
            $link = mysqli_connect($mysqlConfig['host'], $mysqlConfig['user'], $mysqlConfig['password'], $mysqlConfig['database']);
        }
        if (!$link->ping()) {
            $link = mysqli_connect($mysqlConfig['host'], $mysqlConfig['user'], $mysqlConfig['password'], $mysqlConfig['database']);//重连

        }
        return $link;
    }

    public function runTask()
    {
        $link = self::connectMySql($this->mysqlConfig);
//        //如果合同到期，移除合同
        $query1 = "UPDATE ProjectContract SET ProjectContract.EffectFlg = 4 where ProjectContract.AgencyEndDt <='". date("Y-m-d")."' AND ProjectContract.DelFlg = 0 AND ProjectContract.EffectFlg = 3";

        $query2 = "UPDATE DistributedCompanyContractRenewal SET DistributedCompanyContractRenewal.EffectFlg = 2 where DistributedCompanyContractRenewal.AgencyEndDt <='". date("Y-m-d")."' AND DistributedCompanyContractRenewal.DelFlg = 0 AND DistributedCompanyContractRenewal.EffectFlg = 1";
        $query3 = "UPDATE DistributedCompanyContract SET DistributedCompanyContract.Status = 4 where DistributedCompanyContract.EndDt <='". date("Y-m-d")."' AND DistributedCompanyContract.DelFlg = 0 AND DistributedCompanyContract.Status = 1";
        $query4 = "UPDATE DistributedCompanyContract JOIN DistributedCompanyContractRenewal ON DistributedCompanyContractRenewal.ContractId = DistributedCompanyContract.ContractId AND DistributedCompanyContractRenewal.AgencyStartDt <='".date("Y-m-d")."' AND DistributedCompanyContractRenewal.AgencyEndDt >='".date("Y-m-d")."' AND DistributedCompanyContractRenewal.Status = 2 AND DistributedCompanyContractRenewal.EffectFlg = 1 SET DistributedCompanyContract.Status = 1 WHERE DistributedCompanyContract.EndDt <='". date("Y-m-d")."' AND DistributedCompanyContract.DelFlg = 0 AND DistributedCompanyContract.Status = 4";

        $query5 = "UPDATE ProjectCMContract SET ProjectCMContract.EffectFlg = 4 where ProjectCMContract.AgencyEndDt <='". date("Y-m-d")."' AND ProjectCMContract.DelFlg = 0 AND ProjectCMContract.EffectFlg = 3";
        $query6 = "UPDATE ProjectContract JOIN ProjectContractRenewal ON ProjectContract.ContractId = ProjectContractRenewal.ContractId AND ProjectContractRenewal.AgencyStartDt <='".date("Y-m-d")."' AND ProjectContractRenewal.AgencyEndDt >='".date("Y-m-d")."' AND ProjectContractRenewal.Status = 2 SET ProjectContract.EffectFlg = 3 WHERE ProjectContract.AgencyEndDt <='". date("Y-m-d")."' AND ProjectContract.DelFlg = 0 AND ProjectContract.EffectFlg = 4";
        $link->autocommit(false);
        $link->query($query1);
        $link->query($query2);
        $link->query($query3);
        $link->query($query4);
        $link->query($query5);
        $link->query($query6);
        if(!$link->errno){
            $link->commit();
        }else{
            $link->rollback();
        }
    }

    public function onStart($serv) {
        echo "Start\n";
    }
    public function onConnect($serv, $fd, $from_id) {
        echo "Client {$fd} connect\n";
    }
    public function onClose($serv, $fd, $from_id) {
        echo "Client {$fd} close connection\n";
    }
    public function onWorkerStart($serv, $worker_id) {
        if ($worker_id == 0) {
            // swoole_timer_tick(1000, array($this, 'onTick'), "Hello");
            $this->updateTimerTask();
            $this->timerTask();
            $this->id = swoole_timer_tick(1000, function () {
                $this->timerTask();
            });
        }
    }
    public function onReceive(swoole_server $serv, $fd, $from_id, $data) {
        echo "Get Message From Client {$fd}:{$data}\n";

        echo "Continue Handle Worker\n";
    }
    public function onTick($timer_id, $params = null) {
        echo "Timer {$timer_id} running\n";
        echo "Params: {$params}\n";

        echo "Timer running\n";
        echo "recv: {$params}\n";
    }

    /**
     * @param null $consulTask
     * @throws SwooleException
     */
    protected function updateTimerTask() {
        $timer_tasks = $this->taskConfig;

        $this->timer_tasks_used = [];
        //foreach ($timer_tasks as $name => $timer_task) {
        $task_name = $timer_tasks['task_name'] ?? '';
        if (empty($task_name) && empty($model_name)) {
            secho("[TIMERTASK]", "定时任务$task_name 配置错误，缺少task_name.");
            return;
        }
        if (!array_key_exists('start_time', $timer_tasks)) {
            $start_time = time();
        } else {
            $start_time = strtotime(date($timer_tasks['start_time']));
        }
        if (!array_key_exists('end_time', $timer_tasks)) {
            $end_time = -1;
        } else {
            $end_time = strtotime(date($timer_tasks['end_time']));
        }
        if (!array_key_exists('delay', $timer_tasks)) {
            $delay = false;
        } else {
            $delay = $timer_tasks['delay'];
        }
        $interval_time = $timer_tasks['interval_time'] < 1 ? 1 : $timer_tasks['interval_time'];
        $max_exec = $timer_tasks['max_exec'] ?? -1;
        $this->timer_tasks_used[] = [
            'task_name' => $task_name,
            'start_time' => $start_time,
            'next_time' => $start_time,
            'end_time' => $end_time,
            'interval_time' => $interval_time,
            'max_exec' => $max_exec,
            'now_exec' => 0,
            'delay' => $delay,
        ];
        //}
    }

    /**
     * 定时任务
     */
    public function timerTask() {
        $time = time();
        foreach ($this->timer_tasks_used as &$timer_task) {
            if ($timer_task['next_time'] < $time) {
                $count = round(($time - $timer_task['start_time']) / $timer_task['interval_time']);
                $timer_task['next_time'] = $timer_task['start_time'] + $count * $timer_task['interval_time'];
            }
            if ($timer_task['end_time'] != -1 && $time > $timer_task['end_time']) {
                //说明执行完了一轮，开始下一轮的初始化
                $timer_task['end_time'] += 86400;
                $timer_task['start_time'] += 86400;
                $timer_task['next_time'] = $timer_task['start_time'];
                $timer_task['now_exec'] = 0;
            }
            if (($time == $timer_task['next_time']) &&
                ($time < $timer_task['end_time'] || $timer_task['end_time'] == -1) &&
                ($timer_task['now_exec'] < $timer_task['max_exec'] || $timer_task['max_exec'] == -1)
            ) {
                if ($timer_task['delay']) {
                    $timer_task['next_time'] += $timer_task['interval_time'];
                    $timer_task['delay'] = false;
                    continue;
                }
                $timer_task['now_exec']++;
                $timer_task['next_time'] += $timer_task['interval_time'];
                echo "定时任务在这里执行,执行时间段：" . $this->taskConfig['start_time'] . " " . $this->taskConfig['end_time'] . "\n";
                $this->runTask();
            }
        }
    }
}
$server = new TimerTask();