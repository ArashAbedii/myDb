<?php

//****MY DATABASE CLASS***** Github: https://github.com/ArashAbedii/myDb
namespace Database;

//config 
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'fsearch');

use PDO;
use PDOException;

class Db {

    private static $db;

    private static $server=DB_SERVER;

    private static $username=DB_USERNAME;

    private static $password=DB_PASSWORD;

    private static $dbName=DB_NAME;

    public static function connect(){

        try {
            $conn = new PDO("mysql:host=".self::$server.";dbname=".self::$dbName.";charset=utf8mb4", self::$username, self::$password); 
            mb_internal_encoding('utf8');
            // set the PDO error mode to exception
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$db=$conn;
            return $conn;
        }catch(PDOException $e) {
            file_put_contents('DatabaseExeptions.log',$e);
        }

    }

    public static function insert($table,array $params){
        if($table && $params){
            $db=Db::connect();
            $columns=self::createValidCulomnFormat($params);
            $values=self::createValidValuesFormat($params);
            $sql="INSERT INTO $table $columns VALUES $values";
            
            try{
                $db->query($sql);
                return $db->lastInsertId();
            }catch(PDOException $e){
                file_put_contents('DatabaseExeptions.log',$e);
            }
        }
    }

    //pass array to this function and this will return correct format of column for insert. like : (id,name,age)
    private static function createValidCulomnFormat(array $params){
        if($params){
            $columns="";
            foreach($params as $key=>$value){
                $columns.=$key.",";
            }
            $columns="(".trim($columns,",").")";
            return $columns;
        }else{
            return false;
        }

    }

    //pass array to this function and It will return correct format of values to insert. like: ('1','arash','22')
    private static function createValidValuesFormat(array $inputs){
        if($inputs){
            foreach($inputs as $key=>$value){
                $inputs[$key]=str_replace("'","\'",$value);
            }
            //handle single quotation exeptions
            $values="('".implode("','",$inputs)."')";
            return $values;
        }else{
            return false;
        }
    }

    //select data with limits and order type and It has pagination option too
    public static function select($table,$conditions="1",$fetchType='All' /*all or single */,$disdinct="*",$limit=0,$orderBy='id',$orderType='ASC',$perPage="",$offset=1){
        
        if($limit){
            if($perPage!=="" && is_numeric($perPage) && $perPage>0 && $offset>=1){
                //for paginations
                $start=($offset-1)*$perPage; //start of limit 
                $len=$perPage; //length of limit
                $sql="SELECT $disdinct FROM $table WHERE $conditions ORDER BY $orderBy $orderType LIMIT $start,$len";
            }elseif($limit){
                $sql="SELECT $disdinct FROM $table WHERE $conditions ORDER BY $orderBy $orderType LIMIT $limit";
            }
        }else{
            $sql="SELECT $disdinct FROM $table WHERE $conditions ORDER BY $orderBy $orderType";
        }
    
        
        if($sql){
            $result=Db::connect()->query($sql);
            if(strtolower($fetchType)=='all'){
                if($res=$result->fetchAll(PDO::FETCH_NAMED)){
                    return $res;
                }else{
                    return false;
                }
            }elseif(strtolower($fetchType)=='single'){
                if($res=$result->fetch(PDO::FETCH_ASSOC)){
                    return $res;
                }else{
                    return false;
                }
            }
        }else{
            return false;
        }

    }

    public static function delete($table,$conditions){
        $sql="DELETE FROM $table WHERE $conditions";
        try{
            $db=Db::connect()->query($sql);
            return true;
        }catch(PDOException $e){
            file_put_contents('DatabaseExeptions.log',$e);
            return false;
        }
    }

    public static function update(string $table,array $values,string $conditions="id='-1'"){
        $validValues=self::createValidValuesForUpdate($values);
        $sql="UPDATE $table SET $validValues WHERE $conditions";
        
        // global $bot;
        // $bot->sendMessage(['text'=>$sql]);
        try{
            Db::connect()->query($sql);
            return true;
        }catch(PDOException $e){
            file_put_contents('DatabaseExeptions.log',$e);
            return false;
        }
    }


    private static function createValidValuesForUpdate(array $values){
        $str="";
        if($values){
            foreach($values as $key=>$value){
                $values[$key]=str_replace("'","\'",$value);
                $str.="$key='".$values[$key]."',";
            }
            return trim($str,",");
        }else{
            return false;
        }
    }

