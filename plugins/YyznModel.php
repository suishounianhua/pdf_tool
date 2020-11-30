<?php
namespace plugins;
/**
 * Created by PhpStorm.
 * User: pangxb
 * Date: 17/3/5
 * Time: 下午6:01
 */

use Phalcon\Events\Event;
use Phalcon\Events\Manager as EventsManager;
use Phalcon\Di;
use Phalcon\Mvc\Model\Resultset;
use Phalcon\Mvc\Model\Transaction\Manager as TxManager;

class YyznModel extends \Phalcon\Mvc\Model
{
    public $accessCondition;

    public static $prefix;    //表前缀
    public static $suffix;    //表后缀

    public static $TABLE_NAME;

    public $config = [];
    public $plugin_config = [];

    public function setConfig(string $string){
        try {
            $arr = explode('\\', $string);
            $configPATH = APP_PATH . "/plugins/".$arr[1]."/config/config.php";
            if(!file_exists($configPATH)){
                 echo "请确定配置文件是否合法";exit;
            }
            if(empty($this->plugin_config)){
                $this->plugin_config = include $configPATH;
            }
            $config = $this->plugin_config;
        } catch (\Exception $e) {
            
            echo "请确定配置文件是否合法";exit;
        }
        
        // var_dump($config);exit;
        self::$prefix = $config['databases']['prefix'];

        $di = $this->di;
        $this->di->set('pluginsdb', function () use ($config,$di) {
            try {
                $dbConfig = $config['databases'];
                $adapter = $dbConfig['adapter'];
                unset($dbConfig['adapter']);
                unset($dbConfig['prefix']);
                $class = 'Phalcon\Db\Adapter\Pdo\\' . $adapter;
                $dbInstance = @new $class($dbConfig);
                $dbInstance->setEventsManager($di->getShared('eventsManager'));
            } catch (\Exception $e) {
                echo "请确定配置文件是否合法";exit;
            }
            return $dbInstance;
        });
        $this->setConnectionService('pluginsdb');
    }

    public function initialize(){

        if ($this->getDI()->get('config')->productionDb == 0) {
            self::$prefix = $this->getDI()->get('config')->databaseTest->prefix;
        } else {
            self::$prefix = $this->getDI()->get('config')->databaseMaster->prefix;
        }
        $this->useDynamicUpdate(true);
        $this->setReadConnectionService("dbSlave");
        $this->setWriteConnectionService("dbMaster");
    }

    public function fields(){
        return $this->getModelsMetaData()->getAttributes($this);
    }

    public function getSource() {
//        return self::$prefix.self::$TABLE_NAME;
        return self::$prefix.static::$TABLE_NAME; //必须使用static::$TABLE_NAME才能使用到子类中的定义值，不能用self
    }

    /**
     * @param $field  自增的字段
     * @param $options  条件，需包含conditions与bind
     * @param int $step 自增步长，默认为1
     * @return boolean 返回true或者false
     */
    public static function increase($field,$options,$step = 1) {
        $options = self::getCondition($options);
        $phql = "UPDATE " . get_called_class(). " SET $field = $field + $step WHERE ".$options['conditions'];
        return Di::getDefault()->getModelsManager() -> executeQuery($phql, $options['bind']);
    }
    public static function decrease($field,$options,$step = 1) {
        $options = self::getCondition($options);
        $phql = "UPDATE " . get_called_class(). " SET $field = $field - $step WHERE ".$options['conditions'];
        return Di::getDefault()->getModelsManager() -> executeQuery($phql, $options['bind']);
    }

    public function schema(){
        $arr = [];
        $fields = Di::getDefault()->get('dbSlave')->describeColumns($this->getSource());
        foreach ($fields as $field) {
            $name = $field->getName();
            $arr[$name] = array(
                'name' => $name,
                'size' => $field->getSize(),
                'type' => $field->getType(),
            );
        }
        return $arr;
    }


    public $contentPath = '/{source}/{id}';

    private function getContentFilePath() {
        $path = str_replace('{id}',$this->id,$this->contentPath);
        $path = str_replace('{source}',self::$TABLE_NAME,$path);
        if( strpos($path,'{') !== false ) {
            if( !empty($this->creator) ) {
                $path = str_replace('{creator}',$this->creator,$path);
            }
            if( !empty($this->user_id) ) {
                $path = str_replace('{user_id}',$this->user_id,$path);
            }
            if( !empty($this->app_id) ) {
                $path = str_replace('{app_id}',$this->app_id,$path);
            }
        }
        return $path;
    }

