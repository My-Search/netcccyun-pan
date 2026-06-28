<?php
/**
 * 彩虹外链网盘 - 公共函数库
 * 
 * 本文件存放 ajax.php 与 includes/ajax.php 中重复的函数定义，
 * 通过 common.php 统一引入，避免代码重复维护。
 */

function getFolderPath($folder_id, $uid){
	global $DB;
	$path = [];
	$fid = intval($folder_id);
	while($fid > 0){
		$row = $DB->getRow("SELECT * FROM pre_folder WHERE id=:id AND uid=:uid", [':id'=>$fid, ':uid'=>$uid]);
		if(!$row) break;
		array_unshift($path, ['id'=>$row['id'], 'name'=>$row['name']]);
		$fid = $row['parent_id'];
	}
	return $path;
}

function parse_ini_size($val){
	$val = trim($val);
	$last = strtolower(substr($val, -1));
	$num = intval($val);
	switch($last){
		case 'g': $num *= 1024;
		case 'm': $num *= 1024;
		case 'k': $num *= 1024;
	}
	return $num;
}

function get_safe_chunksize(){
	$upload_max = parse_ini_size(ini_get('upload_max_filesize'));
	$post_max = parse_ini_size(ini_get('post_max_size'));
	$limit = min($upload_max, $post_max);
	$safe = max(1024 * 1024, intval($limit * 0.8));
	return min($safe, 8 * 1024 * 1024);
}

function getUserUsedStorage($uid){
	global $DB;
	$used = $DB->getColumn("SELECT COALESCE(SUM(size), 0) FROM pre_file WHERE uid=:uid", [':uid'=>$uid]);
	return intval($used);
}

function checkUserStorageLimit($uid, $addSize = 0){
	global $DB, $conf;
	if(!$uid) return true; // 未登录用户不限制
	$user = $DB->getRow("SELECT storage_limit FROM pre_user WHERE uid=:uid", [':uid'=>$uid]);
	if(!$user || intval($user['storage_limit']) <= 0) return true; // 0表示不限制
	$used = getUserUsedStorage($uid);
	$limit = intval($user['storage_limit']);
	if($used + $addSize > $limit){
		return false;
	}
	return true;
}

/**
 * 生成邀请上传使用的 4 位数字密码。
 * 主流程：优先使用 random_int；旧 PHP 环境不可用时回退 mt_rand。
 */
function generateUploadInvitePwd(){
	if(function_exists('random_int')){
		return str_pad(strval(random_int(0, 9999)), 4, '0', STR_PAD_LEFT);
	}
	return str_pad(strval(mt_rand(0, 9999)), 4, '0', STR_PAD_LEFT);
}

/**
 * 生成邀请上传 token。
 * 主流程：优先使用 random_bytes 生成 128bit 随机 token；旧环境不可用时回退现有 md5 风格。
 */
function generateUploadInviteToken(){
	if(function_exists('random_bytes')){
		return bin2hex(random_bytes(16));
	}
	return md5(uniqid().mt_rand(0,999).time());
}

/**
 * 解析邀请上传的单文件大小上限。
 * 主流程：按 MB 接收用户输入；为空或非法时使用默认 1GB，避免无限制公开入口。
 */
function parseUploadInviteMaxSize($value){
	$mb = intval($value);
	if($mb <= 0){
		$mb = 1024;
	}
	return $mb * 1024 * 1024;
}

/**
 * 解析邀请有效期。
 * 主流程：按小时接收用户输入；0 表示长期有效，正数转换为过期时间。
 */
function parseUploadInviteExpireTime($hours){
	if($hours === '' || !preg_match('/^\d+$/', strval($hours))){
		return false;
	}
	$hours = intval($hours);
	if($hours <= 0){
		return null;
	}
	return date('Y-m-d H:i:s', time() + $hours * 3600);
}

/**
 * 解析邀请上传备注。
 * 主流程：清理 HTML 后限制长度，作为邀请上传成功后写入文件备注的固定说明。
 */
function parseUploadInviteRemark($value){
	$remark = trim(strip_tags(strval($value)));
	if(function_exists('mb_substr')){
		return mb_substr($remark, 0, 200, 'UTF-8');
	}
	return substr($remark, 0, 200);
}

/**
 * 输出邀请上传数据库写入失败信息。
 * 主流程：将底层 SQL 错误转成可理解提示，避免返回不可用的邀请链接。
 */
function exitUploadInviteDatabaseError(){
	global $DB;
	$error = $DB->error();
	$msg = '邀请保存失败，请先完成网站数据库升级';
	if($error){
		$msg .= '：'.$error;
	}
	exit(json_encode(['code'=>-1, 'msg'=>$msg, 'error'=>'database']));
}

/**
 * 完成一次邀请上传后立即停用邀请。
 * 主流程：成功计数加一并关闭邀请，使链接成为一次性上传入口。
 */
