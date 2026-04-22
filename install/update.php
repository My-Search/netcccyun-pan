<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);
require '../config.php';

@header('Content-Type: text/html; charset=UTF-8');

function random($length, $numeric = 0) {
	$seed = base_convert(md5(microtime().$_SERVER['DOCUMENT_ROOT']), 16, $numeric ? 10 : 35);
	$seed = $numeric ? (str_replace('0', '', $seed).'012340567890') : ($seed.'zZ'.strtoupper($seed));
	$hash = '';
	$max = strlen($seed) - 1;
	for($i = 0; $i < $length; $i++) {
		$hash .= $seed[mt_rand(0, $max)];
	}
	return $hash;
}

try{
	$db=new PDO("mysql:host=".$dbconfig['host'].";dbname=".$dbconfig['dbname'].";port=".$dbconfig['port'],$dbconfig['user'],$dbconfig['pwd']);
}catch(Exception $e){
	exit('链接数据库失败:'.$e->getMessage());
}
date_default_timezone_set("PRC");
$date = date("Y-m-d");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
$db->exec("set sql_mode = ''");
$db->exec("set names utf8");

$version = 0;
if($rs = $db->query("SELECT v FROM pre_config WHERE k='version'")){
	$version = $rs->fetchColumn();
}

$sqls = [];
if($version<1001){
	$sqls = array_merge($sqls, explode(';', file_get_contents('update_1001.sql')));
	if(!$db->query("SELECT v FROM pre_config WHERE k='syskey'")->fetchColumn()){
		$sqls[]="REPLACE INTO `pre_config` VALUES ('syskey', '".random(32)."')";
	}
}
if($version<1002){
	$sqls = array_merge($sqls, explode(';', file_get_contents('update_1002.sql')));
}
if($version<1003){
	$sqls = array_merge($sqls, explode(';', file_get_contents('update_1003.sql')));
}
if($version<1004){
	$sqls = array_merge($sqls, explode(';', file_get_contents('update_1004.sql')));
}
if($version<1005){
	$sqls = array_merge($sqls, explode(';', file_get_contents('update_1005.sql')));
}
if($version<1006){
	$sqls = array_merge($sqls, explode(';', file_get_contents('update_1006.sql')));
}
if(empty($sqls)){
	exit('你的网站已经升级到最新版本了');
}
$sqls[]="REPLACE INTO `pre_config` VALUES ('version', '1006')";
$success=0;$error=0;$errorMsg=null;
foreach ($sqls as $value) {
	$value=trim($value);
	if(empty($value))continue;
	if($db->exec($value)===false){
		$error++;
		$dberror=$db->errorInfo();
		$errorMsg.=$dberror[2]."<br>";
	}else{
		$success++;
	}
}
// 创建默认管理员前台账号（如果不存在）
$admin_user = $db->query("SELECT v FROM pre_config WHERE k='admin_user'")->fetchColumn();
$admin_pwd = $db->query("SELECT v FROM pre_config WHERE k='admin_pwd'")->fetchColumn();
if($admin_user && $admin_pwd){
	$exists = $db->query("SELECT uid FROM pre_user WHERE type='local' AND username='".addslashes($admin_user)."'")->fetchColumn();
	if(!$exists){
		$hash = password_hash($admin_pwd, PASSWORD_DEFAULT);
		$db->exec("INSERT INTO pre_user (type, openid, username, password, nickname, enable, level, addtime, lasttime) VALUES ('local', '".addslashes($admin_user)."', '".addslashes($admin_user)."', '".addslashes($hash)."', '管理员', 1, 1, NOW(), NOW())");
	}
}
echo '成功执行SQL语句'.$success.'条！<br/>';
if($errorMsg){
//echo '<div class="alert alert-danger text-center" role="alert">'.$errorMsg.'</div>';
}
exit("<script language='javascript'>alert('网站数据库升级完成！');window.location.href='../';</script>");
?>