    /**
     * 经测试findFirst会调用afterFetch，但find方法不会
     */
    public function afterFetch() {
        if(isset($this->serialize_info)) {
            $this->serialize_info = json_decode($this->serialize_info,true);
        }
        $classname = get_class($this);
        if( isset(Di::getDefault()->get('config')->{$classname}) && Di::getDefault()->get('config')->{$classname}->content_to_oss ) {
            try{
                if( array_key_exists('content',$this->schema() ) ) { // 只有一条，或者选取了内容字段时
                    $object_name = $this->getContentFilePath();
                    if( $object_name ) {
                        $content = StorageCloud::getFromAliOss($object_name);
                        if( $content ) {
                            $this->content = $content; // 有内容时，才使用值，否则使用数据库中的值。兼容数据库中已有的数据
                        }
                    }
                }
            }
            catch(Exception $e){
                return;
            }
        }
    }

    public static function deleteAll($conditions=array(),$foreach = false){
        if($foreach == false) {
            $ret = self::getCondition($conditions);
            if (is_array($ret) && $ret['conditions']) {
                $conditions = $ret['conditions'];
                $bind = $ret['bind'];
            } else {
                $bind = array();
                $conditions = $ret;
            }
            $phql = "DELETE FROM " . get_called_class() . " WHERE " . $conditions;
            return Di::getDefault()->getModelsManager()->executeQuery($phql, $bind);
        }else{
            $items = self::find($conditions);
            $items->delete();
        }
    }

    /**
     * @param array $data
     * @param array $conditions
     * @param bool $foreach 默认为false，批量删除，不触发事件。为true时循环逐个编辑，触发aftersave事件
     */
    public static function updateAll( $data=array(), $conditions=array(),$foreach = false ){

        if($foreach == false) {
            $ret = self::getCondition($conditions);
            if(is_array($ret) && $ret['conditions']) {
                $conditions = $ret['conditions'];
                $bind = $ret['bind'];
            }
            else{
                $bind = array();
                $conditions = $ret;
            }

            $upsql = $whsql = array(); $bk = 0;
            foreach($data as $k => $v) {
                $bk ++;
                if(is_numeric($k)) {
                    $upsql[] = $v;
                }
                else{
                    $upsql[] = "$k = :val_$bk:";
                    $bind['val_'.$bk] = $v;
                }
            }
            $phql = "UPDATE ".get_called_class()." SET  ".implode(',',$upsql). " WHERE ".$conditions;
            return Di::getDefault()->getModelsManager()->executeQuery($phql,$bind);
        }
        else{
            $items = self::find($conditions);
            $attributes = array();
            foreach($items as $item) {
                if( is_array($data) && count($data) > 0 ){
                    if(empty($attributes)) {
                        $attributes = $item->getModelsMetaData()->getAttributes($item);
                    }
                    $item->skipAttributesOnUpdate(array_diff($attributes, array_keys($data)));
                }
                foreach($data as $k => $v) {
                    $item->{$k} = $v;
                }
                $item->save(); // $item->update();
            }
            return true;
        }
    }

    public function update( $data=null, $whiteList=null){
        if(is_array($data) && count($data) > 0){
            $attributes = $this->getModelsMetaData()->getAttributes($this);
            $this->skipAttributesOnUpdate(array_diff($attributes, array_keys($data)));
        }
        return parent::update($data, $whiteList);
    }

    public static function simpleWhere($where, $debug = false)
    {
        if ($debug) {
            Di::getDefault()->get('logger')->debug(__FUNCTION__ . 'creating conditions');
        }
        $result = [];
        $result['conditions'] = '';
        $result['bind'] = [];
        foreach ($where as $k => $v) {
            if ($result['conditions'] !== '') {
                $result['conditions'] = $result['conditions'] . ' AND ';
            }
            $result['conditions'] = $result['conditions'] . '(' . $k . ' = :' . $k . ':)';
            $result['bind'][$k] = $v;
        }
        if ($debug) {
            Di::getDefault()->get('logger')->debug(__FUNCTION__ . json_encode($result));
        }
        return $result;
    }

