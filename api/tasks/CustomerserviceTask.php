<?php

use Phalcon\Cli\Task;

class CustomerserviceTask extends Task
{


    const HOST = '127.0.0.1';
    const PORT = '9501';
    //待回复消息统计
    const MESSAGE_STATISTICS = 'messageStatistics';
    //用户key跟swoole的fd对应列表
    const TRANSFER_TABLE = 'transferTable';
    //待接入消息队列
    const WAIT_MESSAGE_QUEUE = 'waitMessage';
    //正在对话消息列表
    const MESSAGE_QUEUE = 'message';
    //离线消息队列
    const OFFLINE_QUEUE = 'offlineMessage';
    //在线的客服队列
    const CLERK_QUEUE = 'clerk';
    //在线的访客队列
    const VISITOR_QUEUE = 'visitor';
    //3分钟无任何会话，自动过期
    const EXPIRES = 600;
    //redis过期时间
    const REDIS_EXPIRES = 601;
    //最大任务数
    const TASK_NUM = 10;
//    //api地址
//    const API_URL = 'http://www.oim.com/api/';
    //webSocket连接对象
    public $server = null;


    public function initialize()
    {
        $this->server = new swoole_websocket_server(self::HOST, self::PORT);
        $this->server->set([
            //启动task必须要设置其数量
            'task_worker_num' => self::TASK_NUM,
            //心跳检测间隔
              'heartbeat_check_interval' => self::EXPIRES,
            //最大闲置时间 30s
              'heartbeat_idle_time' => self::EXPIRES,
        ]);
        $this->server->on('open', [$this, 'onOpen']);
        $this->server->on('workerstart', [$this, 'onWorkerstart']);
        $this->server->on('request', [$this, 'onRequest']);
        $this->server->on('message', [$this, 'onMessage']);
        $this->server->on('task', [$this, 'onTask']);
        $this->server->on('finish', [$this, 'onFinish']);
        $this->server->on('close', [$this, 'onClose']);
        $this->server->start();
    }

    public function onWorkerstart($server, $id){
        $server->redis = $this->getDI()->get('redisCache');
    }


    public function onRequest($request, $response){

    }



    public function mainAction()
    {
        echo "This is the default task and the default action" . PHP_EOL;
    }

    public function onOpen($server, $request)
    {

        if (isset($request->get['id']) && isset($request->get['type']) &&
            in_array($request->get['type'], [self::CLERK_QUEUE, self::VISITOR_QUEUE]))
        {
            $server->push($request->fd, "服务已启动。");
            if($request->get['type'] == self::VISITOR_QUEUE && !empty($request->header['x-real-ip'])){
                $csr_session = CsrSession::findFirst([
                    'conditions' => ['from_key' => $request->get['id'] . ":" . self::VISITOR_QUEUE]
                ]);
                if (!$csr_session) {
                    $csr_session = new CsrSession();
                }
                $city = new ipip\db\City(APP_PATH . '/library/ipiptest.ipdb');
                $city_info = $city->findMap($request->header['x-real-ip'], 'CN');
                $session_data = array(
                    'from_key' => $request->get['id'] . ":" . self::VISITOR_QUEUE,
                    'client_ip' => $request->header['x-real-ip'],
                    'country' => $city_info['country_name'],
                    'province' => $city_info['region_name'],
                    'city' => $city_info['city_name'],
                );
                $csr_session->save($session_data);

            }

            $id = intval($request->get['id']);
            $type = $request->get['type'];
            $key = $id . ':' . $type;
            //加进在线队列，如果 key 已经存在， 则关闭，
            //限制一个客户最多打开二十个客户端
            $fd_key = $server->redis->get($key);
            if (!$fd_key) {
                $server->redis->setex($key, intval(self::REDIS_EXPIRES), $request->fd);
            }else{
                $fd_list = count(explode(":",array_shift(explode('|',$fd_key))));

                if($fd_list>20){
                    $server->push($request->fd, "服务关闭。");
                    $server->disconnect($request->fd);
                    return;
                }
                $server->redis->setex($key, intval(self::REDIS_EXPIRES), $request->fd.':'.$fd_key);
            }
            $server->redis->hSet(self::TRANSFER_TABLE,$request->fd,$key);
            //以下为内部调度进程处理
            //处理发送离线消息
            if ($server->redis->hexists(self::OFFLINE_QUEUE, $key))
            {
                $param = ['emit' => 'offlineMessage', 'key' => $key, 'fd' => $request->fd];
                $server->task($param);
            }
            //处理发送在线客服上线通知
            if ($type == self::CLERK_QUEUE)
            {
                $param = ['emit' => 'status', 'key' => $key, 'status' => 'online'];
                $server->task($param);
            }
        }
        else
        {
            $server->push($request->fd, "服务关闭。");
            $server->disconnect($request->fd);
        }
    }