function completeUploadInvite($token){
	global $DB;
	if(empty($token))return;
	$DB->exec("UPDATE pre_upload_invite SET uploads=uploads+1, enable=0 WHERE token=:token", [':token'=>$token]);
}

/**
 * 通过 hash 兼容旧客户端删除请求。
 * 主流程：仅在当前用户/会话可操作范围内匹配唯一记录；多引用歧义时要求客户端传 file_id。
 */
function getDeletableFileByHash($hash){
	global $DB, $islogin2, $uid;
	if($islogin2){
		$rows = $DB->getAll("SELECT * FROM `pre_file` WHERE `hash`=:hash AND `uid`=:uid", [':hash'=>$hash, ':uid'=>$uid]);
	}else{
		if(!isset($_SESSION['fileids']) || !is_array($_SESSION['fileids']) || count($_SESSION['fileids']) === 0)return false;
		$ids = array_map('intval', $_SESSION['fileids']);
		$placeholders = implode(',', array_fill(0, count($ids), '?'));
		$params = array_merge([$hash], $ids);
		$rows = $DB->getAll("SELECT * FROM `pre_file` WHERE `hash`=? AND `id` IN ($placeholders)", $params);
	}
	if(!$rows || count($rows) === 0)return false;
	if(count($rows) > 1){
		exit('{"code":-1,"msg":"存在多个同文件引用，请刷新列表后重试"}');
	}
	return $rows[0];
}

/**
 * 校验免登录邀请上传上下文。
 * 主流程：校验 token 与 4 位数字密码，确认目标目录仍属于邀请创建者，并返回归属 uid/folder_id。
 */
function resolveUploadInviteContext($token, $pwd){
	global $DB;
	$token = trim($token);
	$pwd = trim($pwd);
	if(empty($token) || !preg_match('/^[0-9a-z]{32}$/i', $token)){
		return ['ok'=>false, 'msg'=>'邀请链接无效'];
	}
	if(!preg_match('/^\d{4}$/', $pwd)){
		return ['ok'=>false, 'msg'=>'上传密码必须为4位数字'];
	}
	$invite = $DB->getRow("SELECT * FROM pre_upload_invite WHERE token=:token", [':token'=>$token]);
	if(!$invite){
		return ['ok'=>false, 'msg'=>'邀请已失效'];
	}
	if(intval($invite['enable']) !== 1){
		return ['ok'=>false, 'msg'=>'邀请已失效'];
	}
	if(!empty($invite['expire_time']) && strtotime($invite['expire_time']) < time()){
		return ['ok'=>false, 'msg'=>'邀请已失效'];
	}
	if(intval($invite['fail_count']) >= 10 && !empty($invite['last_failtime']) && strtotime($invite['last_failtime']) > strtotime('-10 minutes')){
		return ['ok'=>false, 'msg'=>'密码错误次数过多，请10分钟后再试'];
	}
	if($invite['pwd'] !== $pwd){
		$DB->exec("UPDATE pre_upload_invite SET fail_count=fail_count+1, last_failtime=NOW() WHERE id=:id", [':id'=>$invite['id']]);
		return ['ok'=>false, 'msg'=>'上传密码错误'];
	}
	$folder = null;
	if(intval($invite['folder_id']) > 0){
		$folder = $DB->getRow("SELECT * FROM pre_folder WHERE id=:id AND uid=:uid", [':id'=>$invite['folder_id'], ':uid'=>$invite['uid']]);
		if(!$folder){
			return ['ok'=>false, 'msg'=>'邀请已失效'];
		}
	}
	if(intval($invite['fail_count']) > 0){
		$DB->exec("UPDATE pre_upload_invite SET fail_count=0, last_failtime=NULL WHERE id=:id", [':id'=>$invite['id']]);
	}
	return ['ok'=>true, 'invite'=>$invite, 'folder'=>$folder, 'uid'=>intval($invite['uid']), 'folder_id'=>intval($invite['folder_id'])];
}

/**
 * 复核会话中的邀请上传授权。
 * 主流程：分片/完成阶段重新校验邀请 token、密码、目录归属与会话目标，避免邀请被删除或密码轮换后继续写入。
 */
function revalidateUploadInviteSession(){
	if(empty($_SESSION['upload']['invite_token'])){
		return ['ok'=>true, 'invite'=>null];
	}
	$pwd = isset($_SESSION['upload']['invite_pwd']) ? $_SESSION['upload']['invite_pwd'] : '';
	$context = resolveUploadInviteContext($_SESSION['upload']['invite_token'], $pwd);
	if(!$context['ok']){
		return $context;
	}
	if(intval($_SESSION['upload']['uid']) !== $context['uid'] || intval($_SESSION['upload']['folder_id']) !== $context['folder_id']){
		return ['ok'=>false, 'msg'=>'邀请上传目标已变更，请重新选择文件上传'];
	}
	if(isset($_SESSION['upload']['size']) && intval($context['invite']['max_size']) > 0 && intval($_SESSION['upload']['size']) > intval($context['invite']['max_size'])){
		return ['ok'=>false, 'msg'=>'文件超过邀请允许的大小上限（'.size_format($context['invite']['max_size']).'）'];
	}
	return $context;
}