    public static function likeWhereOr($where, $debug = false)
    {
        if ($debug) {
            Di::getDefault()->get('logger')->debug(__FUNCTION__ . 'creating conditions');
        }
        $result = [];
        $result['conditions'] = '';
        $result['bind'] = [];
        foreach ($where as $k => $v) {
            if ($result['conditions'] !== '') {
                $result['conditions'] = $result['conditions'] . ' OR ';
            }
            $result['conditions'] = $result['conditions'] . '(' . $k . ' like :' . $k . ':)';
            $result['bind'][$k] = "%{$v}%";
        }
        if ($debug) {
            Di::getDefault()->get('logger')->debug(__FUNCTION__ . json_encode($result));
        }
        return $result;
    }

    public static function getObjFromArray($field, $data)
    {
        if (array_key_exists($field, $data)) {
            $target = $data[$field];
            if (is_array($target) || is_object($target)) {
                return $target;
            } else {
                return json_decode($target, true);
            }
        } else {
            return false;
        }
    }

    public static function modelResult($code, $data, $info)
    {
        return [
            'code' => $code,
            'data' => $data,
            'info' => $info
        ];
    }

    /**防范xss攻击
     * @param $model
     * @param $data
     * @return bool
     */
    public static function safeSave($model,$data,&$transaction = null){
        if($transaction){
            $model->setTransaction($transaction);
        }
        foreach($data as $key => $val){
            $model->$key = self::removeXSS($val);
        }
        if($model->save()) {
            return true;
        }else{
            return false;
        }
    }

    //移除html携带的xss代码攻击
    public static function removeXSS($val)
    {
        static $obj = null;
        if ($obj === null) {
            require_once  APP_PATH.'/library/ezyang/htmlpurifier/library/HTMLPurifier.autoload.php';
            $obj = new HTMLPurifier();
        }
        // 返回过滤后的数据
        return $obj->purify($val);
    }

    /**
     * @param $modelName 模型名字
     * @param $relatedid   对应的模型id
     * @param $tags   标签id数组
     * @param $transaction   事务
     * @return bool
     */
    public static function editTag($modelName,$relatedid,$tags,&$transaction){
        $tableTag = TagRelated::find([
            'conditions' => '(relatedmodel = :relatedmodel: AND relatedid = :relatedid:)',
            'bind' => ['relatedmodel' => $modelName,'relatedid' => $relatedid],
            "columns" => ['tag_id'],
        ])->toArray();
        foreach ($tableTag as $val){
            if(!in_array($val['tag_id'],$tags)){
                $delTag = TagRelated::findFirst([
                    'conditions' => '(tag_id = :tag_id: AND relatedid = :relatedid:) AND relatedmodel = :relatedmodel:',
                    'bind' => ['tag_id' => $val['tag_id'],'relatedid' => $relatedid,'relatedmodel' => $modelName],
                ]);
                if($delTag) {
                    $delTag->setTransaction($transaction);
                    $delTag->delete();
                }
            }
        }
        foreach ($tags as $tagid){
            $relatedTag = TagRelated::findFirst([
                'conditions' => '(tag_id = :tag_id: AND relatedid = :relatedid:) AND relatedmodel = :relatedmodel:',
                'bind' => ['tag_id' => $tagid,'relatedid' => $relatedid,'relatedmodel' => $modelName],
            ]);
            if($relatedTag) {
                $relatedTag->setTransaction($transaction);
                if ($relatedTag->deleted == 1) {
                    $relatedTag->deleted = 0;
                    $relatedTag->save();
                }
            }else {
                $relatedTag = new TagRelated();
                $relatedTag->setTransaction($transaction);
                $data = array(
                    'created' => date('Y-m-d H:i:s'),
                    'updated' => date('Y-m-d H:i:s'),
                    'tag_id' => $tagid,
                    'relatedid' => $relatedid,
                    'relatedmodel' => $modelName,
                );
                if (!self::safeSave($relatedTag, $data, $transaction)) {
                    $transaction->rollback();
                    return false;
                }
            }
        }
        return true;
    }
    public static function count($parameters = null){
        if( is_array($parameters) ) {
            if( isset($parameters['conditions']) ) {
                $ret = self::getCondition($parameters['conditions']);
                if(is_array($ret) && $ret['conditions']) {
                    $parameters['conditions'] = $ret['conditions'];
                    $parameters['bind'] = $ret['bind'];
                }
            }
        }
        return parent::count($parameters);
    }
    public static function findFirst($parameters = null)
    {
        if( is_array($parameters) ) {
            if( isset($parameters['conditions']) ) {
                $ret = self::getCondition($parameters['conditions']);
                if(is_array($ret) && $ret['conditions']) {
                    $parameters['conditions'] = $ret['conditions'];
                    $parameters['bind'] = $ret['bind'];
                }
            }
            elseif( !isset($parameters['bind'])
                && !isset($parameters['for_update']) && !isset($parameters['order']) && !isset($parameters['columns'])
                && !isset($parameters['limit'])&& !isset($parameters['joins']) && !isset($parameters['group']) ) {
                $parameters = self::getCondition($parameters);
            }
            if(isset($parameters['joins']) && !empty($parameters['joins'])) {
                $builder = Di::getDefault()->getModelsManager()->createBuilder();
                $builder->from(get_called_class());
                if(isset($parameters['conditions']) || isset($parameters['bind'])){
                    $builder->where($parameters['conditions'],$parameters['bind']);
                }
                if(isset($parameters['order'])) {
                    $builder->orderBy($parameters['order']);
                }
                if(isset($parameters['columns'])) {
                    $builder->columns($parameters['columns']);
                }
                if(isset($parameters['group'])) {
                    $builder->groupBy($parameters['group']);
                }
                if(isset($parameters['having'])) {
                    $builder->having($parameters['having']);
                }
                if($parameters['limit']) {
                    if( $parameters['page'] > 1 ) {
                        $builder->limit($parameters['limit'],($parameters['page'] - 1)*$parameters['limit']);
                    }
                    else{
                        $builder->limit($parameters['limit']);
                    }
                }
                foreach ($parameters['joins'] as $join) {
                    if(isset($join['join']) && $join['join'] == 'left'){
                        $builder->leftjoin($join['model'],$join['on'],$join['model']);
                    }elseif (isset($join['join']) && $join['join'] == 'right'){
                        $builder->rightjoin($join['model'],$join['on'],$join['model']);
                    }else {
                        $builder->join($join['model'], $join['on'], $join['model']);
                    }
                }

                if($parameters['cache']) {
                    if(empty($parameters['cache']['service'])) {
                        $parameters['cache']['service'] = 'redisCache';
                    }
                    return $result =  $builder->getQuery()->cache($parameters['cache'])->getSingleResult();
                }
                else{
                    return $result =  $builder->getQuery()->getSingleResult();
                }
            }
        }
        return parent::findFirst($parameters);
    }

