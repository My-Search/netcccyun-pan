<?php
$nosecu = true;
include("./includes/common.php");
$act=isset($_GET['act'])?daddslashes($_GET['act']):null;
$uid = isset($uid) ? intval($uid) : 0;

if(!checkRefererHost())exit('{"code":403}');

@header('Content-Type: application/json; charset=UTF-8');

if($islogin2 && $userrow['level']>0){
	$conf['upload_limit']=0;
	$conf['videoreview']=0;
	$conf['type_block']=null;
	$conf['name_block']=null;
}

function createFileReference($name, $type, $size, $hash, $ip, $pwd, $uid, $folder_id, $remark = null){
	global $DB;
	$sds = $DB->exec("INSERT INTO `pre_file` (`name`,`type`,`size`,`hash`,`addtime`,`ip`,`pwd`,`uid`,`folder_id`,`remark`) values (:name,:type,:size,:hash,NOW(),:ip,:pwd,:uid,:folder_id,:remark)", [':name'=>$name, ':type'=>$type, ':size'=>$size, ':hash'=>$hash, ':ip'=>$ip, ':pwd'=>$pwd, ':uid'=>$uid, ':folder_id'=>$folder_id, ':remark'=>$remark]);
	if(!$sds)exit('{"code":-1,"msg":"上传失败'.$DB->error().'","error":"database"}');
	return $DB->lastInsertId();
}

/**
 * 通过 hash 兼容旧客户端删除请求。
 * 主流程：仅在当前用户/会话可操作范围内匹配唯一记录；多引用歧义时要求客户端传 file_id。
 */
