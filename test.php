<?php

require_once 'DbClass.php';
use Database\Db;
echo '<pre>';
//$term=Db::correctTermFormat($_GET['q'],"simple");
//var_dump(Db::simpleSearch(['tracks'],"performer LIKE '%$term%'"));
var_dump(Db::fullSearch(['tracks'],['performer','title'],$_GET['q'],"*",0,'downloads','desc'));
echo '</pre>';