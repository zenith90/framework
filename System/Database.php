<?php
namespace System;
use ReflectionClass;
use ReflectionException;
use System\DatabaseDriver\pdo_mysql;
/**
 * PHP-QuickORM 框架数据库基础类
 * @author Rytia <rytia@outlook.com>
 * @uses 用于驱动数据库，属于对 PHP PDO 的二次封装以满足快速操作，其中 PDO 对象保存在 $this->PDOConnect 中，PDOStatement 对象保存在 $this->PDOStatement 中
 */

class Database{

    public $PDOStatement;
    public $PDOConnect;
    public $SQLStatement;
    public $table;
    public $model;
    public $select = '*';
    public $where;
    public $join;
    public $on;
    public $orderBy;


    public function __construct($table) {
        // 使用 pdo 进行处理
        $this->PDOConnect = (new pdo_mysql())->connect;
        $this->table = $table;
    }

    /**
     * 选择数据表
     * @param string $table
     * @return Database
     * @uses 用于通过数据表生成 Database 实例
     */
    public static function table($table) {
        $db = new self($table);
        return $db;
    }

    /**
     * 选择模型(静态方法)
     * @param model $modelClass
     * @return Database
     * @uses 用于通过模型生成 Database 实例
     */
    public static function model($modelClass) {
        // 使用反射获取数据表名
        try{
            $reflect = new ReflectionClass($modelClass);
        }
        catch (ReflectionException $e) {
            // 提示错误
            trigger_error($e->getMessage(),E_USER_ERROR);
        }
        // 创建新的 Database 实例
        $db = new self($reflect->getStaticPropertyValue('table'));
        $db->model = $modelClass;
        return $db;
    }

    /**
     * 选择模型(实例方法)
     * @param model $modelClass
     * @return Database
     * @uses 为实例选择 Model
     */
    public function setModel($modelClass) {
        // 使用反射获取数据表名
        try{
            $reflect = new ReflectionClass($modelClass);
        }
        catch (ReflectionException $e) {
            // 提示错误
            trigger_error("setModel($modelClass)".$e->getMessage(),E_USER_ERROR);
        }
        // 修改当前实例的数据表和 Model
        $this->table = $reflect->getStaticPropertyValue("table");
        $this->model = $modelClass;
        return $this;
    }

    // PDO类封装

    /**
     * SQL 语句预处理并执行
     * @param string $sqlStatement
     * @param array $parameters
     * @return boolean
     * @uses 用于预处理并执行语句，请注意本方法结合了 pdo 中 prepare 和 execute 两个方法
     */
    public function prepare($sqlStatement = '', $parameters = []){
        if (!empty($sqlStatement)) {
            $this->SQLStatement = $sqlStatement;
        }
        $this->PDOStatement = $this->PDOConnect->prepare($this->SQLStatement);
        return $this->PDOStatement->execute($parameters);
    }

    /**
     * 执行 SQL 语句
     * @param string $sqlStatement
     * @return Database
     */
    public function query($sqlStatement, $parameters = []){
        $this->prepare($sqlStatement, $parameters);
        return $this;
    }

    /**
     * 执行 SQL 语句并取回第一条结果
     * @return array
     */
    public function fetch() {
        $this->prepare($this->SQLStatement);
        return $this->PDOStatement->fetch(2);
    }

    /**
     * 执行 SQL 语句并取回结果集
     * @return array
     */
    public function fetchAll() {
        $this->prepare($this->SQLStatement);
        return $this->PDOStatement->fetchAll(2);
    }

    /**
     * 开始一个新的事务
     * @return boolean
     */
    public function beginTransaction() {
        return $this->PDOConnect->beginTransaction();
    }

    /**
     * 提交事务
     * @return boolean
     */
    public function commit() {
        return $this->PDOConnect->commit();
    }

    /**
     * 回滚事务
     * @return boolean
     */
    public function rollBack() {
        return $this->PDOConnect->rollBack();
    }


    // ORM 数据库查询方法

    /**
     * 字段选择
     * @param string|array $sqlStatement
     * @return Database
     */
    public function select($sqlStatement = '*'){
        // 对传入类型进行判断
        if (is_array($sqlStatement)) {
            // 拼接数组形成 SQL 语句
            $this->select = implode(",",$sqlStatement);
        } else {
            $this->select = $sqlStatement;
        }

        $this->SQLStatement = 'SELECT '.$sqlStatement.' FROM '.$this->table;
        return $this;
    }