function formatStorageSize($bytes){
	$units = ['B', 'KB', 'MB', 'GB', 'TB'];
	$unitIndex = 0;
	while($bytes >= 1024 && $unitIndex < count($units) - 1){
		$bytes /= 1024;
		$unitIndex++;
	}
	return round($bytes, 2) . $units[$unitIndex];
}

function resolveUploadPath($relativePath, $baseFolderId, $uid, $DB){
	$relativePath = str_replace(['\\\\',':','*','"','<','>','|','?'], '', $relativePath);
	$parts = explode('/', $relativePath);
	$fileName = array_pop($parts);
	if(empty($fileName) && count($parts) > 0){
		$fileName = array_pop($parts);
	}
	$currentFolderId = intval($baseFolderId);
	foreach($parts as $part){
		$part = trim($part);
		if($part === '') continue;
		$row = $DB->getRow("SELECT * FROM pre_folder WHERE uid=:uid AND parent_id=:parent_id AND name=:name", [':uid'=>$uid, ':parent_id'=>$currentFolderId, ':name'=>$part]);
		if($row){
			$currentFolderId = $row['id'];
		}else{
			$id = $DB->insert('folder', [
				'uid' => $uid,
				'parent_id' => $currentFolderId,
				'name' => $part,
				'addtime' => 'NOW()',
			]);
			if($id){
				$currentFolderId = $id;
			}else{
				$row = $DB->getRow("SELECT * FROM pre_folder WHERE uid=:uid AND parent_id=:parent_id AND name=:name", [':uid'=>$uid, ':parent_id'=>$currentFolderId, ':name'=>$part]);
				if($row){
					$currentFolderId = $row['id'];
				}else{
					return ['folder_id'=>$currentFolderId, 'name'=>$fileName, 'error'=>'创建目录失败：'.$part];
				}
			}
		}
	}
	return ['folder_id'=>$currentFolderId, 'name'=>$fileName, 'error'=>null];
}

function getSubFolderIds($parentId, $uid, $DB){
	$ids = [$parentId];
	$children = $DB->getAll("SELECT id FROM pre_folder WHERE parent_id=:parent_id AND uid=:uid", [':parent_id'=>$parentId, ':uid'=>$uid]);
	foreach($children as $child){
		$ids = array_merge($ids, getSubFolderIds($child['id'], $uid, $DB));
	}
	return $ids;
}

function getShareFolderTree($parentId, $uid, $DB){
	$folders = $DB->getAll("SELECT id, name, parent_id FROM pre_folder WHERE parent_id=:parent_id AND uid=:uid", [':parent_id'=>$parentId, ':uid'=>$uid]);
	$result = [];
	foreach($folders as $f){
		$f['children'] = getShareFolderTree($f['id'], $uid, $DB);
		$result[] = $f;
	}
	return $result;
}

function getMineFolderVersion($folder_id, $uid){
	global $DB;
	$folderVersion = $DB->getRow("SELECT COUNT(*) AS total, COALESCE(MAX(id), 0) AS max_id, COALESCE(MAX(addtime), '') AS max_addtime FROM pre_folder WHERE uid=:uid AND parent_id=:parent_id", [':uid'=>$uid, ':parent_id'=>$folder_id]);
	$fileVersion = $DB->getRow("SELECT COUNT(*) AS total, COALESCE(MAX(id), 0) AS max_id, COALESCE(MAX(addtime), '') AS max_addtime FROM pre_file WHERE uid=:uid AND folder_id=:folder_id", [':uid'=>$uid, ':folder_id'=>$folder_id]);
	return implode('|', [
		intval($folderVersion['total']),
		intval($folderVersion['max_id']),
		$folderVersion['max_addtime'],
		intval($fileVersion['total']),
		intval($fileVersion['max_id']),
		$fileVersion['max_addtime'],
	]);
}

function createFoldersRecursively($folders, $targetParentId, $uid, $DB, &$map){
	foreach($folders as $f){
		$exist = $DB->getRow("SELECT * FROM pre_folder WHERE uid=:uid AND parent_id=:parent_id AND name=:name", [':uid'=>$uid, ':parent_id'=>$targetParentId, ':name'=>$f['name']]);
		if($exist){
			$newId = $exist['id'];
		} else {
			$newId = $DB->insert('folder', [
				'uid' => $uid,
				'parent_id' => $targetParentId,
				'name' => $f['name'],
				'addtime' => 'NOW()',
			]);
		}
		$map[$f['id']] = $newId;
		if(!empty($f['children'])){
			createFoldersRecursively($f['children'], $newId, $uid, $DB, $map);
		}
	}
}