switch($act){
case 'pre_upload':
	if(!checkCsrfToken(isset($_POST['csrf_token'])?$_POST['csrf_token']:''))exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	$invite_token = isset($_POST['invite_token'])?trim($_POST['invite_token']):'';
	$invite_pwd = isset($_POST['invite_pwd'])?trim($_POST['invite_pwd']):'';
	$inviteContext = null;
	if(!empty($invite_token) || !empty($invite_pwd)){
		$inviteContext = resolveUploadInviteContext($invite_token, $invite_pwd);
		if(!$inviteContext['ok'])exit(json_encode(['code'=>-1, 'msg'=>$inviteContext['msg']]));
	}
	$inviteUploadMode = $inviteContext ? true : false;
	if($conf['forcelogin']==1 && !$islogin2 && !$inviteContext)exit('{"code":-1,"msg":"请先登录"}');
	$name = trim(htmlspecialchars($_POST['name']));
	$hash = trim($_POST['hash']);
	$size = intval($_POST['size']);
	$ispwd = intval($_POST['ispwd']);
	$pwd = $ispwd==1?trim(htmlspecialchars($_POST['pwd'])):null;
	$remark = $inviteContext ? parseUploadInviteRemark($inviteContext['invite']['remark']) : null;
	$folder_id = isset($_POST['folder_id'])?intval($_POST['folder_id']):0;
	$uploadUid = $uid ? $uid : 0;
	if($inviteContext){
		$uploadUid = $inviteContext['uid'];
		$folder_id = $inviteContext['folder_id'];
	}elseif($folder_id>0 && $islogin2){
		$frow = $DB->getRow("SELECT * FROM pre_folder WHERE id=:id AND uid=:uid", [':id'=>$folder_id, ':uid'=>$uid]);
		if(!$frow) $folder_id = 0;
	}elseif($folder_id>0){
		$folder_id = 0;
	}
	$relative_path = isset($_POST['relative_path'])?trim($_POST['relative_path']):'';
	if(!empty($relative_path) && ($islogin2 || $inviteContext)){
		$res = resolveUploadPath($relative_path, $folder_id, $uploadUid, $DB);
		if($res['error']){
			exit('{"code":-1,"msg":"'.$res['error'].'"}');
		}
		$folder_id = $res['folder_id'];
		$name = $res['name'];
	}
	$name = str_replace(['/','\\',':','*','"','<','>','|','?'],'',$name);
	if(empty($name))exit('{"code":-1,"msg":"文件名不能为空"}');
	if(!preg_match('/^[0-9a-z]{32}$/i', $hash))exit('{"code":-1,"msg":"hash error"}');
	if($ispwd==1 && !empty($pwd)){
		if (!preg_match('/^[a-zA-Z0-9]+$/', $pwd)) {
			exit('{"code":-1,"msg":"文件密码只能为字母和数字"}');
		}
	}
	$ext=get_file_ext($name);
	if($conf['type_block']){
		$type_block = explode('|',$conf['type_block']);
		if(in_array($ext,$type_block)){
			exit('{"code":-1,"msg":"文件上传失败，不支持上传该格式文件","error":"block"}');
		}
	}
	if($conf['name_block']){
		$name_block = explode('|',$conf['name_block']);
		foreach($name_block as $row){
			if(strpos($name,$row)!==false){
				exit('{"code":-1,"msg":"文件上传失败","error":"block"}');
			}
		}
	}
	$limit_size = intval($conf['upload_size']);
	if($limit_size > 0 && $size > $limit_size * 1024 * 1024){
		exit('{"code":-1,"msg":"上传文件大小限制'.$limit_size.'MB"}');
	}
	if($inviteContext && intval($inviteContext['invite']['max_size']) > 0 && $size > intval($inviteContext['invite']['max_size'])){
		exit(json_encode(['code'=>-1, 'msg'=>'文件超过邀请允许的大小上限（'.size_format($inviteContext['invite']['max_size']).'）']));
	}
	// 用户容量限制检查
	if(($islogin2 || $inviteContext) && !checkUserStorageLimit($uploadUid, $size)){
		$used = getUserUsedStorage($uploadUid);
		$userLimit = $DB->getRow("SELECT storage_limit FROM pre_user WHERE uid=:uid", [':uid'=>$uploadUid]);
		$limitStr = formatStorageSize(intval($userLimit['storage_limit']));
		$usedStr = formatStorageSize($used);
		exit('{"code":-1,"msg":"您的存储空间不足（已用'.$usedStr.' / 总共'.$limitStr.'），无法上传该文件","error":"storage_limit"}');
	}
	if($conf['upload_limit']>0){
		$thisday = date("Y-m-d 00:00:00");
		if($islogin2 || $inviteContext){
			$ipcount=$DB->getColumn("SELECT count(*) from pre_file WHERE uid=:uid AND addtime>=:day", [':uid'=>$uploadUid, ':day'=>$thisday]);
		}else{
			$ipcount=$DB->getColumn("SELECT count(*) from pre_file WHERE ip='$clientip' AND addtime>='".$thisday."'");
		}
		if($ipcount>$conf['upload_limit']){
			exit('{"code":-1,"msg":"你今天上传文件的数量已超过限制"}');
		}
	}
	$overwrite_id = null;
	$old_hash = null;

	// 检查当前目录同名文件（覆盖逻辑）
	$existing = $DB->getRow("SELECT * FROM pre_file WHERE uid=:uid AND folder_id=:folder_id AND name=:name", [':uid'=>$uploadUid, ':folder_id'=>$folder_id, ':name'=>$name]);
	if($existing && $existing['hash'] == $hash){
		// 完全相同的文件，秒传（保留原记录）
		if($inviteContext){
			$DB->exec("UPDATE pre_file SET remark=:remark WHERE id=:id", [':remark'=>$remark, ':id'=>$existing['id']]);
			completeUploadInvite($invite_token);
		}
		$result = ['code'=>1, 'msg'=>'本站已存在该文件', 'exists'=>1, 'hash'=>$hash, 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$existing['id']];
		exit(json_encode($result));
	}
	if($existing && $inviteUploadMode){
		exit('{"code":-1,"msg":"目标目录已存在同名文件，请更换文件名后再上传"}');
	}
	if($existing && $existing['hash'] != $hash){
		// 同名但内容不同，需要覆盖
		$hash_exists = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash", [':hash'=>$hash]);
		if($hash_exists){
			// 新 hash 物理文件已存在，直接更新数据库记录
			$old_hash = $existing['hash'];
			$file_id = $existing['id'];
			$DB->exec("UPDATE pre_file SET hash=:hash, size=:size, type=:type, addtime=NOW(), ip=:ip, pwd=:pwd WHERE id=:id", [':hash'=>$hash, ':size'=>$size, ':type'=>$ext, ':ip'=>$clientip, ':pwd'=>$pwd, ':id'=>$file_id]);
			if($inviteContext){
				completeUploadInvite($invite_token);
			}
			$refCount = $DB->getColumn("SELECT count(*) FROM pre_file WHERE hash=:hash", [':hash'=>$old_hash]);
			if(intval($refCount) === 0){
				$stor->delete($old_hash);
			}
			$result = ['code'=>1, 'msg'=>'文件更新成功', 'exists'=>1, 'hash'=>$hash, 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$file_id];
			exit(json_encode($result));
		}
		// 新 hash 不存在，需要上传，记录覆盖信息
		$overwrite_id = $existing['id'];
		$old_hash = $existing['hash'];
	}

	// 检查全站 hash（秒传）
	$row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash", [':hash'=>$hash]);
	if($row && empty($overwrite_id)){
		$file_id = createFileReference($name, $ext, $size, $hash, $clientip, $pwd, $uploadUid, $folder_id, $remark);
		if($inviteContext){
			completeUploadInvite($invite_token);
		}else{
			$_SESSION['fileids'][] = $file_id;
		}
		$result = ['code'=>1, 'msg'=>'本站已存在该文件', 'exists'=>1, 'hash'=>$hash, 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$file_id];
		exit(json_encode($result));
	}

	if(\lib\StorHelper::is_cloud() && $conf['uploadfile_type'] == 1){
		$param = $stor->getUploadParam($hash, $name, $limit_size * 1024 * 1024);
		if(!$param)exit('{"code":-1,"msg":"获取上传参数失败","errmsg":"'.$stor->errmsg().'"}');
		$_SESSION['upload'] = [
			'chunks' => 1,
			'name' => $name,
			'hash' => $hash,
			'size' => $size,
			'ext' => $ext,
			'pwd' => $pwd,
			'folder_id' => $folder_id,
			'overwrite_id' => $overwrite_id,
			'old_hash' => $old_hash,
			'uid' => $uploadUid,
			'invite_token' => $inviteContext ? $invite_token : null,
			'invite_pwd' => $inviteContext ? $invite_pwd : null,
			'remark' => $remark
		];
		$result = ['code'=>0, 'third'=>true, 'hash'=>$hash, 'url'=>$param['url'], 'post'=>$param['post']];
		exit(json_encode($result));
	}else{
		$chunksize = get_safe_chunksize();
		$chunks = ceil($size / $chunksize);
		$_SESSION['upload'] = [
			'chunks' => $chunks,
			'name' => $name,
			'hash' => $hash,
			'size' => $size,
			'ext' => $ext,
			'pwd' => $pwd,
			'folder_id' => $folder_id,
			'overwrite_id' => $overwrite_id,
			'old_hash' => $old_hash,
			'uid' => $uploadUid,
			'invite_token' => $inviteContext ? $invite_token : null,
			'invite_pwd' => $inviteContext ? $invite_pwd : null,
			'remark' => $remark
		];
		$result = ['code'=>0, 'third'=>false, 'hash'=>$hash, 'chunksize'=>$chunksize, 'chunks'=>$chunks];
		exit(json_encode($result));
	}
break;

case 'upload_part':
	if(!isset($_FILES['file']))exit('{"code":-1,"msg":"请选择文件"}');
	// 用户容量限制检查（二次校验）
	$uploadUid = isset($_SESSION['upload']['uid']) ? intval($_SESSION['upload']['uid']) : ($uid ? $uid : 0);
	if($uploadUid && isset($_SESSION['upload']['size']) && !checkUserStorageLimit($uploadUid, $_SESSION['upload']['size'])){
		$used = getUserUsedStorage($uploadUid);
		$userLimit = $DB->getRow("SELECT storage_limit FROM pre_user WHERE uid=:uid", [':uid'=>$uploadUid]);
		$limitStr = formatStorageSize(intval($userLimit['storage_limit']));
		$usedStr = formatStorageSize($used);
		exit('{"code":-1,"msg":"您的存储空间不足（已用'.$usedStr.' / 总共'.$limitStr.'），无法继续上传","error":"storage_limit"}');
	}
	if(isset($_FILES['file']['error']) && $_FILES['file']['error'] !== UPLOAD_ERR_OK){
		$errMap = [
			UPLOAD_ERR_INI_SIZE => '文件大小超出服务器限制(' . ini_get('upload_max_filesize') . ')',
			UPLOAD_ERR_FORM_SIZE => '文件大小超出表单限制',
			UPLOAD_ERR_PARTIAL => '文件只有部分被上传，请重试',
			UPLOAD_ERR_NO_FILE => '没有文件被上传',
			UPLOAD_ERR_NO_TMP_DIR => '服务器缺少临时文件夹',
			UPLOAD_ERR_CANT_WRITE => '服务器文件写入失败',
			UPLOAD_ERR_EXTENSION => '服务器扩展阻止了文件上传',
		];
		$errMsg = isset($errMap[$_FILES['file']['error']]) ? $errMap[$_FILES['file']['error']] : '未知上传错误('.$_FILES['file']['error'].')';
		exit('{"code":-1,"msg":"'.$errMsg.'","error":"php_upload"}');
	}
	if(!checkCsrfToken(isset($_POST['csrf_token'])?$_POST['csrf_token']:''))exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	if($conf['forcelogin']==1 && !$islogin2 && empty($_SESSION['upload']['invite_token']))exit('{"code":-1,"msg":"请先登录"}');
	$chunk = intval($_POST['chunk']);
	$hash = trim($_POST['hash']);
	if(!$_SESSION['upload'] || !$_SESSION['upload']['hash'] || $_SESSION['upload']['hash']!=$hash){
		exit('{"code":-1,"msg":"参数校验失败，请刷新页面重试"}');
	}
	$inviteCheck = revalidateUploadInviteSession();
	if(!$inviteCheck['ok']){
		unset($_SESSION['upload']);
		exit(json_encode(['code'=>-1, 'msg'=>$inviteCheck['msg']]));
	}
	if(!preg_match('/^[0-9a-z]{32}$/i', $hash))exit('{"code":-1,"msg":"hash error"}');
	$chunks = intval($_SESSION['upload']['chunks']);
	if($chunk < 1 || $chunk > $chunks){
		exit('{"code":-1,"msg":"分块序号错误","error":"chunk_range"}');
	}
	$ext = $_SESSION['upload']['ext'];
	if($chunks > 1){
		$tempFile = sys_get_temp_dir() . '/' . $hash. '.part'.$chunk;
		if(!move_uploaded_file($_FILES['file']['tmp_name'], $tempFile)){
			exit('{"code":-1,"msg":"文件第'.$chunk.'分块上传失败"}');
		}
		if($chunks == $chunk){
			$savePathTemp = file_part_merge($hash, $chunks);
			$real_hash = md5_file($savePathTemp);
			$real_size = filesize($savePathTemp);
			$result = $stor->savefile($hash, $savePathTemp, minetype($ext));
			if(!$result)exit('{"code":-1,"msg":"文件上传失败","error":"stor","errmsg":"'.$stor->errmsg().'"}');
		}else{
			$result = ['code'=>0, 'chunk'=>$chunk];
			exit(json_encode($result));
		}
	}else{
		$real_hash = md5_file($_FILES['file']['tmp_name']);
		$real_size = filesize($_FILES['file']['tmp_name']);
		$result = $stor->upload($hash, $_FILES['file']['tmp_name'], minetype($ext));
		if(!$result)exit('{"code":-1,"msg":"文件上传失败","error":"stor","errmsg":"'.$stor->errmsg().'"}');
	}

	$size = $_SESSION['upload']['size'];
	if($real_size != $size){
		exit('{"code":-1,"msg":"文件大小校验失败"}');
	}
	if($real_hash != $hash){
		exit('{"code":-1,"msg":"文件MD5校验失败"}');
	}

	$name = $_SESSION['upload']['name'];
	$pwd = $_SESSION['upload']['pwd'];
	$folder_id = $_SESSION['upload']['folder_id'];
	$remark = isset($_SESSION['upload']['remark']) ? $_SESSION['upload']['remark'] : null;

	// 同名覆盖逻辑
	if(!empty($_SESSION['upload']['overwrite_id'])){
		$overwrite_id = $_SESSION['upload']['overwrite_id'];
		$old_hash = $_SESSION['upload']['old_hash'];
		$DB->exec("UPDATE pre_file SET hash=:hash, size=:size, type=:type, addtime=NOW(), ip=:ip, pwd=:pwd WHERE id=:id", [':hash'=>$hash, ':size'=>$size, ':type'=>$ext, ':ip'=>$clientip, ':pwd'=>$pwd, ':id'=>$overwrite_id]);
		if(!empty($_SESSION['upload']['invite_token'])){
			completeUploadInvite($_SESSION['upload']['invite_token']);
		}
		$refCount = $DB->getColumn("SELECT count(*) FROM pre_file WHERE hash=:hash", [':hash'=>$old_hash]);
		if(intval($refCount) === 0){
			$stor->delete($old_hash);
		}
		unset($_SESSION['upload']);
		$result = ['code'=>1, 'msg'=>'文件更新成功', 'exists'=>1, 'hash'=>$hash, 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$overwrite_id];
		exit(json_encode($result));
	}

	$row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash", [':hash'=>$hash]);
	if($row){
		$file_id = createFileReference($name, $ext, $size, $hash, $clientip, $pwd, $uploadUid, $folder_id, $remark);
		if(!empty($_SESSION['upload']['invite_token'])){
			completeUploadInvite($_SESSION['upload']['invite_token']);
		}else{
			$_SESSION['fileids'][] = $file_id;
		}
		unset($_SESSION['upload']);
		$result = ['code'=>1, 'msg'=>'本站已存在该文件', 'exists'=>1, 'hash'=>$hash, 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$file_id];
		exit(json_encode($result));
	}

	$sds = $DB->exec("INSERT INTO `pre_file` (`name`,`type`,`size`,`hash`,`addtime`,`ip`,`pwd`,`uid`,`folder_id`,`remark`) values (:name,:type,:size,:hash,NOW(),:ip,:pwd,:uid,:folder_id,:remark)", [':name'=>$name, ':type'=>$ext, ':size'=>$size, ':hash'=>$hash, ':ip'=>$clientip, ':pwd'=>$pwd, ':uid'=>$uploadUid, ':folder_id'=>$folder_id, ':remark'=>$remark]);
	if(!$sds)exit('{"code":-1,"msg":"上传失败'.$DB->error().'","error":"database"}');
	$id = $DB->lastInsertId();

	$type_image = explode('|',$conf['type_image']);
	$type_video = explode('|',$conf['type_video']);
	if($conf['green_check']>0 && in_array($ext,$type_image)){
		if(checkImage($hash, $ext)){
			$DB->exec("UPDATE `pre_file` SET `block`=1 WHERE `id`='{$id}' LIMIT 1");
		}
	}
	if($conf['videoreview']==1 && in_array($ext,$type_video)){
		$DB->exec("UPDATE `pre_file` SET `block`=2 WHERE `id`='{$id}' LIMIT 1");
	}

	if(!empty($_SESSION['upload']['invite_token'])){
		completeUploadInvite($_SESSION['upload']['invite_token']);
	}else{
		$_SESSION['fileids'][] = $id;
	}
	unset($_SESSION['upload']);
	$result = ['code'=>1, 'msg'=>'文件上传成功！', 'exists'=>0, 'hash'=>$hash, 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$id];
	exit(json_encode($result));
break;

case 'complete_upload':
	if(!checkCsrfToken(isset($_POST['csrf_token'])?$_POST['csrf_token']:''))exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	if($conf['forcelogin']==1 && !$islogin2 && empty($_SESSION['upload']['invite_token']))exit('{"code":-1,"msg":"请先登录"}');
	$hash = trim($_POST['hash']);
	if(!$_SESSION['upload'] || !$_SESSION['upload']['hash'] || $_SESSION['upload']['hash']!=$hash){
		exit('{"code":-1,"msg":"参数校验失败，请刷新页面重试"}');
	}
	$inviteCheck = revalidateUploadInviteSession();
	if(!$inviteCheck['ok']){
		unset($_SESSION['upload']);
		exit(json_encode(['code'=>-1, 'msg'=>$inviteCheck['msg']]));
	}
	if(!preg_match('/^[0-9a-z]{32}$/i', $hash))exit('{"code":-1,"msg":"hash error"}');
	// 用户容量限制检查（二次校验）
	$uploadUid = isset($_SESSION['upload']['uid']) ? intval($_SESSION['upload']['uid']) : ($uid ? $uid : 0);
	if($uploadUid && isset($_SESSION['upload']['size']) && !checkUserStorageLimit($uploadUid, $_SESSION['upload']['size'])){
		$used = getUserUsedStorage($uploadUid);
		$userLimit = $DB->getRow("SELECT storage_limit FROM pre_user WHERE uid=:uid", [':uid'=>$uploadUid]);
		$limitStr = formatStorageSize(intval($userLimit['storage_limit']));
		$usedStr = formatStorageSize($used);
		exit('{"code":-1,"msg":"您的存储空间不足（已用'.$usedStr.' / 总共'.$limitStr.'），无法完成上传","error":"storage_limit"}');
	}

	if(!$stor->exists($hash)){
		exit('{"code":-1,"msg":"文件上传失败","error":"stor","errmsg":"'.$stor->errmsg().'"}');
	}

	$name = $_SESSION['upload']['name'];
	$size = $_SESSION['upload']['size'];
	$ext = $_SESSION['upload']['ext'];
	$pwd = $_SESSION['upload']['pwd'];
	$folder_id = $_SESSION['upload']['folder_id'];
	$remark = isset($_SESSION['upload']['remark']) ? $_SESSION['upload']['remark'] : null;

	// 同名覆盖逻辑
	if(!empty($_SESSION['upload']['overwrite_id'])){
		$overwrite_id = $_SESSION['upload']['overwrite_id'];
		$old_hash = $_SESSION['upload']['old_hash'];
		$DB->exec("UPDATE pre_file SET hash=:hash, size=:size, type=:type, addtime=NOW(), ip=:ip, pwd=:pwd WHERE id=:id", [':hash'=>$hash, ':size'=>$size, ':type'=>$ext, ':ip'=>$clientip, ':pwd'=>$pwd, ':id'=>$overwrite_id]);
		if(!empty($_SESSION['upload']['invite_token'])){
			completeUploadInvite($_SESSION['upload']['invite_token']);
		}
		$refCount = $DB->getColumn("SELECT count(*) FROM pre_file WHERE hash=:hash", [':hash'=>$old_hash]);
		if(intval($refCount) === 0){
			$stor->delete($old_hash);
		}
		unset($_SESSION['upload']);
		$result = ['code'=>1, 'msg'=>'文件更新成功', 'exists'=>1, 'hash'=>$hash, 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$overwrite_id];
		exit(json_encode($result));
	}

	$row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash", [':hash'=>$hash]);
	if($row){
		$file_id = createFileReference($name, $ext, $size, $hash, $clientip, $pwd, $uploadUid, $folder_id, $remark);
		if(!empty($_SESSION['upload']['invite_token'])){
			completeUploadInvite($_SESSION['upload']['invite_token']);
		}else{
			$_SESSION['fileids'][] = $file_id;
		}
		unset($_SESSION['upload']);
		$result = ['code'=>1, 'msg'=>'本站已存在该文件', 'exists'=>1, 'hash'=>$hash, 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$file_id];
		exit(json_encode($result));
	}

	$sds = $DB->exec("INSERT INTO `pre_file` (`name`,`type`,`size`,`hash`,`addtime`,`ip`,`pwd`,`uid`,`folder_id`,`remark`) values (:name,:type,:size,:hash,NOW(),:ip,:pwd,:uid,:folder_id,:remark)", [':name'=>$name, ':type'=>$ext, ':size'=>$size, ':hash'=>$hash, ':ip'=>$clientip, ':pwd'=>$pwd, ':uid'=>$uploadUid, ':folder_id'=>$folder_id, ':remark'=>$remark]);
	if(!$sds)exit('{"code":-1,"msg":"上传失败'.$DB->error().'","error":"database"}');
	$id = $DB->lastInsertId();

	$type_image = explode('|',$conf['type_image']);
	$type_video = explode('|',$conf['type_video']);
	if($conf['green_check']>0 && in_array($ext,$type_image)){
		if(checkImage($hash, $ext)){
			$DB->exec("UPDATE `pre_file` SET `block`=1 WHERE `id`='{$id}' LIMIT 1");
		}
	}
	if($conf['videoreview']==1 && in_array($ext,$type_video)){
		$DB->exec("UPDATE `pre_file` SET `block`=2 WHERE `id`='{$id}' LIMIT 1");
	}

	if(!empty($_SESSION['upload']['invite_token'])){
		completeUploadInvite($_SESSION['upload']['invite_token']);
	}else{
		$_SESSION['fileids'][] = $id;
	}
	unset($_SESSION['upload']);
	$result = ['code'=>1, 'msg'=>'文件上传成功！', 'exists'=>0, 'hash'=>$hash, 'name'=>$name, 'size'=>$size, 'type'=>$ext, 'id'=>$id];
	exit(json_encode($result));
break;

case 'deleteFile':
	$file_id = isset($_POST['file_id'])?intval($_POST['file_id']):0;
	$hash = isset($_POST['hash'])?trim($_POST['hash']):'';
	if(!checkCsrfToken(isset($_POST['csrf_token'])?$_POST['csrf_token']:''))exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	if($file_id > 0){
		$row = $DB->getRow("SELECT * FROM `pre_file` WHERE `id`=:id", [':id'=>$file_id]);
	}else{
		if(empty($hash))exit('{"code":-1,"msg":"no hash"}');
		if(!preg_match('/^[0-9a-z]{32}$/i', $hash))exit('{"code":-1,"msg":"hash error"}');
		$row = getDeletableFileByHash($hash);
	}
	if(!$row)exit('{"code":-1,"msg":"文件不存在"}');
	$hash = $row['hash'];
	if($islogin2 && $row['uid']!=$uid || !$islogin2 && (!isset($_SESSION['fileids']) || !in_array($row['id'], $_SESSION['fileids'])))exit('{"code":-1,"msg":"无权限"}');
	if($row['block']==1)exit('{"code":-1,"msg":"文件已被冻结，无法删除"}');
	if(!$islogin2 && strtotime($row['addtime'])<strtotime("-7 days"))exit('{"code":-1,"msg":"无法删除7天前的文件"}');
	$sql = "DELETE FROM pre_file WHERE id=:id";
	if($DB->exec($sql, [':id'=>$row['id']])){
		$refCount = $DB->getColumn("SELECT count(*) FROM pre_file WHERE hash=:hash", [':hash'=>$hash]);
		if(intval($refCount) === 0){
			$stor->delete($hash);
		}
		exit('{"code":0,"msg":"删除文件成功！"}');
	}
	else exit('{"code":-1,"msg":"删除文件失败['.$DB->error().']"}');
break;

case 'listFolder':
	if(!$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	$parent_id = isset($_GET['parent_id'])?intval($_GET['parent_id']):0;
	$list = $DB->getAll("SELECT * FROM pre_folder WHERE uid=:uid AND parent_id=:parent_id ORDER BY id DESC", [':uid'=>$uid, ':parent_id'=>$parent_id]);
	exit(json_encode(['code'=>0, 'data'=>$list]));
break;

case 'createFolder':
	if(!$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	$name = isset($_POST['name'])?trim(htmlspecialchars($_POST['name'])):'';
	$parent_id = isset($_POST['parent_id'])?intval($_POST['parent_id']):0;
	if(empty($name))exit('{"code":-1,"msg":"目录名不能为空"}');
	if(strlen($name)>255)exit('{"code":-1,"msg":"目录名过长"}');
	if($parent_id>0){
		$prow = $DB->getRow("SELECT * FROM pre_folder WHERE id=:id AND uid=:uid", [':id'=>$parent_id, ':uid'=>$uid]);
		if(!$prow)exit('{"code":-1,"msg":"父目录不存在"}');
	}
	$exists = $DB->getRow("SELECT * FROM pre_folder WHERE uid=:uid AND parent_id=:parent_id AND name=:name", [':uid'=>$uid, ':parent_id'=>$parent_id, ':name'=>$name]);
	if($exists)exit('{"code":-1,"msg":"该目录下已存在同名文件夹"}');
	$id = $DB->insert('folder', [
		'uid' => $uid,
		'parent_id' => $parent_id,
		'name' => $name,
		'addtime' => 'NOW()',
	]);
	if(!$id)exit('{"code":-1,"msg":"创建失败'.$DB->error().'"}');
	exit(json_encode(['code'=>0, 'msg'=>'创建成功', 'id'=>$id]));
break;

case 'renameFolder':
	if(!$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	$id = isset($_POST['id'])?intval($_POST['id']):0;
	$name = isset($_POST['name'])?trim(htmlspecialchars($_POST['name'])):'';
	if($id<=0 || empty($name))exit('{"code":-1,"msg":"参数错误"}');
	$row = $DB->getRow("SELECT * FROM pre_folder WHERE id=:id AND uid=:uid", [':id'=>$id, ':uid'=>$uid]);
	if(!$row)exit('{"code":-1,"msg":"目录不存在"}');
	$exists = $DB->getRow("SELECT * FROM pre_folder WHERE uid=:uid AND parent_id=:parent_id AND name=:name AND id!=:id", [':uid'=>$uid, ':parent_id'=>$row['parent_id'], ':name'=>$name, ':id'=>$id]);
	if($exists)exit('{"code":-1,"msg":"该目录下已存在同名文件夹"}');
	$DB->update('folder', ['name'=>$name], ['id'=>$id]);
	exit(json_encode(['code'=>0, 'msg'=>'重命名成功']));
break;

case 'deleteFolder':
	if(!$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	$id = isset($_POST['id'])?intval($_POST['id']):0;
	if($id<=0)exit('{"code":-1,"msg":"参数错误"}');
	$row = $DB->getRow("SELECT * FROM pre_folder WHERE id=:id AND uid=:uid", [':id'=>$id, ':uid'=>$uid]);
	if(!$row)exit('{"code":-1,"msg":"目录不存在"}');

	$folderIds = getSubFolderIds($id, $uid, $DB);

	// 删除这些目录下的所有文件
	if(count($folderIds) > 0){
		$placeholders = implode(',', array_fill(0, count($folderIds), '?'));
		$files = $DB->getAll("SELECT id, hash FROM pre_file WHERE folder_id IN ($placeholders) AND uid=?", array_merge($folderIds, [$uid]));
		foreach($files as $file){
			$DB->exec("DELETE FROM pre_file WHERE id=?", [$file['id']]);
			$refCount = $DB->getColumn("SELECT count(*) FROM pre_file WHERE hash=?", [$file['hash']]);
			if(intval($refCount) === 0){
				$stor->delete($file['hash']);
			}
		}
	}

	// 删除所有目录记录（从最深的子目录开始）
	rsort($folderIds);
	foreach($folderIds as $fid){
		$DB->exec("DELETE FROM pre_folder WHERE id=?", [$fid]);
	}

	exit(json_encode(['code'=>0, 'msg'=>'删除成功']));
break;

case 'moveFile':
	if(!$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	if(!checkCsrfToken(isset($_POST['csrf_token'])?$_POST['csrf_token']:''))exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	$file_id = isset($_POST['file_id'])?intval($_POST['file_id']):0;
	$hash = isset($_POST['hash'])?trim($_POST['hash']):'';
	$folder_id = isset($_POST['folder_id'])?intval($_POST['folder_id']):0;
	if($file_id > 0){
		$row = $DB->getRow("SELECT * FROM pre_file WHERE id=:id AND uid=:uid", [':id'=>$file_id, ':uid'=>$uid]);
	}else{
		if(empty($hash))exit('{"code":-1,"msg":"参数错误"}');
		$row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash AND uid=:uid", [':hash'=>$hash, ':uid'=>$uid]);
	}
	if(!$row)exit('{"code":-1,"msg":"文件不存在或无权限"}');
	if($folder_id>0){
		$frow = $DB->getRow("SELECT * FROM pre_folder WHERE id=:id AND uid=:uid", [':id'=>$folder_id, ':uid'=>$uid]);
		if(!$frow)exit('{"code":-1,"msg":"目标目录不存在"}');
	}
	$DB->update('file', ['folder_id'=>$folder_id], ['id'=>$row['id']]);
	exit(json_encode(['code'=>0, 'msg'=>'移动成功']));
break;

case 'listMine':
	if(!$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	$global_search = isset($_GET['global_search']) && $_GET['global_search'] == '1';
	$folder_id = isset($_GET['folder_id'])?intval($_GET['folder_id']):0;
	if($global_search){
		$folder_id = 0;
	}
	if($folder_id>0){
		$frow = $DB->getRow("SELECT * FROM pre_folder WHERE id=:id AND uid=:uid", [':id'=>$folder_id, ':uid'=>$uid]);
		if(!$frow) $folder_id = 0;
	}
	$kw = isset($_GET['kw'])?trim($_GET['kw']):'';
	if(strlen($kw) > 100)$kw = substr($kw, 0, 100);
	$search_like = '';
	if($kw !== ''){
		$search_like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $kw).'%';
	}
	// 搜索时获取当前目录及所有子目录ID
	$search_folder_ids = $search_like !== '' ? getSubFolderIds($folder_id, $uid, $DB) : [$folder_id];
	$folder_placeholders = implode(',', array_fill(0, count($search_folder_ids), '?'));
	
	if($search_like !== ''){
		// 有搜索关键词时，搜索匹配的文件夹
		$folder_params = array_merge([$uid], $search_folder_ids, [$search_like]);
		$folders = $DB->getAll("SELECT id, name, addtime FROM pre_folder WHERE uid=? AND parent_id IN ($folder_placeholders) AND name LIKE ? ORDER BY id DESC", $folder_params);
	}else{
		// 无搜索关键词时，只获取当前目录的直接子文件夹
		$folders = $DB->getAll("SELECT id, name, addtime FROM pre_folder WHERE uid=:uid AND parent_id=:parent_id ORDER BY id DESC", [':uid'=>$uid, ':parent_id'=>$folder_id]);
	}

	$type = isset($_GET['type'])?trim($_GET['type']):'';
	$type_condition = '';
	$params = [$uid];
	$file_folder_placeholders = $folder_placeholders;
	$params = array_merge($params, $search_folder_ids);
	
	if(!empty($type)){
		$type_image = explode('|', $conf['type_image']);
		$type_video = explode('|', $conf['type_video']);
		$type_audio = ['mp3','wav','ogg','flac','aac','m4a'];
		$type_document = ['doc','docx','pdf','txt','xls','xlsx','ppt','pptx'];
		$type_map = [
			'image' => $type_image,
			'video' => $type_video,
			'audio' => $type_audio,
			'document' => $type_document,
		];
		if(isset($type_map[$type])){
			$types = array_map(function($t){ return strtolower($t); }, $type_map[$type]);
			$placeholders = implode(',', array_fill(0, count($types), '?'));
			$type_condition = " AND `type` IN ($placeholders)";
			$params = array_merge($params, $types);
		}elseif($type == 'other'){
			$all_types = array_merge($type_image, $type_video, $type_audio, $type_document);
			$all_types = array_map(function($t){ return strtolower($t); }, $all_types);
			$placeholders = implode(',', array_fill(0, count($all_types), '?'));
			$type_condition = " AND `type` NOT IN ($placeholders)";
			$params = array_merge($params, $all_types);
		}
	}

	$search_condition = '';
	if($search_like !== ''){
		$search_condition = " AND (`name` LIKE ? OR `remark` LIKE ?)";
		$params[] = $search_like;
		$params[] = $search_like;
	}
	$files = $DB->getAll("SELECT id, name, type, size, hash, addtime, count, remark FROM pre_file WHERE uid=? AND folder_id IN ($file_folder_placeholders) $type_condition $search_condition ORDER BY id DESC", $params);
	$path = getFolderPath($folder_id, $uid);
	
	// 判断是否需要建议全局搜索（当前目录搜索无结果且不是全局搜索模式）
	$suggest_global = false;
	if(!$global_search && $kw !== '' && count($files) == 0 && count($folders) == 0){
		$suggest_global = true;
	}
	
	exit(json_encode([
		'code'=>0, 
		'folders'=>$folders, 
		'files'=>$files, 
		'path'=>$path, 
		'folder_id'=>$folder_id, 
		'keyword'=>$kw,
		'version'=>getMineFolderVersion($folder_id, $uid),
		'is_global_search'=>$global_search,
		'suggest_global'=>$suggest_global
	]));
break;

case 'mineFolderVersion':
	if(!$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	$folder_id = isset($_GET['folder_id'])?intval($_GET['folder_id']):0;
	if($folder_id>0){
		$frow = $DB->getRow("SELECT id FROM pre_folder WHERE id=:id AND uid=:uid", [':id'=>$folder_id, ':uid'=>$uid]);
		if(!$frow) $folder_id = 0;
	}
	$since = isset($_GET['since']) ? strval($_GET['since']) : '';
	$wait = isset($_GET['wait']) ? intval($_GET['wait']) : 0;
	$maxWaitSeconds = 25;
	$startedAt = time();
	if($wait > 0){
		@set_time_limit($maxWaitSeconds + 5);
		if(function_exists('session_write_close'))@session_write_close();
	}
	do{
		$version = getMineFolderVersion($folder_id, $uid);
		$changed = $since === '' || $version !== $since;
		if($changed || $wait <= 0){
			exit(json_encode(['code'=>0, 'folder_id'=>$folder_id, 'version'=>$version, 'changed'=>$changed]));
		}
		if(connection_aborted())exit;
		usleep(1000000);
	}while(time() - $startedAt < $maxWaitSeconds);
	$version = getMineFolderVersion($folder_id, $uid);
	$changed = $since === '' || $version !== $since;
	exit(json_encode(['code'=>0, 'folder_id'=>$folder_id, 'version'=>$version, 'changed'=>$changed]));
break;

case 'createShare':
	if(!$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	$folder_id = isset($_POST['folder_id'])?intval($_POST['folder_id']):0;
	$pwd = isset($_POST['pwd'])?trim(htmlspecialchars($_POST['pwd'])):'';
	if($folder_id<=0)exit('{"code":-1,"msg":"参数错误"}');
	$row = $DB->getRow("SELECT * FROM pre_folder WHERE id=:id AND uid=:uid", [':id'=>$folder_id, ':uid'=>$uid]);
	if(!$row)exit('{"code":-1,"msg":"目录不存在"}');
	if(!empty($pwd)){
		if(!preg_match('/^[a-zA-Z0-9]+$/', $pwd)){
			exit('{"code":-1,"msg":"密码只能为字母和数字"}');
		}
	}
	$exist = $DB->getRow("SELECT * FROM pre_share WHERE folder_id=:folder_id AND uid=:uid", [':folder_id'=>$folder_id, ':uid'=>$uid]);
	if($exist){
		$token = $exist['token'];
		$DB->exec("UPDATE pre_share SET pwd=:pwd WHERE id=:id", [':pwd'=>!empty($pwd)?$pwd:null, ':id'=>$exist['id']]);
	}else{
		$token = md5(uniqid().mt_rand(0,999).time());
		$DB->exec("INSERT INTO pre_share (token, folder_id, uid, pwd, addtime, views) VALUES (:token, :folder_id, :uid, :pwd, NOW(), 0)", [':token'=>$token, ':folder_id'=>$folder_id, ':uid'=>$uid, ':pwd'=>!empty($pwd)?$pwd:null]);
	}
	$shareurl = $siteurl.'share.php?token='.$token;
	exit(json_encode(['code'=>0, 'msg'=>'分享成功', 'url'=>$shareurl, 'token'=>$token]));
break;

case 'createUploadInvite':
	if(!$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	if(!checkCsrfToken(isset($_POST['csrf_token'])?$_POST['csrf_token']:''))exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	$folder_id = isset($_POST['folder_id'])?intval($_POST['folder_id']):0;
	if($folder_id < 0)$folder_id = 0;
	if($folder_id > 0){
		$row = $DB->getRow("SELECT * FROM pre_folder WHERE id=:id AND uid=:uid", [':id'=>$folder_id, ':uid'=>$uid]);
		if(!$row)exit('{"code":-1,"msg":"目录不存在"}');
	}
	$pwd = generateUploadInvitePwd();
	$max_size = parseUploadInviteMaxSize(isset($_POST['max_size_mb'])?$_POST['max_size_mb']:1024);
	$expire_time = parseUploadInviteExpireTime(isset($_POST['expire_hours'])?$_POST['expire_hours']:0);
	if($expire_time === false)exit('{"code":-1,"msg":"有效时长必须为非负整数"}');
	$remark = parseUploadInviteRemark(isset($_POST['remark'])?$_POST['remark']:'');
	$exist = $DB->getRow("SELECT * FROM pre_upload_invite WHERE folder_id=:folder_id AND uid=:uid", [':folder_id'=>$folder_id, ':uid'=>$uid]);
	if($exist){
		$token = $exist['token'];
		if($DB->exec("UPDATE pre_upload_invite SET pwd=:pwd, max_size=:max_size, expire_time=:expire_time, remark=:remark, enable=1, addtime=NOW(), fail_count=0, last_failtime=NULL WHERE id=:id", [':pwd'=>$pwd, ':max_size'=>$max_size, ':expire_time'=>$expire_time, ':remark'=>$remark, ':id'=>$exist['id']]) === false){
			exitUploadInviteDatabaseError();
		}
	}else{
		$token = generateUploadInviteToken();
		if($DB->exec("INSERT INTO pre_upload_invite (token, folder_id, uid, pwd, max_size, expire_time, remark, enable, addtime, uploads, fail_count, last_failtime) VALUES (:token, :folder_id, :uid, :pwd, :max_size, :expire_time, :remark, 1, NOW(), 0, 0, NULL)", [':token'=>$token, ':folder_id'=>$folder_id, ':uid'=>$uid, ':pwd'=>$pwd, ':max_size'=>$max_size, ':expire_time'=>$expire_time, ':remark'=>$remark]) === false){
			exitUploadInviteDatabaseError();
		}
	}
	$inviteurl = $siteurl.'invite_upload.php?token='.$token.'&pwd='.$pwd;
	exit(json_encode(['code'=>0, 'msg'=>'邀请链接已生成', 'url'=>$inviteurl, 'token'=>$token, 'pwd'=>$pwd, 'max_size'=>$max_size, 'expire_time'=>$expire_time, 'remark'=>$remark]));
break;

case 'listMyUploadInvites':
	if(!$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	$invites = $DB->getAll("SELECT i.id, i.token, i.folder_id, i.pwd, i.max_size, i.expire_time, i.remark, i.enable, i.addtime, i.uploads, f.name as folder_name FROM pre_upload_invite i LEFT JOIN pre_folder f ON i.folder_id=f.id WHERE i.uid=:uid ORDER BY i.id DESC", [':uid'=>$uid]);
	foreach($invites as &$invite){
		$invite['inviteurl'] = $siteurl.'invite_upload.php?token='.$invite['token'].'&pwd='.$invite['pwd'];
		if(intval($invite['folder_id']) === 0){
			$invite['folder_name'] = '根目录';
		}
		$invite['max_size_mb'] = intval($invite['max_size']) > 0 ? round(intval($invite['max_size']) / 1024 / 1024, 2) : 0;
		$invite['expired'] = !empty($invite['expire_time']) && strtotime($invite['expire_time']) < time();
		$invite['status_text'] = intval($invite['uploads']) > 0 ? '已完成' : (intval($invite['enable']) === 1 ? ($invite['expired'] ? '已过期' : '有效') : '已停用');
	}
	exit(json_encode(['code'=>0, 'data'=>$invites]));
break;

case 'updateUploadInvite':
	if(!$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	if(!checkCsrfToken(isset($_POST['csrf_token'])?$_POST['csrf_token']:''))exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	$id = isset($_POST['id'])?intval($_POST['id']):0;
	if($id<=0)exit('{"code":-1,"msg":"参数错误"}');
	$row = $DB->getRow("SELECT * FROM pre_upload_invite WHERE id=:id AND uid=:uid", [':id'=>$id, ':uid'=>$uid]);
	if(!$row)exit('{"code":-1,"msg":"邀请不存在"}');
	$max_size = parseUploadInviteMaxSize(isset($_POST['max_size_mb'])?$_POST['max_size_mb']:1024);
	$remark = parseUploadInviteRemark(isset($_POST['remark'])?$_POST['remark']:$row['remark']);
	$expire_time = $row['expire_time'];
	if(array_key_exists('expire_hours', $_POST) && $_POST['expire_hours'] !== ''){
		$expire_time = parseUploadInviteExpireTime($_POST['expire_hours']);
		if($expire_time === false)exit('{"code":-1,"msg":"有效时长必须为非负整数"}');
	}
	$enable = isset($_POST['enable']) ? intval($_POST['enable']) : 1;
	$enable = $enable === 1 ? 1 : 0;
	if($DB->exec("UPDATE pre_upload_invite SET max_size=:max_size, expire_time=:expire_time, remark=:remark, enable=:enable WHERE id=:id AND uid=:uid", [':max_size'=>$max_size, ':expire_time'=>$expire_time, ':remark'=>$remark, ':enable'=>$enable, ':id'=>$id, ':uid'=>$uid]) === false){
		exitUploadInviteDatabaseError();
	}
	exit(json_encode(['code'=>0, 'msg'=>'邀请设置已更新']));
break;

case 'toggleUploadInvite':
	if(!$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	if(!checkCsrfToken(isset($_POST['csrf_token'])?$_POST['csrf_token']:''))exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	$id = isset($_POST['id'])?intval($_POST['id']):0;
	$enable = isset($_POST['enable']) ? intval($_POST['enable']) : 1;
	$enable = $enable === 1 ? 1 : 0;
	if($id<=0)exit('{"code":-1,"msg":"参数错误"}');
	$row = $DB->getRow("SELECT id FROM pre_upload_invite WHERE id=:id AND uid=:uid", [':id'=>$id, ':uid'=>$uid]);
	if(!$row)exit('{"code":-1,"msg":"邀请不存在"}');
	$DB->exec("UPDATE pre_upload_invite SET enable=:enable WHERE id=:id AND uid=:uid", [':enable'=>$enable, ':id'=>$id, ':uid'=>$uid]);
	exit(json_encode(['code'=>0, 'msg'=>$enable ? '邀请已启用' : '邀请已停用']));
break;

case 'deleteUploadInvite':
	if(!$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	if(!checkCsrfToken(isset($_POST['csrf_token'])?$_POST['csrf_token']:''))exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	$id = isset($_POST['id'])?intval($_POST['id']):0;
	if($id<=0)exit('{"code":-1,"msg":"参数错误"}');
	$row = $DB->getRow("SELECT id FROM pre_upload_invite WHERE id=:id AND uid=:uid", [':id'=>$id, ':uid'=>$uid]);
	if(!$row)exit('{"code":-1,"msg":"邀请不存在"}');
	$DB->exec("DELETE FROM pre_upload_invite WHERE id=:id AND uid=:uid", [':id'=>$id, ':uid'=>$uid]);
	exit(json_encode(['code'=>0, 'msg'=>'邀请已删除']));
break;

case 'checkSharePwd':
	$token = isset($_POST['token'])?trim($_POST['token']):'';
	$pwd = isset($_POST['pwd'])?trim($_POST['pwd']):'';
	if(empty($token) || !preg_match('/^[0-9a-z]{32}$/i', $token))exit('{"code":-1,"msg":"分享链接无效"}');
	$share = $DB->getRow("SELECT * FROM pre_share WHERE token=:token", [':token'=>$token]);
	if(!$share)exit('{"code":-1,"msg":"分享不存在或已失效"}');
	if(!empty($share['pwd']) && $share['pwd'] != $pwd){
		exit('{"code":-1,"msg":"密码错误"}');
	}
	$rootFolderId = $share['folder_id'];
	$currentFolderId = isset($_POST['fid'])?intval($_POST['fid']):$rootFolderId;
	if($currentFolderId != $rootFolderId){
		$allSubIds = getSubFolderIds($rootFolderId, $share['uid'], $DB);
		if(!in_array($currentFolderId, $allSubIds)){
			exit('{"code":-1,"msg":"目录不存在"}');
		}
	}
	$folders = $DB->getAll("SELECT id, name, addtime FROM pre_folder WHERE parent_id=:parent_id AND uid=:uid ORDER BY id DESC", [':parent_id'=>$currentFolderId, ':uid'=>$share['uid']]);
	$files = $DB->getAll("SELECT id, name, type, size, hash, addtime, count FROM pre_file WHERE folder_id=:folder_id ORDER BY id DESC", [':folder_id'=>$currentFolderId]);
	exit(json_encode(['code'=>0, 'files'=>$files, 'folders'=>$folders]));
break;

case 'saveShare':
	if(!checkCsrfToken(isset($_POST['csrf_token'])?$_POST['csrf_token']:''))exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	if(!$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	$token = isset($_POST['token'])?trim($_POST['token']):'';
	$pwd = isset($_POST['pwd'])?trim($_POST['pwd']):'';
	$folder_id = isset($_POST['folder_id'])?intval($_POST['folder_id']):0;
	if(empty($token) || !preg_match('/^[0-9a-z]{32}$/i', $token))exit('{"code":-1,"msg":"分享链接无效"}');
	$share = $DB->getRow("SELECT * FROM pre_share WHERE token=:token", [':token'=>$token]);
	if(!$share)exit('{"code":-1,"msg":"分享不存在或已失效"}');
	if(!empty($share['pwd']) && $share['pwd'] != $pwd){
		exit('{"code":-1,"msg":"密码错误"}');
	}
	if($share['uid'] == $uid)exit('{"code":-1,"msg":"不能转存自己的分享"}');
	if($folder_id>0){
		$frow = $DB->getRow("SELECT * FROM pre_folder WHERE id=:id AND uid=:uid", [':id'=>$folder_id, ':uid'=>$uid]);
		if(!$frow)exit('{"code":-1,"msg":"目标目录不存在"}');
	}

	// 获取分享的完整文件夹树并递归创建
	$tree = getShareFolderTree($share['folder_id'], $share['uid'], $DB);
	$folderMap = [];
	$folderMap[$share['folder_id']] = $folder_id;
	createFoldersRecursively($tree, $folder_id, $uid, $DB, $folderMap);

	// 获取所有分享目录下的文件（根+所有子目录）
	$allFolderIds = array_keys($folderMap);
	$placeholders = implode(',', array_fill(0, count($allFolderIds), '?'));
	$files = $DB->getAll("SELECT * FROM pre_file WHERE folder_id IN ($placeholders)", $allFolderIds);
	if(count($files) == 0)exit('{"code":-1,"msg":"该分享下没有文件"}');
	$savedCount = 0;
	foreach($files as $f){
		$targetFolderId = isset($folderMap[$f['folder_id']]) ? $folderMap[$f['folder_id']] : $folder_id;
		$exist = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash AND uid=:uid AND folder_id=:folder_id", [':hash'=>$f['hash'], ':uid'=>$uid, ':folder_id'=>$targetFolderId]);
		if($exist) continue;
		$sds = $DB->exec("INSERT INTO `pre_file` (`name`,`type`,`size`,`hash`,`addtime`,`ip`,`pwd`,`uid`,`folder_id`) values (:name,:type,:size,:hash,NOW(),:ip,:pwd,:uid,:folder_id)", [':name'=>$f['name'], ':type'=>$f['type'], ':size'=>$f['size'], ':hash'=>$f['hash'], ':ip'=>$clientip, ':pwd'=>null, ':uid'=>$uid, ':folder_id'=>$targetFolderId]);
		if($sds) $savedCount++;
	}
	exit(json_encode(['code'=>0, 'msg'=>'转存成功，共转存'.$savedCount.'个文件', 'count'=>$savedCount]));
break;

case 'deleteShare':
	if(!$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	$id = isset($_POST['id'])?intval($_POST['id']):0;
	if($id<=0)exit('{"code":-1,"msg":"参数错误"}');
	$DB->exec("DELETE FROM pre_share WHERE id=:id AND uid=:uid", [':id'=>$id, ':uid'=>$uid]);
	exit(json_encode(['code'=>0, 'msg'=>'删除成功']));
break;

case 'listMyShares':
	if(!$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	$shares = $DB->getAll("SELECT s.id, s.token, s.folder_id, s.pwd, s.addtime, s.views, f.name as folder_name FROM pre_share s LEFT JOIN pre_folder f ON s.folder_id=f.id WHERE s.uid=:uid ORDER BY s.id DESC", [':uid'=>$uid]);
	foreach($shares as &$share){
		$share['shareurl'] = $siteurl.'share.php?token='.$share['token'];
		$share['has_pwd'] = !empty($share['pwd']);
	}
	exit(json_encode(['code'=>0, 'data'=>$shares]));
break;

case 'updateSharePwd':
	if(!$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	if(!checkCsrfToken(isset($_POST['csrf_token'])?$_POST['csrf_token']:''))exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	$id = isset($_POST['id'])?intval($_POST['id']):0;
	$pwd = isset($_POST['pwd'])?trim(htmlspecialchars($_POST['pwd'])):'';
	if($id<=0)exit('{"code":-1,"msg":"参数错误"}');
	$row = $DB->getRow("SELECT * FROM pre_share WHERE id=:id AND uid=:uid", [':id'=>$id, ':uid'=>$uid]);
	if(!$row)exit('{"code":-1,"msg":"分享不存在"}');
	if(!empty($pwd)){
		if(!preg_match('/^[a-zA-Z0-9]+$/', $pwd)){
			exit('{"code":-1,"msg":"密码只能为字母和数字"}');
		}
	}
	$DB->exec("UPDATE pre_share SET pwd=:pwd WHERE id=:id", [':pwd'=>!empty($pwd)?$pwd:null, ':id'=>$id]);
	exit(json_encode(['code'=>0, 'msg'=>'密码修改成功']));
break;

case 'getUserStats':
	if(!$islogin2)exit('{"code":-1,"msg":"请先登录"}');

	// 统计用户文件总数
	$totalFiles = $DB->getColumn("SELECT COUNT(*) FROM pre_file WHERE uid=:uid", [':uid'=>$uid]);

	// 统计已用空间（字节）
	$totalSize = $DB->getColumn("SELECT COALESCE(SUM(size), 0) FROM pre_file WHERE uid=:uid", [':uid'=>$uid]);

	// 统计今日上传文件数
	$today = date("Y-m-d 00:00:00");
	$todayFiles = $DB->getColumn("SELECT COUNT(*) FROM pre_file WHERE uid=:uid AND addtime>=:today", [':uid'=>$uid, ':today'=>$today]);

	// 统计分享次数（用户创建的所有分享的总浏览次数）
	$totalViews = $DB->getColumn("SELECT COALESCE(SUM(views), 0) FROM pre_share WHERE uid=:uid", [':uid'=>$uid]);

	// 统计各类别文件数
	$typeStats = $DB->getAll("SELECT type, COUNT(*) as count FROM pre_file WHERE uid=:uid GROUP BY type", [':uid'=>$uid]);

	// 分类统计
	$type_image = explode('|', $conf['type_image']);
	$type_video = explode('|', $conf['type_video']);
	$stats = [
		'image' => 0,
		'video' => 0,
		'audio' => 0,
		'document' => 0,
		'other' => 0
	];
	foreach($typeStats as $t){
		$type = strtolower($t['type']);
		if(in_array($type, $type_image)){
			$stats['image'] += $t['count'];
		}elseif(in_array($type, $type_video)){
			$stats['video'] += $t['count'];
		}elseif(in_array($type, ['mp3','wav','ogg','flac','aac','m4a'])){
			$stats['audio'] += $t['count'];
		}elseif(in_array($type, ['doc','docx','pdf','txt','xls','xlsx','ppt','pptx'])){
			$stats['document'] += $t['count'];
		}else{
			$stats['other'] += $t['count'];
		}
	}

	// 格式化文件大小
	$formattedSize = size_format($totalSize);

	exit(json_encode([
		'code' => 0,
		'data' => [
			'totalFiles' => intval($totalFiles),
			'totalSize' => $totalSize,
			'formattedSize' => $formattedSize,
			'todayFiles' => intval($todayFiles),
			'totalViews' => intval($totalViews),
			'typeStats' => $stats
		]
	]));
break;

case 'getRecentFiles':
	if(!$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	$limit = isset($_GET['limit'])?intval($_GET['limit']):8;
	if($limit<1) $limit = 8;
	if($limit>20) $limit = 20;

	$files = $DB->getAll("SELECT id, name, type, size, hash, addtime FROM pre_file WHERE uid=:uid ORDER BY addtime DESC LIMIT $limit", [':uid'=>$uid]);

	exit(json_encode(['code'=>0, 'data'=>$files]));
break;

case 'uploadAvatar':
	if(!$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	if(!checkCsrfToken(isset($_POST['csrf_token'])?$_POST['csrf_token']:''))exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	if(!isset($_FILES['avatar']))exit('{"code":-1,"msg":"请选择图片文件"}');
	if($_FILES['avatar']['error'] !== UPLOAD_ERR_OK){
		$errMap = [
			UPLOAD_ERR_INI_SIZE => '文件大小超出服务器限制',
			UPLOAD_ERR_FORM_SIZE => '文件大小超出表单限制',
			UPLOAD_ERR_PARTIAL => '文件只有部分被上传',
			UPLOAD_ERR_NO_FILE => '没有文件被上传',
		];
		$errMsg = isset($errMap[$_FILES['avatar']['error']]) ? $errMap[$_FILES['avatar']['error']] : '上传错误('.$_FILES['avatar']['error'].')';
		exit('{"code":-1,"msg":"'.$errMsg.'"}');
	}
	$file = $_FILES['avatar'];
	$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
	$allow_ext = ['jpg','jpeg','png','gif','webp'];
	if(!in_array($ext, $allow_ext)){
		exit('{"code":-1,"msg":"仅支持 jpg, jpeg, png, gif, webp 格式的图片"}');
	}
	if($file['size'] > 2 * 1024 * 1024){
		exit('{"code":-1,"msg":"头像文件大小不能超过2MB"}');
	}
	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	$mime = finfo_file($finfo, $file['tmp_name']);
	finfo_close($finfo);
	$allow_mime = ['image/jpeg','image/png','image/gif','image/webp'];
	if(!in_array($mime, $allow_mime)){
		exit('{"code":-1,"msg":"文件类型不合法"}');
	}
	$avatarDir = ROOT.'assets/avatars/';
	if(!is_dir($avatarDir)){
		@mkdir($avatarDir, 0755, true);
	}
	// 删除旧头像文件（如果是本地文件）
	$oldAvatar = $userrow['faceimg'];
	if($oldAvatar && strpos($oldAvatar, 'assets/avatars/') !== false){
		$oldPath = ROOT . $oldAvatar;
		if(file_exists($oldPath)) @unlink($oldPath);
	}
	$filename = 'avatar_'.$uid.'_'.time().'.'.$ext;
	$savePath = $avatarDir . $filename;
	if(!move_uploaded_file($file['tmp_name'], $savePath)){
		exit('{"code":-1,"msg":"头像保存失败"}');
	}
	$avatarUrl = 'assets/avatars/'.$filename;
	$DB->exec("UPDATE pre_user SET faceimg=:faceimg WHERE uid=:uid", [':faceimg'=>$avatarUrl, ':uid'=>$uid]);
	exit(json_encode(['code'=>0, 'msg'=>'头像上传成功', 'avatar'=>$avatarUrl]));
break;

case 'getUserStorage':
	if(!$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	$used = getUserUsedStorage($uid);
	$limit = intval($userrow['storage_limit']);
	exit(json_encode([
		'code'=>0,
		'data'=>[
			'used'=>$used,
			'limit'=>$limit,
			'usedFormatted'=>formatStorageSize($used),
			'limitFormatted'=>$limit > 0 ? formatStorageSize($limit) : '无限制',
			'percent'=>$limit > 0 ? round($used / $limit * 100, 2) : 0
		]
	]));
break;

case 'getUserInfo':
	if(!$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	exit(json_encode([
		'code'=>0,
		'data'=>[
			'uid'=>$userrow['uid'],
			'nickname'=>$userrow['nickname'],
			'username'=>$userrow['username'],
			'email'=>$userrow['email'],
			'avatar'=>$userrow['faceimg'],
			'type'=>$userrow['type'],
			'level'=>$userrow['level'],
			'addtime'=>$userrow['addtime']
		]
	]));
break;

case 'updateProfile':
	if(!$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	if(!checkCsrfToken(isset($_POST['csrf_token'])?$_POST['csrf_token']:''))exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	$nickname = isset($_POST['nickname'])?trim(htmlspecialchars($_POST['nickname'])):'';
	if(empty($nickname))exit('{"code":-1,"msg":"昵称不能为空"}');
	if(mb_strlen($nickname) > 32)exit('{"code":-1,"msg":"昵称不能超过32个字符"}');
	$DB->exec("UPDATE pre_user SET nickname=:nickname WHERE uid=:uid", [':nickname'=>$nickname, ':uid'=>$uid]);
	exit(json_encode(['code'=>0, 'msg'=>'保存成功']));
break;

case 'moveFolder':
	if(!$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	$id = isset($_POST['id'])?intval($_POST['id']):0;
	$parent_id = isset($_POST['parent_id'])?intval($_POST['parent_id']):0;
	if($id<=0)exit('{"code":-1,"msg":"参数错误"}');
	$row = $DB->getRow("SELECT * FROM pre_folder WHERE id=:id AND uid=:uid", [':id'=>$id, ':uid'=>$uid]);
	if(!$row)exit('{"code":-1,"msg":"目录不存在"}');
	if($parent_id>0){
		$prow = $DB->getRow("SELECT * FROM pre_folder WHERE id=:id AND uid=:uid", [':id'=>$parent_id, ':uid'=>$uid]);
		if(!$prow)exit('{"code":-1,"msg":"目标目录不存在"}');
	}
	if($row['parent_id']==$parent_id)exit('{"code":-1,"msg":"目录已在目标位置"}');
	$subIds = getSubFolderIds($id, $uid, $DB);
	if(in_array($parent_id, $subIds))exit('{"code":-1,"msg":"不能将目录移动到自身或其子目录下"}');
	$exists = $DB->getRow("SELECT * FROM pre_folder WHERE uid=:uid AND parent_id=:parent_id AND name=:name AND id!=:id", [':uid'=>$uid, ':parent_id'=>$parent_id, ':name'=>$row['name'], ':id'=>$id]);
	if($exists)exit('{"code":-1,"msg":"目标目录下已存在同名文件夹"}');
	$DB->update('folder', ['parent_id'=>$parent_id], ['id'=>$id]);
	exit(json_encode(['code'=>0, 'msg'=>'移动成功']));
break;

case 'getFileContent':
	if(!$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	$hash = isset($_GET['hash'])?trim($_GET['hash']):'';
	if(!preg_match('/^[0-9a-z]{32}$/i', $hash))exit('{"code":-1,"msg":"hash error"}');
	$row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash AND uid=:uid", [':hash'=>$hash, ':uid'=>$uid]);
	if(!$row)exit('{"code":-1,"msg":"文件不存在或无权限"}');
	$editable_types = !empty($conf['type_editable']) ? explode('|', $conf['type_editable']) : [];
	if(empty($editable_types) || !in_array(strtolower($row['type']), $editable_types)){
		exit('{"code":-1,"msg":"该文件类型不支持在线编辑"}');
	}
	$max_size = 2 * 1024 * 1024; // 2MB
	if($row['size'] > $max_size){
		exit('{"code":-1,"msg":"文件过大，仅支持编辑2MB以内的文件"}');
	}
	$content = $stor->get($hash);
	if($content === false){
		exit('{"code":-1,"msg":"读取文件内容失败"}');
	}
	// 清理无效的 UTF-8 字节，避免加密/二进制内容导致 json_encode 失败
	if (function_exists('iconv')) {
		$content = @iconv('UTF-8', 'UTF-8//IGNORE', $content);
	}
	exit(json_encode(['code'=>0, 'content'=>$content, 'name'=>$row['name'], 'type'=>$row['type']]));
break;

case 'saveFileContent':
	if(!$islogin2)exit('{"code":-1,"msg":"请先登录"}');
	if(!checkCsrfToken(isset($_POST['csrf_token'])?$_POST['csrf_token']:''))exit('{"code":-1,"msg":"CSRF TOKEN ERROR"}');
	$hash = isset($_POST['hash'])?trim($_POST['hash']):'';
	$content = isset($_POST['content'])?$_POST['content']:'';
	if(!preg_match('/^[0-9a-z]{32}$/i', $hash))exit('{"code":-1,"msg":"hash error"}');
	$row = $DB->getRow("SELECT * FROM pre_file WHERE hash=:hash AND uid=:uid", [':hash'=>$hash, ':uid'=>$uid]);
	if(!$row)exit('{"code":-1,"msg":"文件不存在或无权限"}');
	$editable_types = !empty($conf['type_editable']) ? explode('|', $conf['type_editable']) : [];
	if(empty($editable_types) || !in_array(strtolower($row['type']), $editable_types)){
		exit('{"code":-1,"msg":"该文件类型不支持在线编辑"}');
	}
	$max_size = 2 * 1024 * 1024; // 2MB
	$content_length = strlen($content);
	if($content_length > $max_size){
		exit('{"code":-1,"msg":"保存失败，内容大小超过2MB限制"}');
	}
	$tmpfile = tempnam(sys_get_temp_dir(), 'edit_');
	if(file_put_contents($tmpfile, $content) === false){
		@unlink($tmpfile);
		exit('{"code":-1,"msg":"写入临时文件失败"}');
	}
	$new_hash = md5_file($tmpfile);
	$new_size = filesize($tmpfile);
	if($new_hash === $hash){
		@unlink($tmpfile);
		exit(json_encode(['code'=>0, 'msg'=>'内容未发生变化', 'hash'=>$hash]));
	}
	if(!$stor->exists($new_hash)){
		if(!$stor->savefile($new_hash, $tmpfile)){
			@unlink($tmpfile);
			exit('{"code":-1,"msg":"保存文件到存储失败"}');
		}
	}
	@unlink($tmpfile);
	$DB->exec("UPDATE pre_file SET hash=:new_hash, size=:new_size WHERE id=:id", [':new_hash'=>$new_hash, ':new_size'=>$new_size, ':id'=>$row['id']]);
	$DB->exec("REPLACE INTO pre_hash_route (old_hash, new_hash, addtime) VALUES (:old_hash, :new_hash, NOW())", [':old_hash'=>$hash, ':new_hash'=>$new_hash]);
	$refCount = $DB->getColumn("SELECT count(*) FROM pre_file WHERE hash=:hash", [':hash'=>$hash]);
	if(intval($refCount) === 0){
		$stor->delete($hash);
	}
	exit(json_encode(['code'=>0, 'msg'=>'保存成功', 'hash'=>$new_hash]));
break;

default:
	exit('{"code":-4,"msg":"No Act"}');
break;
}