    /**
     * 通过数组条件检索数据表
     * @param array $sqlConditionArray
     * @return Database
     */
    public function where($sqlConditionArray = []){
        // 如果是字符串，交由 whereRaw() 处理
        if (is_string($sqlConditionArray)) {
            return $this->whereRaw($sqlConditionArray);
        } elseif (!is_array($sqlConditionArray)) {
            trigger_error("where($sqlConditionArray) - Parameter type is invalid", E_USER_ERROR);
        }

        // 判断是否第一次执行
        if(empty($this->where)) {
            // 判断 $sqlConditionArray 是否传入：加入空条件的判断使开发变得简便
            if(empty($sqlConditionArray)){
                // 未传入条件，显示全部数据
                $this->SQLStatement = 'SELECT '.$this->select.' FROM '.$this->table.' '.$this->join.' '.$this->on;
            } else {
                // 传入条件，进行 SQL 语句拼接
                foreach ($sqlConditionArray as $key => $value) {
                    if (isset($whereSQL)) {
                        $whereSQL .= " AND ".$key.'="'.addslashes($value).'"';
                    } else {
                        $whereSQL = $key.'="'.addslashes($value).'"';
                    }
                }
                $this->where = '('.$whereSQL.')';

                // 组合语句，加入 join 和 on
                $this->SQLStatement = 'SELECT '.$this->select.' FROM '.$this->table.' '.$this->join.' '.$this->on.' WHERE '.$this->where;
            }
        } else {
            // 不是第一次执行，判断 $sqlConditionArray 是否传入
            if(empty($sqlConditionArray)){
                // 未传入条件，SQL语句不做任何改动
            } else {
                // 传入条件，进行 SQL 语句拼接
                foreach ($sqlConditionArray as $key => $value) {
                    if (isset($whereSQL)) {
                        $whereSQL .= " AND ".$key.'="'.addslashes($value).'"';
                    } else {
                        $whereSQL = $key.'="'.addslashes($value).'"';
                    }
                }
                $this->where .= ' AND ('.$whereSQL.')';
                $this->SQLStatement = 'SELECT '.$this->select.' FROM '.$this->table.' '.$this->join.' '.$this->on.' WHERE '.$this->where;
            }
        }

        return $this;
    }

    /**
     * 通过 SQL 语句条件检索数据表
     * @param string $sqlConditionStatement
     * @return Database
     */
    public function whereRaw($sqlConditionStatement = ''){
        // 判断是否第一次执行
        if(empty($this->where)) {
            if (empty($sqlConditionStatement)) {
                // 未传入条件，显示全部数据
                $this->objectSQL = 'SELECT '.$this->select.' FROM ' . $this->table;
            } else {
                $this->where = '('.$sqlConditionStatement.')';
                $this->SQLStatement = 'SELECT '.$this->select.' FROM ' . $this->table .' '.$this->join.' '.$this->on.' WHERE ' . $this->where;
            }
        } else {
            // 判断 $sqlConditionArray 是否传入：加入空条件的判断使开发变得简便
            if (empty($sqlConditionStatement)) {
                // 未传入条件，SQL语句不做任何改动
            } else {
                // 传入条件，进行 SQL 语句拼接
                $this->where .= ' AND ('.$sqlConditionStatement.')';
                $this->SQLStatement = 'SELECT '.$this->select.' FROM '.$this->table.' '.$this->join.' '.$this->on.' WHERE '.$this->where;
            }
        }

        return $this;
    }

    /**
     * 通过数组条件检索数据表
     * @param array $sqlConditionArray
     * @return Database
     */
    public function orWhere($sqlConditionArray = []){
        // 如果是字符串，交由 orWhereRaw() 处理
        if (is_string($sqlConditionArray)) {
            return $this->orWhereRaw($sqlConditionArray);
        } elseif (!is_array($sqlConditionArray)) {
            trigger_error("orWhere($sqlConditionArray) - Parameter type is invalid", E_USER_ERROR);
        }

        // 判断 $sqlConditionArray 是否传入：加入空条件的判断使开发变得简便
        if(empty($sqlConditionArray)){
            // 未传入条件，SQL语句不做任何改动
        } else {
            // 传入条件，进行 SQL 语句拼接
            foreach ($sqlConditionArray as $key => $value) {
                if (isset($whereSQL)) {
                    $whereSQL .= " AND ".$key.'="'.addslashes($value).'"';
                } else {
                    $whereSQL = $key.'="'.addslashes($value).'"';
                }
            }
            $this->where .= ' OR ('.$whereSQL.')';
            $this->SQLStatement = 'SELECT '.$this->select.' FROM '.$this->table.' '.$this->join.' '.$this->on.' WHERE '.$this->where;
        }

        return $this;
    }

    /**
     * 通过 SQL 语句条件检索数据表
     * @param string $sqlConditionStatement
     * @return Database
     */
    public function orWhereRaw($sqlConditionStatement = ''){
        // 判断 $sqlConditionArray 是否传入：加入空条件的判断使开发变得简便
        if(empty($sqlConditionStatement)){
            // 未传入条件，SQL语句不做任何改动
        } else {
            $this->where .= ' OR ('.$sqlConditionStatement.')';
            // 传入条件，进行 SQL 语句拼接
            $this->SQLStatement = 'SELECT '.$this->select.' FROM '.$this->table.' '.$this->join.' '.$this->on.' WHERE '.$this->where;
        }

        return $this;
    }

