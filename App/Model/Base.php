<?php
namespace App\Model;
/**
 * Created by PhpStorm.
 * User: zouxiansheng
 * Date: 18/11/22
 * Time: 上午12:31
 * Mysql索引降维   先跟精确条件再跟区间条件   例如 用户在10月消费记录     where user=xx and ctime>
 */

use EasySwoole\Component\Pool\PoolManager;
use App\Utility\Pool\MysqlPool;
use App\Utility\Pool\MysqlObject;
use EasySwoole\EasySwoole\Config;

class Base
{

//    public $db;
    static $_db_instance; //存储对象

    function __construct()
    {

        if(self::$_db_instance instanceof MysqlObject){

            self::$_db_instance->resetDbStatus();

        }else{

            $timeout=Config::getInstance()->getConf('MYSQL.POOL_TIME_OUT');
            $mysqlObject = PoolManager::getInstance()->getPool(MysqlPool::class)->getObj($timeout);

            if ($mysqlObject instanceof MysqlObject) {
                self::$_db_instance = $mysqlObject;
            } else {
                throw new \Exception('mysql pool is empty');
            }
        }

    }

    public function __get($name)
    {
        $getter = 'get' . $name;
        if (method_exists($this, $getter)) {
            return $this->$getter();
        }
    }

    //防止克隆
    private function __clone(){}

    //获取数据库操作对象
    public function getDb() {
        return self::$_db_instance;
    }

    function __destruct()
    {
        // TODO: Implement __destruct() method.
        if ($this->db instanceof MysqlObject) {
            PoolManager::getInstance()->getPool(MysqlPool::class)->recycleObj($this->db);
            $this->db=null;
        }
    }

    /**
     * @param $string
     * 转义过滤字符串查询  安全过滤
     * 例如： ' and 1=1=>\' and 1=1
     * real_escape_string  2.x版本
     * mysql_real_escape_string
     */

    /**
     * 打印sql语句
     */
    public function getLastQuery(){
        return $this->db->getLastQuery();
    }

    /**
     * @param $data
     * @return bool
     * 添加记录 返回插入id
     */
    public function add($tableName,$data,$OneFlag=1){

        if(empty($data) || !is_array($data)){
            return false;
        }
        if($OneFlag){//一条  ['key'=>value]
            return $this->db->insert($tableName,$data);
        }else{//插入多条记录
            /*$data=[
                ["admin", "John", "Doe"],
                ["other", "Another", "User"]
            ];
            $keys=["login", "firstName", "lastName"];*/
            $keys = array_keys($data[0]);
            $map=[];
            foreach($data as $key=>$val){
                $map[$key] = array_values($val);
            }

            $ids=$this->db->insertMulti($tableName, $data, $keys);
            if(!$ids) {
//                echo $this->db->getLastError();
                return false;
            } else {
                return true;
//            implode(', ', $ids)
            }
        }

    }

    /**
     * @param $table
     * @param $data
     * $data ['firstName' => 'Bobby','lastName' => 'Tables']
     * 修改记录
     * update($table_name, ['num'=>$db->inc(3)]);  例如 num=num+3   dec(3)  num=num-3
     */
    public function update($tableName,$data,$where=[]){

        if(!empty($where)){
            $this->db=$this->whereArr($where);
        }

        $result=$this->db->update ($tableName, $data);
        if ($result) {
            return $this->db->getAffectRows();
        }else {
//            echo 'update failed: ' . $this->db->getLastError ();
            return false;
        }
    }

    /**
     * @param $table
     * @return bool
     * 删除
     */
    public function del($tableName,$where=[]){

        if(!empty($where)){
            $this->db=$this->whereArr($where);
        }

        if($this->db->delete($tableName)){
            return true;
        }else{
            return false;
        }

    }