    public static function checkExists($table,$conditions,$disdinct="id"){
        $item=self::select($table,$conditions,'single',$disdinct);
        if($item){
            return $item;
        }else{
            return false;
        }
    }

    public static function query($sql){
        
        try{
            return Db::connect()->query($sql);
        }catch(PDOException $e){
            file_put_contents('DatabaseExeptions.log',$e);
            return false;
        } 
    }

    //SIMPLE TEXT SEARCH
    public static function simpleSearch(array $tables,$conditions="1",$disdinct="*",$limit=0,$orderBy='id',$orderType='ASC',$perPage="",$offset=1){
        $tables=self::convertTbArrToSql($tables);
        
        if($limit){
            if($perPage!=="" && is_numeric($perPage) && $perPage>0 && $offset>=1){
                //for paginations
                $start=($offset-1)*$perPage; //start of limit 
                $len=$perPage; //length of limit
                $sql="SELECT $disdinct FROM $tables WHERE $conditions ORDER BY $orderBy $orderType LIMIT $start,$len";
            }elseif($limit){
                $sql="SELECT $disdinct FROM $tables WHERE $conditions ORDER BY $orderBy $orderType LIMIT $limit";
            }
        }else{
            $sql="SELECT $disdinct FROM $tables WHERE $conditions ORDER BY $orderBy $orderType";
        }

        if(isset($sql) && $sql){

            $result=self::query($sql);
            if($res=$result->fetchAll(PDO::FETCH_NAMED)){
                return $res;
            }else{
                return false;
            }

        }else{
            return false;
        }
        
    }

    //FULL TEXT SEARCH 
    public static function fullSearch(array $tables,array $columns,string $term,$disdinct="*",$limit=0,$orderBy='id',$orderType='ASC',$perPage="",$offset=1){
        $tables=self::convertTbArrToSql($tables);
        $columns=self::convertTbArrToSql($columns);
        $term=self::correctTermFormat($term,"fulltext");

        if($limit){
            if($perPage!=="" && is_numeric($perPage) && $perPage>0 && $offset>=1){
                //for paginations
                $start=($offset-1)*$perPage; //start of limit 
                $len=$perPage; //length of limit
 
                $sql="SELECT $disdinct FROM $tables WHERE MATCH($columns) AGAINST('$term') LIMIT $start,$len";
            }elseif($limit){
               $sql="SELECT $disdinct FROM $tables WHERE MATCH($columns) AGAINST('$term') LIMIT $limit";  
            }
        }else{
            $sql="SELECT $disdinct FROM $tables WHERE MATCH($columns) AGAINST('$term')";
        }

        if(isset($sql) && $sql){
            echo $sql;
            $result=self::query($sql);
            if($res=$result->fetchAll(PDO::FETCH_NAMED)){
                $count=count($res);
                
                if($count>2){
                    if(strtolower($orderType)=='asc'){
                        $orderType=SORT_ASC;
                    }else{
                        $orderType=SORT_DESC;
                    }
                    $topItems[0]=$res[0];
                    $topItems[1]=$res[1];
                    unset($res[0],$res[1]);

                    
                    $sort=self::orderArray($res,$orderBy,$orderType);

                    $res=array_merge($topItems,$sort);
                    print_r($res);
                    die();
                }
               
                return $res;
            }else{
                return false;
            }

        }else{
            return false;
        }
    }

    //CONVERT ARRAY TO SQL FORMAT
    private static function convertTbArrToSql(array $tables){
        $tables=implode(",",$tables);
        return $tables;
    }

    //CORRECT TERM FORMAT
    public static function correctTermFormat($term,$termType="simple or fulltext"){
        $term=str_replace("'","\'",$term);
        if(strtolower($termType)=='simple'){
            $term=str_replace(" ","%",$term);
        }elseif(strtolower($termType)=='fulltext'){
            $term=str_replace(" ",",",$term);
        }else{
            echo 'fill $termType with one of these words: simple or fulltext';
        }
        
        return $term;
    }

    //sort array with order
    public static function orderArray($array,$order='id',$orderBy=SORT_DESC){
        $args = func_get_args();
        $data = array_shift($args);
        foreach ($args as $n => $field) {
            if (is_string($field)) {
                $tmp = array();
                foreach ($data as $key => $row)
                    $tmp[$key] = $row[$field];
                $args[$n] = $tmp;
                }
        }
        $args[] = &$data;
        call_user_func_array('array_multisort', $args);
        return array_pop($args);
    }


}