    /**
     * join 语句
     * @param string $table, string
     * @param$method = inner
     * @return Database
     * @uses 用于根据两个或多个表中的列之间的关系查询数据。其中 method 可选 left, right, full, inner
     */
    public function join($table,$method = 'inner'){
        switch ($method){
            case 'left': $methodSQL = 'LEFT'; break;
            case 'right': $methodSQL = 'RIGHT'; break;
            case 'full': $methodSQL = 'FULL OUTER'; break;
            default: $methodSQL = 'INNER'; break;
        }
        $this->join = $methodSQL.' JOIN '.$table;
        $this->SQLStatement = 'SELECT '.$this->select.' FROM '.$this->table.' '.$this->join;
        return $this;
    }

    /**
     * on 语句
     * @param string $sqlConditionStatement
     * @return Database
     * @uses 根据关系查询数据表
     */
    public function on($sqlConditionStatement){
        // on 和 where 条件的差别在于 SQL 的 join 语句会生成临时表
        // 1. on 所附带的条件是在生成临时表时使用的条件
        // 2. where 所附带的条件是在临时表生成好后做进一步筛选的条件，若不为真则将该条目过滤

        $this->on = "ON ".$sqlConditionStatement;
        $this->SQLStatement = 'SELECT '.$this->select.' FROM '.$this->table.' '.$this->join.' '.$this->on;
        return $this;
    }

    /**
     * 根据字段排列结果集
     * @param string|array $field
     * @param string $method
     * @return Database
     * @uses 根据字段排列结果集, 其中 $field 可为单个字段字符串或关联数组
     */
    public function orderBy($field,$method = 'ASC'){
        if (is_array($field)){

            // 将数组拼接为 SQL 语句，传入样例
            // $array = [
            // 	'arr1' => 'DESC',
            // 	'arr2' => 'DESC',
            // 	'arr3' => 'ASC'
            // ];

            foreach ($field as $key => $value) {
                if (isset($sql)) {
                    $sql .= ', '.$key.' '.$value;
                } else {
                    $sql = $key.' '.$value;
                }
            }
            $this->orderBy = ' ORDER BY '.$sql;
        } else {
            // 直接拼接两个参数
            $this->orderBy = ' ORDER BY '.$field.' '.$method;
        }

        $this->SQLStatement .= $this->orderBy;
        return $this;
    }



    /**
     * 插入条目
     * @param array $data
     * @return boolean
     */
    public function insert($data){
        // 生成 count($data) 个 ?, 作为 SQL 语句 VALUES 占位符
        $sqlPlaceholder = "?";
        for ($i = 1; $i<count($data); $i++) {
            $sqlPlaceholder .= ',?';
        }
        // 执行语句进行插入
        $this->SQLStatement = 'INSERT INTO '.$this->table.' ('.implode(',',array_keys($data)).') VALUES ('.$sqlPlaceholder.')';

        return $this->prepare($this->SQLStatement,array_values($data));
    }

    /**
     * 更新条目
     * @param array $data
     * @return boolean
     */
    public function update($data){

        $where = $this->where;
        //   拼接 SQL 语句，形成 id=?, name=? 的形式
        foreach ($data as $key => $value) {
            if (isset($sql)) {
                $sql .= " , ".$key.'=?';
            } else {
                $sql = $key.'=?';
            }
        }
        $this->SQLStatement = 'UPDATE '.$this->table.' SET '.$sql.' WHERE '.$where;
        return $this->prepare($this->SQLStatement,array_values($data));
    }

    /**
     * 删除条目
     * @return boolean
     * @uses 用于更新当前操作的实例信息到数据库，
     */
    public function delete(){
        $where = $this->where;
        // 执行 SQL 语句删除条目
        $this->SQLStatement = 'DELETE FROM '.$this->table.' WHERE '.$where;
        return $this->prepare($this->SQLStatement);
    }

    public function count(){
        $countSQL = str_replace($select, 'COUNT('.$select.')', $this->SQLStatement);
        return $this->PDOConnect->query($countSQL)->fetch()[0];
    }

    /**
     * Database 分页
     * @param int $pageNum
     * @param boolean $furtherPageInfo
     * @return Collection
     * @uses 数据库 LIMIT 语句调用
     */
    public function paginate($pageNum, $furtherPageInfo = true){
        // 获取当前页码
        $currentPage = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $startAt = (($currentPage-1)*$pageNum);

        // 执行语句获取总行数
        $select = $this->select;
        $countSQL = str_replace($select, 'COUNT('.$select.')', $this->SQLStatement);
        $total =  $this->PDOConnect->query($countSQL)->fetch()[0];

        // 拼接 SQL 语句：select * from table limit start,pageNum
        $this->SQLStatement = $this->SQLStatement." LIMIT ".$startAt.",".$pageNum;

        // 返回集合
        return Collection::make($this->fetchAll())->format($this->model)->forPage($pageNum, $currentPage, $total, $furtherPageInfo);

    }

    /**
     * 执行 SQL 语句并将结果格式化为 Collection
     * @return Collection
     * @uses 取出数据库内容，并以 Collection 集合返回。用于将 Database 层的数据转换至 Collection
     */
    public function get(){
        // 判断 Model 是否设置
        if (is_null($this->model)) {
            trigger_error("Model is undefined", E_USER_ERROR);
        }
        return Collection::make($this->fetchAll())->format($this->model);
    }
}