    /**
     * 监听接收事件的回调
     * 分发任务执行
     *
     * @param  $server swoole_websocket_server
     * @param  $frame swoole_websocket_frame
     * @return void
     */
    public function onMessage($server, $frame)
    {
        if(!empty($frame->data) && substr($frame->data,0,1) == '{'){
            $data = json_decode($frame->data, true);
        }else{
            $data = $frame->data;
        }

        if($frame->data == 'ping'){
            $userkey = $server->redis->hGet(self::TRANSFER_TABLE,$frame->fd);
            $server->redis->expire($userkey, intval(self::REDIS_EXPIRES));
        }

        if (isset($data['emit']))
        {
            $data['fd'] = $frame->fd;
            $data['client_ip'] =  $server->connection_info($frame->fd)['remote_ip'];
            $server->task($data);
        }
    }
    /**
     * 监听关闭事件的回调
     * @param  $server swoole_websocket_server
     * @param  $fd 会话连接ID
     * @return void
     */
    public function onClose($server, $fd)
    {

        $key = $server->redis->hGet(self::TRANSFER_TABLE,$fd);
        if (!empty($key))
        {
            //处理发送在线状态消息
            $param = ['emit' => 'status', 'key' => $key, 'status' => 'offline'];
            $server->redis->hDel(self::TRANSFER_TABLE,$fd);
            $server->task($param);
        }
        $value = $server->redis->get($key);
        if (is_string($value))
        {
            $users = explode('|',$value);
            $fdList = explode(':', $users[0]);
            foreach ($fdList as $k=>$value){
                if($value == $fd){
                    unset($fdList[$k]);
                }
            }
            if (count($fdList)<=0){
                $server->redis->del($key);
            }else {
                $fdList = implode(':', $fdList);
                $users[0] = $fdList;
                $value = implode('|',$users);
                $server->redis->setex($key, intval(self::REDIS_EXPIRES), $value);
            }


        }
    }
    /**
     * 执行任务回调事件
     *
     * @param  $server swoole_websocket_server
     * @param  $task_id 任务ID，由swoole扩展内自动生成，用于区分不同的任务
     * @param  $src_worke r_id   $task_id和$src_worker_id组合起来才是全局唯一的，不同的worker进程投递的任务ID可能会有相同
     * @param  $data  参数传递
     * @return void
     */
    public function onTask($server, $task_id, $src_worker_id, $data)
    {
        switch ($data['emit'])
        {                                                  
            case 'msg':
                $userlist = $server->redis->get($data['from']['key']);
                if (false === strpos($userlist, '|' . $data['to']['key'])) {
                    $userlist = $server->redis->get($data['from']['key']) . '|' . $data['to']['key'];
                }
                $server->redis->setex($data['from']['key'], intval(self::REDIS_EXPIRES), $userlist);
                $data['from']['timestamp'] = time();
                // $data['from']['message'] = nl2br($data['from']['message']);
                if($fd_list = array_shift(explode('|',$server->redis->get($data['to']['key']))))
                {
                    //判断接收人是否在线
                    $message = ['emit' => 'msg', 'data' => $data['from']];

                    $fd_list = array_unique(explode(':',$fd_list));
                    foreach ($fd_list as $k=>$fd) {
                        if($fd != $data['fd']) {
                            $server->push((int)$fd, json_encode($message));
                        }
                    }

                    $clerkList = Staff::find(['conditions'=>['status >=' => '0'],'columns'=>['id']]);
                    foreach ($clerkList as $clerkId){
                        $clerk_fd = array_unique(explode(':',array_shift(explode('|',$server->redis->get($clerkId['id'].':'.self::CLERK_QUEUE)))));
                        foreach ($clerk_fd as $k=>$fd) {
                            if(is_numeric($fd)) {
                                if ($fd != $data['fd'] && !in_array($fd, $fd_list)) {
                                    if (preg_match('/' . self::CLERK_QUEUE . '/', $data['from']['key'])) {
                                        $message['data']['key'] = $data['to']['key'];
                                        $message['data']['tag'] = 'clerk';
                                        $message['data']['clerk'] = $data['from']['key'];
                                    }
                                    $server->push((int)$fd, json_encode($message));
                                }
                            }
                        }

                    }
                }
                else
                {
                    //如果对方离线了，先存放在离线消息队列中
                    if(isset($data['to']['key']) &&  $data['to']['key'] != '') {
                        $this->saveOfflineMessage($server, $data['to']['key'], $data['from']);
                    }
                }
                $this->saveMessage($server, $data['from']['key'], $data['to']['key'], $data['from'],$data['fd']);
                //保存消息
                break;
            case 'status'://修改在线状态
//                $clerkList = Staff::find(['conditions'=>['status >=' => '0'],'columns'=>['id']]);
//                foreach ($clerkList as $clerkId){
//                    $this->notifyStatus($server,$clerkId['id'].":clerk", $data['status']);
//                }
                $this->notifyStatus($server, $data['key'], $data['status']);
                break;
            case 'offlineMessage':
                $this->sendOfflineMessage($server, $data['fd'], $data['key']);
                break;
        }
    }
    /**
     * 完成事件回调事件
     *
     * @param $task_id 	是任务的ID
     * @param $data	参数传递
     * @return void
     */
    public function onFinish($serv, $task_id, $data)
    {

    }
    /**
     * 调用外部方法
     *
     * @param $method 方法
     * @param  $param array 携带的参数
     * @return  string 返回获取的内容
     */
    public function callFunction($method, $param)
    {
        $http = curl_init();
        curl_setopt($http, CURLOPT_URL, self::API_URL . $method);
        curl_setopt($http, CURLOPT_HEADER, 0);
        curl_setopt($http, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($http, CURLOPT_POST, 1);
        curl_setopt($http, CURLOPT_POSTFIELDS, $param);
        $result = curl_exec($http); //运行curl
        curl_close($http);
        return $result;
    }
    /**
     * 保存消息
     *
     * @param $fromKey 发送人key
     * @param $toKey 接收人key
     * @param  $data 新消息
     * @return void
     */
    public function saveMessage($server, $fromKey, $toKey, $data,$fd)
    {
        $array = [];
        if($data['tag'] != 'clerk') {
            if (empty($toKey)) {
                $chatKey = $fromKey;
                if ($message = $server->redis->hGet(self::WAIT_MESSAGE_QUEUE, $chatKey)) {
                    $array = json_decode($message);
                }
                $array[] = $data;
                if (count($array) == 1) {
                    $csr_session = CsrSession::findFirst([
                        'conditions' => ['from_key' => $fromKey]
                    ]);
                    if (!$csr_session) {
                        $csr_session = new CsrSession();
                        $csr_session->visited_num = 0;
                    }
                    $level = UserRole::findFirst([
                        'joins' => [['model' => 'Role', 'on' => "UserRole.role_id = Role.id"]],
                        'conditions' => 'UserRole.user_id = :user_id: AND UserRole.started < :time: AND UserRole.ended > :time:',
                        'bind' => ['user_id' => explode(':', $fromKey)[0],'time' => date('Y-m-d H:i:s')],
                        'columns' => ['Role.name'],
                    ]);
                    if (is_numeric(explode(':', $fromKey)[0])) {
                        $user = User::findFirstById(explode(':', $fromKey)[0]);
                    }
                    $session_data = array(
                        'from_key' => $fromKey,
                        'visited_num' => $csr_session->visited_num + 1,
                        'rolename' => isset($level['name']) ? $level['name'] : '普通注册用户',
                        'updated' => time(),
                        'created' => time(),
                        'username' => isset($user->username) ? $user->username : null,
                        'nickname' => isset($user->nickname) ? $user->nickname : null,
                        'avatar' => isset($user->avatar) ? $user->avatar : null,
                    );
                    $csr_session->save($session_data);
                }
                if (isset($data['message']) && $data['message'] != '') {
                    $server->redis->hSet(self::WAIT_MESSAGE_QUEUE, $chatKey, json_encode($array));
//                    $data = ['chatkey' => $chatKey, 'message' =>  json_encode($array),'created' => time()];
//                    $csrmessage = new CsrMessage();
//                    $csrmessage->save($data);
                }


            } else {
                $chatKey = '';
                $findKey = '';
                if (preg_match('/' . self::CLERK_QUEUE . '/', $toKey)) {
                    $chatKey = $fromKey . '|' . $toKey;
                    $server->redis->hIncrBy(self::MESSAGE_STATISTICS, $chatKey, 1);
                    $findKey = $fromKey;
                } else {
                    $chatKey = $toKey . '|' . $fromKey;
                    $findKey = $toKey;
                }
                if (preg_match('/' . self::VISITOR_QUEUE . '/', $toKey) && $data['message'] != '' && !isset($data['tag'])) {
                    $server->redis->hDel(self::MESSAGE_STATISTICS, $chatKey);
                }
                if ($message = $server->redis->hGet(self::MESSAGE_QUEUE, $chatKey)) {
                    $array = json_decode($message);
                }
                $csr_session = CsrSession::findFirst(['from_key' => $findKey]);
                if ($waitmessage = $server->redis->hGet(self::WAIT_MESSAGE_QUEUE, $toKey)) {
                    if ($csr_session) {
                        $csr_session->chatKey = $chatKey;
                        if ($csr_session->lastId != explode(':', $fromKey)[0]) {
                            $server->redis->hDel(self::MESSAGE_STATISTICS, $fromKey . '|' . $csr_session->lastId . ":clerk");
                        }
                        $csr_session->lastId = explode(':', $fromKey)[0];
                    }
                    $array = array_merge($array, json_decode($waitmessage));
                    $server->redis->hDel(self::WAIT_MESSAGE_QUEUE, $toKey);
                    $server->redis->hIncrBy(self::MESSAGE_STATISTICS, $chatKey, 1);
                    if($csr_session){
                        $csr_session->updated = time();
                        $csr_session->save();
                        $server->push($fd, "ok");
                    }
                }else{
                    if($csr_session){
                        $csr_session->updated = time();
                        $csr_session->save();
                    }
                }

                if (isset($data['message']) && $data['message'] != '') {

                    $array[] = $data;
                }
                $server->redis->hSet(self::MESSAGE_QUEUE, $chatKey, json_encode($array));
            }
        }

    }
    /**
     * 保存离线消息
     *
     * @param $key 接收者
     * @param  $data 新离线消息
     * @return void
     */
    public function saveOfflineMessage($server, $key, $data)
    {
        $array = [];
        //处理有多条消息，则追加在后面
        if ($offlineMessage = $server->redis->hGet(self::OFFLINE_QUEUE, $key))
        {
            $array = json_decode($offlineMessage);
        }
        $array[] = $data;
        if(!$server->redis->exists(self::OFFLINE_QUEUE)) {
            $server->redis->hSet(self::OFFLINE_QUEUE, $key, nl2br(json_encode($array)));
            $server->redis->expire(self::OFFLINE_QUEUE, 2592000);
        }else{
            $server->redis->hSet(self::OFFLINE_QUEUE, $key, nl2br(json_encode($array)));
        }
    }
    /**
     * 发送离线消息
     *
     * @param  $server swoole_websocket_server
     * @param $key 接收者
     * @return void
     */
    public function sendOfflineMessage($server, $fd, $key)
    {
        //如果发现存在离线消息，推送之
        if ($offlineMessage = $server->redis->hGet(self::OFFLINE_QUEUE, $key))
        {
            $queue = json_decode($offlineMessage);
            //至少要存在一条，才发送
            if (isset($queue[0]))
            {
                $message = ['emit' => 'offlineMessage', 'data' => $queue];
                if ($server->push($fd, json_encode($message)))
                {
                    //删除离线消息
                    $server->redis->hDel(self::OFFLINE_QUEUE, $key);
                }
            }
        }
    }
    /**
     * 实现客服上下线通知 广播所有客户端
     * 注意$server->connections会话连接池，需要安装pcre组件的支持，并重新编译swoole才有值
     * 用户会话关闭时保存聊天记录到数据库
     *
     * @param  $server swoole_websocket_server
     * @param $key 上线的客服KEY
     * @param $status 上线的状态 offline online
     * @return void
     */
    public function notifyStatus($server, $key, $status = 'online')
    {
        if ($server->connections)
        {
//            $data = ['emit' => 'status', 'id' => (int) $key, 'status' => $status];
//            foreach ($server->connections as $fd)
//            {
//                $server->push($fd, json_encode($data));
//            }
            if ($status == 'offline')
            {
                $list = $server->redis->hGetAll(self::MESSAGE_QUEUE);
                if (is_array($list)) {
                    foreach ($list as $key => $value) {
                        $data = ['chatkey' => $key, 'message' => $value, 'created' => time()];
                        $csrmessage = new CsrMessage();
                        if ($csrmessage->save($data)) {
                            $server->redis->hDel(self::MESSAGE_QUEUE, $key);
                        };
                    }
                }


//                $value = $server->redis->get($key);
//                //字符串型，表明至少曾经勾搭过一个用户
//                if (is_string($value))
//                {
//                    //$key=>$value
//                    //形式如：888:clerk=>$fd|1:clerk|2:vicistor|3:visitor……
//                    $users = explode('|', $value);
//                    array_shift($users);
//                    $message = '';
//                    foreach ($users as $toKey)
//                    {
//                        $chatKey = '';
//                        if(preg_match('/'.self::CLERK_QUEUE.'/',$toKey)){
//                            $chatKey = $key . '|' . $toKey;
//                        }else{
//                            $chatKey = $toKey . '|' . $key;
//                        }
//                        if ($message = $server->redis->hGet(self::MESSAGE_QUEUE, $chatKey))
//                        {
//                            $data = ['chatkey' => $chatKey, 'message' => $message,'created' => time()];
//                            $csrmessage = new CsrMessage();
//                            if($csrmessage->save($data)){
//                                $server->redis->hDel(self::MESSAGE_QUEUE, $chatKey);
//                            };
//                        }
//                    }
//                }
//                $server->redis->del($key);
            }
        }
    }
}



$socket = new CustomerserviceTask();