    /**
     * find的结果，在foreach使用时，才会触发afterFetch方法。 （若在toArray后再循环不触发）
     * 即在创建Model对象的时候，调用afterFetch。 结果集toArray时，没有创建对象
     * @param null $parameters
     */
    public static function find($parameters = null)
    {
        if( is_array($parameters) ) {
            if( isset($parameters['conditions']) ) {
                $ret = self::getCondition($parameters['conditions']);
                if(is_array($ret) && $ret['conditions']) {
                    $parameters['conditions'] = $ret['conditions'];
                    $parameters['bind'] = $ret['bind'];
                }
            }
            elseif( !isset($parameters['bind'])
                && !isset($parameters['for_update']) && !isset($parameters['order']) && !isset($parameters['columns'])
                && !isset($parameters['limit'])&& !isset($parameters['joins']) && !isset($parameters['group']) ) {
                $parameters = self::getCondition($parameters);
            }

            if(isset($parameters['joins']) && !empty($parameters['joins'])) {
                $builder = Di::getDefault()->getModelsManager()->createBuilder();
                $builder->from(get_called_class());
                if(isset($parameters['conditions']) || isset($parameters['bind'])){
                    $builder->where($parameters['conditions'],$parameters['bind']);
                }
                if(isset($parameters['order'])) {
                    $builder->orderBy($parameters['order']);
                }
                if(isset($parameters['columns'])) {
                    $builder->columns($parameters['columns']);
                }
                if(isset($parameters['group'])) {
                    $builder->groupBy($parameters['group']);
                }
                if(isset($parameters['having'])) {
                    $builder->having($parameters['having']);
                }
                if($parameters['limit']) {
                    if( $parameters['page'] > 1 ) {
                        $builder->limit($parameters['limit'],($parameters['page'] - 1)*$parameters['limit']);
                    }
                    else{
                        $builder->limit($parameters['limit']);
                    }
                }
                foreach ($parameters['joins'] as $join) {
                    if(isset($join['join']) && $join['join'] == 'left'){
                        $builder->leftjoin($join['model'],$join['on'],$join['model']);
                    }elseif (isset($join['join']) && $join['join'] == 'right'){
                        $builder->rightjoin($join['model'],$join['on'],$join['model']);
                    }else {
                        $builder->join($join['model'], $join['on'], $join['model']);
                    }
                }

                if($parameters['cache']) {
                    if(empty($parameters['cache']['service'])) {
                        $parameters['cache']['service'] = 'redisCache';
                    }
                    $result =  $builder->getQuery()->cache($parameters['cache'])->execute();
                    return $result;
                }
                else{
                    return $result =  $builder->getQuery()->execute();
                }
            }
            else{
                if( isset($parameters['limit']) && isset($parameters['page']) ) {
                    $parameters['limit'] = array('number' => $parameters['limit'],'offset' => ($parameters['page'] - 1)*$parameters['limit'] );
                    unset($parameters['page']);
                }
            }
            if(!isset($parameters['hydration'])) { //如果没定义hydration时，默认使用HYDRATE_RECORDS触发，afterFetch
                $parameters['hydration'] = Resultset::HYDRATE_RECORDS; //Resultset::HYDRATE_RECORDS;
            }
        }

        return parent::find($parameters);
    }