    /*
     * count() max() min() sum() avg()
     * has($tableName) 判断该查询条件下是否存在数据
     * getColumn($tableName, $column, $limit = 1)  使用getColumn()获取某一列的数据
     * getValue($tableName, $column, $limit = 1)   使用getValue()获取某个字段的值
    */
    public function get($table_name,$where=[],$field='*',$limit=[],$order=[],$group=[],$LeftJoin=[]){

        if(is_array($field)){
            $field=implode(',' , $field);
        }

        $this->whereArr($where);
        if(!empty($LeftJoin)){
            $this->LeftJoin($LeftJoin);
        }
        if(!empty($order)){
            $this->order($order);
        }
        if(!empty($group)){
            $this->group($group);
        }
        if(!empty($limit)) {
            $limit = $this->getLimit ($limit[0], $limit[1]);
        }else{
            $limit=null;//全部信息
        }
        $data = $this->db->get($table_name,$limit,$field);
        return $data;
    }

    //获取一条记录
    /*UserModel::$table.' u',
    [
    'u.`id`'=>4,
    'u.`role`'=>[1,4],
    '(u.`uid` > ? and u.`phone` like ? )'=>[0,'188%'],
    ],
    $filed,
    ['u.id'=>1,'u.uid'=>0],
    ['u.id','u.uid'],
    [
    UserServiceInfoModel::$table.' uinfo'=>'uinfo.uid=u.uid and uinfo.uid>0',
    UserTeacherModel::$table.' t'=>'t.uid=u.uid'
    ]*/
    public function getOne($table_name,$where=[],$field='*',$order=[],$group=[],$LeftJoin=[]){
         if(is_array($field)){
             $field=implode(',' , $field);
         }
         $this->whereArr($where);
         if(!empty($LeftJoin)){
             $this->LeftJoin($LeftJoin);
         }
         if(!empty($order)){
             $this->order($order);
         }
         if(!empty($group)){
             $this->group($group);
         }
         $data=$this->db->getOne($table_name,$field);
         return $data;
    }

    //表关联
    public function LeftJoin($JoinWhere=[]){
        if(!empty($JoinWhere)){
            foreach($JoinWhere as $key=>$val){
                $this->db->join($key,$val,'LEFT');
            }
        }
    }

    /**
     * 添加一个WHERE条件
     * @param array $where
     * $where=[key=>value] [key=>[1,2,3]]
     * @return MysqlObject|mixed|null
     *  [
            'u.`id`'=>4,
            'u.`role`'=>[1,4,5],
            '(u.`uid` > ? or u.`phone` like ? )'=>[0,'188%'],
        ]
     */

    //where('name',666,'=','and')->where('id',1,'>','or')

    public function whereArr($where=[]){

        if(!empty($where) && is_array($where)){
            foreach ($where as $key=>$val){

                if(is_array($val)){
                    if(strpos($key,'?') !== false){
                        $this->db->where($key,$val);
                    }else {
                        $this->db->whereIn ($key, $val); // 字段名 in  []
                    }
                }else{
                    $this->db->where($key,$val);
                }

            }
        }
        return $this->db;
    }

    //排序 ['id'=>0,'name'=>1]  $this->db->orderBy('id','ASC')->orderBy('name','DESC');
    public function order($OrderArr=[]){

        if(is_array($OrderArr) && !empty($OrderArr)){
            foreach($OrderArr as $key=>$val) {
                if($val==1){
                    $str='DESC';
                }else{
                    $str='ASC';
                }
                $this->db->orderBy($key,$str);
            }
        }
    }

    //分组  ['type','name']  $this->db->groupBy('type')->groupBy('name')
    public function group($GroupArr){

        if(!empty($GroupArr)) {
            foreach ( $GroupArr as $key => $val ) {
                $this->db->groupBy($val);
            }
        }
    }

    /**
     * @param $sql  SELECT id, firstName, lastName FROM users WHERE id = ? AND login = ? limit 0,5
     * @param $params [1,'admin']
     * 执行mysql
     */
    public function query($sql,$params,$limit=[]){ //[page(1),size(5)]

        if(empty($limit)){
            $sql.=' limit 1';
        }else{
            $offset = $limit[1] * ($limit[0] - 1);
            $sql.= " limit $offset, $limit[1]";
        }

        $result = $this->db->rawQuery($sql, $params);

        return $result;

    }

    /**
     * @param $page
     * @param $size
     * 获取分页参数
     */
    public function getLimit($page,$size){

        $offset = $size * ($page - 1);
        return [$offset, $size];

    }

    //事务处理
    public function setAffair()
    {
        $this->db->startTransaction();
        $this->db->commit();
        $this->db->rollback();
    }

}