    /**
    * @title 数组转find 查询条件
    * @author  luodiao
    * @param condition   array 
    * @           大于  'field >' => $value
    * @           小于  'field <' => $value
    * @           大于等于  'field >=' => $value
    * @           不等于  'field <>' => $value
    * @           小于等于  'field <=' => $value
    * @           like  'field like' => $value
    */
    public static function getCondition($data){

        if( is_array($data) && !isset($data['conditions']) && !isset($data['bind'])
        && !isset($data['for_update']) && !isset($data['order']) && !isset($data['columns'])
        && !isset($data['limit']) && !isset($data['group']) ){
            $conditionArr = array();
            $bindArr = array();
            static $i = 0; //需要为static， OR条件时有递归，递归中也一起递增
            foreach ($data as $key => $value) {
                if( is_array($value) ) { //数组条件
                    if($key == 'or' || $key == 'OR') {  //或者条件
                        $ors = array();
                        foreach($value as $ink => $inv) {
                            if(is_array($inv)) {
                                if(is_numeric($ink) ) { // 数字序号，作为递归
                                    $inner = self::getCondition($inv);
                                    $ors[] = '('.$inner['conditions'].')';
                                    if(!empty($inner['bind'])) { // 合并绑定值
                                        $bindArr = array_merge($bindArr,$inner['bind']);
                                    }
                                }
                                else { // 非数字序号，作为字段条件
                                    $ors[] = $ink.' in ({'.$ink.':array})';
                                    $bindArr[ $ink ]= $inv;
                                }
                            }
                            else{
                                $bk = 'key_'.$i;// 自定义绑定键名，防止含有空格的或者递归时出现重复覆盖
                                $i++;
                                if( strpos($ink,' ') !== false) {
                                    $ors[] = $ink.' :'.$bk.':';
                                    $bindArr[ $bk ]= $inv;
                                }
                                else{
                                    $ors[] = $ink.' = :'.$bk.':';
                                    $bindArr[$bk]= $inv;
                                }
                            }

                        }
                        $conditionArr[] = '('.implode(' OR ',$ors).')';
                    }
                    else{ // in 操作，值在范围内
                        $conditionArr[] = $key.' in ({'.$key.':array})';
                        $bindArr[ $key ]= $value;
                    }
                }
                else{
                    $bk = 'key_'.$i;// 自定义绑定键名，防止含有空格的或者递归时出现重复覆盖
                    $i++;
                    if( strpos($key,' ') !== false) {
                        $conditionArr[] = $key.' :'.$bk.':';
                        $bindArr[ $bk ]= $value;
                    }
                    else{
                        $conditionArr[] = $key.' = :'.$bk.':';
                        $bindArr[$bk]= $value;
                    }
                }
            }

            if( count($bindArr) > 0 ){
                $res = array();
                $res['conditions'] = implode(' AND ',$conditionArr);
                $res['bind'] = $bindArr;
                return $res;
            }else{
                return implode(' AND ',$conditionArr);
            }

        }
        else{
            return $data;
        }

    }



//    public function selectReadConnection($intermediate, $bindParams, $bindTypes)
//    {
//        var_dump($intermediate);
//    }
}