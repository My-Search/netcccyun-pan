<?php
include("./includes/common.php");

if(!$conf['userlogin']){
    @header('Content-Type: text/html; charset=UTF-8');
	exit("<script language='javascript'>alert('未开启登录');window.location.href='./';</script>");
}
if(isset($_GET['logout'])){
	if(!checkRefererHost())exit();
	setcookie("user_token", "", time() - 1, '/');
	@header('Content-Type: text/html; charset=UTF-8');
	exit("<script language='javascript'>alert('您已成功注销本次登录！');window.location.href='./login.php';</script>");
}elseif($islogin2==1){
	@header('Content-Type: text/html; charset=UTF-8');
	exit("<script language='javascript'>alert('您已登录！');window.location.href='./';</script>");
}elseif(isset($_GET['act']) && $_GET['act']=='connect'){
    @header('Content-Type: application/json; charset=UTF-8');
    $type = isset($_POST['type'])?$_POST['type']:exit('{"code":-1,"msg":"no type"}');
    if(!$conf['login_apiurl'] || !$conf['login_appid'] || !$conf['login_appkey'])exit('{"code":-1,"msg":"未配置好快捷登录接口信息"}');
    $Oauth = new \lib\Oauth($conf['login_apiurl'], $conf['login_appid'], $conf['login_appkey']);
    $res = $Oauth->login($type);
    if(isset($res['code']) && $res['code']==0){
        $result = ['code'=>0, 'url'=>$res['url']];
    }elseif(isset($res['code'])){
        $result = ['code'=>-1, 'msg'=>$res['msg']];
    }else{
        $result = ['code'=>-1, 'msg'=>'快捷登录接口请求失败'];
    }
    exit(json_encode($result));
}elseif(isset($_GET['act']) && $_GET['act']=='login'){
    @header('Content-Type: application/json; charset=UTF-8');
    if(!checkRefererHost())exit('{"code":403}');
    $username = isset($_POST['username'])?trim($_POST['username']):'';
    $password = isset($_POST['password'])?$_POST['password']:'';
    if(empty($username) || empty($password)){
        exit('{"code":-1,"msg":"用户名和密码不能为空"}');
    }
    $userrow = $DB->find('user','*',['type'=>'local', 'username'=>$username], null, '1');
    if(!$userrow || !password_verify($password, $userrow['password'])){
        exit('{"code":-1,"msg":"用户名或密码错误"}');
    }
    if($userrow['enable']==0){
        exit('{"code":-1,"msg":"当前用户已被禁止登录"}');
    }
    $uid = $userrow['uid'];
    $DB->update('user', ['loginip' => $clientip, 'lasttime'=>'NOW()'], ['uid'=>$uid]);
    $session=md5('local'.$userrow['username'].$password_hash);
    $expiretime=time()+2592000;
    $token=authcode("{$uid}\t{$session}\t{$expiretime}", 'ENCODE', SYS_KEY);
    setcookie("user_token", $token, time() + 2592000, '/');
    exit('{"code":0,"msg":"登录成功"}');
}elseif(isset($_GET['act']) && $_GET['act']=='sendcode'){
    @header('Content-Type: application/json; charset=UTF-8');
    if(!checkRefererHost())exit('{"code":403}');
    if(empty($conf['register_email_verify'])){
        exit('{"code":-1,"msg":"未开启邮箱验证"}');
    }
    $email = isset($_POST['email'])?trim($_POST['email']):'';
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        exit('{"code":-1,"msg":"邮箱格式不正确"}');
    }
    $exists = $DB->findColumn('user','uid',['email'=>$email]);
    if($exists){
        exit('{"code":-1,"msg":"该邮箱已被注册"}');
    }
    if(isset($_SESSION['reg_code_time']) && time() - $_SESSION['reg_code_time'] < 60){
        exit('{"code":-1,"msg":"请稍后再试"}');
    }
    $code = random(6, 1);
    $_SESSION['reg_email'] = $email;
    $_SESSION['reg_code'] = $code;
    $_SESSION['reg_code_time'] = time();
    $mailer = new \lib\Mailer([
        'host' => $conf['smtp_host'],
        'port' => $conf['smtp_port'],
        'user' => $conf['smtp_user'],
        'pass' => $conf['smtp_pass'],
        'secure' => $conf['smtp_secure'],
        'from' => $conf['smtp_from'],
        'fromname' => $conf['smtp_fromname'],
    ]);
    $subject = '用户注册验证码';
    $body = '<p>您的注册验证码是：<b style="font-size:18px">'.$code.'</b></p><p>验证码10分钟内有效，请勿泄露给他人。</p>';
    if($mailer->send($email, $subject, $body)){
        exit('{"code":0,"msg":"验证码已发送"}');
    }else{
        exit('{"code":-1,"msg":"邮件发送失败：'.$mailer->error().'"}');
    }
}elseif(isset($_GET['act']) && $_GET['act']=='register'){
    @header('Content-Type: application/json; charset=UTF-8');
    if(!checkRefererHost())exit('{"code":403}');
    if(empty($conf['register_open'])){
        exit('{"code":-1,"msg":"管理员已关闭用户注册"}');
    }
    $username = isset($_POST['username'])?trim($_POST['username']):'';
    $password = isset($_POST['password'])?$_POST['password']:'';
    $password2 = isset($_POST['password2'])?$_POST['password2']:'';
    $email = isset($_POST['email'])?trim($_POST['email']):'';
    $code = isset($_POST['code'])?trim($_POST['code']):'';

    if(empty($username) || empty($password) || empty($password2)){
        exit('{"code":-1,"msg":"请填写完整信息"}');
    }
    if(!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $username)){
        exit('{"code":-1,"msg":"用户名只能由4-20位字母、数字、下划线组成"}');
    }
    if(strlen($password) < 4){
        exit('{"code":-1,"msg":"密码长度不能少于4位"}');
    }
    if($password !== $password2){
        exit('{"code":-1,"msg":"两次输入的密码不一致"}');
    }
    if(!empty($conf['register_email_verify'])){
        if(empty($email) || empty($code)){
            exit('{"code":-1,"msg":"请输入邮箱和验证码"}');
        }
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
            exit('{"code":-1,"msg":"邮箱格式不正确"}');
        }
        if(empty($_SESSION['reg_email']) || empty($_SESSION['reg_code']) || empty($_SESSION['reg_code_time'])){
            exit('{"code":-1,"msg":"请先获取验证码"}');
        }
        if($email !== $_SESSION['reg_email'] || $code !== $_SESSION['reg_code']){
            exit('{"code":-1,"msg":"验证码错误"}');
        }
        if(time() - $_SESSION['reg_code_time'] > 600){
            exit('{"code":-1,"msg":"验证码已过期，请重新获取"}');
        }
    }
    $exists = $DB->findColumn('user','uid',['type'=>'local', 'username'=>$username]);
    if($exists){
        exit('{"code":-1,"msg":"该用户名已被注册"}');
    }
    if(!empty($email)){
        $emailExists = $DB->findColumn('user','uid',['email'=>$email]);
        if($emailExists){
            exit('{"code":-1,"msg":"该邮箱已被注册"}');
        }
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $defaultStorage = intval($conf['default_storage']);
    $storageLimit = $defaultStorage > 0 ? $defaultStorage * 1024 * 1024 * 1024 : 0;
    $uid = $DB->insert('user', [
        'type' => 'local',
        'openid' => $username,
        'username' => $username,
        'password' => $hash,
        'email' => $email,
        'nickname' => $username,
        'faceimg' => '',
        'enable' => 1,
        'regip' => $clientip,
        'loginip' => $clientip,
        'storage_limit' => $storageLimit,
        'addtime' => 'NOW()',
        'lasttime' => 'NOW()',
    ]);
    if(!$uid){
        exit('{"code":-1,"msg":"注册失败：'.$DB->error().'"}');
    }
    unset($_SESSION['reg_email'], $_SESSION['reg_code'], $_SESSION['reg_code_time']);
    exit('{"code":0,"msg":"注册成功"}');
}elseif($_GET['code'] && $_GET['type'] && $_GET['state']){
	if($_GET['state'] != $_SESSION['Oauth_state']){
		sysmsg("<h2>The state does not match. You may be a victim of CSRF.</h2>");
	}
	$type = $_GET['type'];
    $typename = $type=='wx'?'微信':'QQ';
	$Oauth = new \lib\Oauth($conf['login_apiurl'], $conf['login_appid'], $conf['login_appkey']);
	$arr = $Oauth->callback();
	if(isset($arr['code']) && $arr['code']==0){
		$openid=$arr['social_uid'];
		$access_token=$arr['access_token'];
		$nickname=trim($arr['nickname']);
        if(empty($nickname) || $nickname=='-') $nickname = $typename.'用户';
		$faceimg=$arr['faceimg'];
	}elseif(isset($arr['code'])){
		sysmsg('<h3>error:</h3>'.$arr['errcode'].'<h3>msg  :</h3>'.$arr['msg']);
	}else{
		sysmsg('获取登录数据失败');
	}

	$userrow=$DB->find('user','*',['type'=>$type, 'openid'=>$openid], null, '1');
	if(!$userrow){
        $defaultStorage = intval($conf['default_storage']);
        $storageLimit = $defaultStorage > 0 ? $defaultStorage * 1024 * 1024 * 1024 : 0;
        if(!$DB->insert('user', [
            'type' => $type,
            'openid' => $openid,
            'nickname' => $nickname,
            'faceimg' => $faceimg,
            'enable' => 1,
            'regip' => $clientip,
            'loginip' => $clientip,
            'storage_limit' => $storageLimit,
            'addtime' => 'NOW()',
            'lasttime' => 'NOW()',
        ]))sysmsg('用户注册失败 '.$DB->error());
        $uid = $DB->lastInsertId();
	}else{
        if($userrow['enable']==0){
            $_SESSION['user_block'] = true;
            sysmsg('当前用户已被禁止登录');
        }
        $uid = $userrow['uid'];
        $DB->update('user', ['loginip' => $clientip, 'lasttime'=>'NOW()'], ['uid'=>$uid]);
    }
    if($_SESSION['user_block']){
        $DB->update('user', ['enable' => 0], ['uid'=>$uid]);
        sysmsg('当前用户已被禁止登录');
    }
    if(isset($_SESSION['fileids']) && count($_SESSION['fileids'])>0){
        $ids = array_reverse($_SESSION['fileids']);
        if(count($ids) > 60){
            $ids = array_splice($ids, 0, 60);
        }
        $ids = implode(',',$ids);
        $DB->exec("UPDATE pre_file SET uid='{$uid}' WHERE id IN ({$ids}) AND uid=0");
    }
    $session=md5($type.$openid.$password_hash);
    $expiretime=time()+2592000;
    $token=authcode("{$uid}\t{$session}\t{$expiretime}", 'ENCODE', SYS_KEY);
    ob_clean();
    setcookie("user_token", $token, time() + 2592000, '/');
	exit("<script language='javascript'>var redirect=localStorage.getItem('login_redirect');if(redirect){localStorage.removeItem('login_redirect');window.location.href=redirect;}else{window.location.href='./';}</script>");
}

$title = '用户登录 - ' . $conf['title'];
include SYSTEM_ROOT.'header.php';
?>
<style>
.login-tabs { margin-bottom: 15px; }
.login-tabs a { display: inline-block; padding: 8px 16px; color: #666; border-bottom: 2px solid transparent; cursor: pointer; text-decoration: none; }
.login-tabs a.active { color: #337ab7; border-bottom-color: #337ab7; }
.loginbtn { width: 50px; height: 50px; line-height: 50px; border-radius: 50%; margin: 0 8px; font-size: 20px; display: inline-flex; align-items: center; justify-content: center; }
</style>
<div class="container">
<div class="col-xs-10 col-sm-8 col-md-6 col-lg-4 center-block" style="float: none;">
    <div class="well bs-component" style="margin-top:30%">
        <div class="text-center">
            <div class="login-tabs">
                <a href="javascript:;" id="tab-login" class="active" onclick="switchTab('login')">账号登录</a>
                <?php if($conf['register_open']){?><a href="javascript:;" id="tab-register" onclick="switchTab('register')">注册账号</a><?php }?>
            </div>

            <div id="panel-login">
                <form id="form-login" onsubmit="return false;">
                    <div class="form-group">
                        <input type="text" name="username" class="form-control" placeholder="用户名" required/>
                    </div>
                    <div class="form-group">
                        <input type="password" name="password" class="form-control" placeholder="密码" required/>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary btn-block" onclick="doLogin()">登录</button>
                    </div>
                </form>
                <hr style="margin:15px 0;">
                <p>
                <?php if($conf['login_qq']){?><a href="javascript:connect('qq')" class="btn btn-info btn-fab loginbtn"><i class="fa fa-qq"></i></a><?php }?>
                <?php if($conf['login_wx']){?><a href="javascript:connect('wx')" class="btn btn-success btn-fab loginbtn"><i class="fa fa-wechat"></i></a><?php }?>
                </p>
                <p class="text-muted">新用户快捷登录后会自动注册账号</p>
            </div>

            <?php if($conf['register_open']){?>
            <div id="panel-register" style="display:none;">
                <form id="form-register" onsubmit="return false;">
                    <div class="form-group">
                        <input type="text" name="username" class="form-control" placeholder="用户名（4-20位字母数字下划线）" required/>
                    </div>
                    <div class="form-group">
                        <input type="password" name="password" class="form-control" placeholder="密码（至少4位）" required/>
                    </div>
                    <div class="form-group">
                        <input type="password" name="password2" class="form-control" placeholder="确认密码" required/>
                    </div>
                    <?php if($conf['register_email_verify']){?>
                    <div class="form-group">
                        <div class="input-group">
                            <input type="email" name="email" id="reg-email" class="form-control" placeholder="邮箱" required/>
                            <span class="input-group-btn">
                                <button type="button" class="btn btn-default" onclick="sendCode()">获取验证码</button>
                            </span>
                        </div>
                    </div>
                    <div class="form-group">
                        <input type="text" name="code" class="form-control" placeholder="邮箱验证码" required/>
                    </div>
                    <?php }?>
                    <div class="form-group">
                        <button type="submit" class="btn btn-success btn-block" onclick="doRegister()">注册</button>
                    </div>
                </form>
            </div>
            <?php }?>
        </div>
    </div>
</div>
</div>
<?php include SYSTEM_ROOT.'footer.php';?>
<script src="https://s4.zstatic.net/ajax/libs/layer/2.3/layer.js"></script>
<script>
var urlParams = new URLSearchParams(window.location.search);
var redirectUrl = urlParams.get('redirect');
if(redirectUrl){
    localStorage.setItem('login_redirect', redirectUrl);
}
function switchTab(tab){
    if(tab=='login'){
        $('#panel-login').show();
        $('#panel-register').hide();
        $('#tab-login').addClass('active');
        $('#tab-register').removeClass('active');
    }else{
        $('#panel-login').hide();
        $('#panel-register').show();
        $('#tab-login').removeClass('active');
        $('#tab-register').addClass('active');
    }
}
function connect(type){
    var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : "POST",
		url : "login.php?act=connect",
		data : {type:type},
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				window.location.href = data.url;
			}else{
				layer.alert(data.msg, {icon: 7});
			}
		}
	});
}
function doLogin(){
    var ii = layer.load(2, {shade:[0.1,'#fff']});
    $.ajax({
        type : "POST",
        url : "login.php?act=login",
        data : $('#form-login').serialize(),
        dataType : 'json',
        success : function(data) {
            layer.close(ii);
            if(data.code == 0){
                var redirect = localStorage.getItem('login_redirect');
                if(redirect){
                    localStorage.removeItem('login_redirect');
                    window.location.href = redirect;
                }else{
                    window.location.href = './';
                }
            }else{
                layer.alert(data.msg, {icon: 7});
            }
        },
        error : function(){
            layer.close(ii);
            layer.msg('服务器错误');
        }
    });
}
function doRegister(){
    var ii = layer.load(2, {shade:[0.1,'#fff']});
    $.ajax({
        type : "POST",
        url : "login.php?act=register",
        data : $('#form-register').serialize(),
        dataType : 'json',
        success : function(data) {
            layer.close(ii);
            if(data.code == 0){
                layer.alert(data.msg, {icon: 1}, function(index){
                    layer.close(index);
                    switchTab('login');
                });
            }else{
                layer.alert(data.msg, {icon: 7});
            }
        },
        error : function(){
            layer.close(ii);
            layer.msg('服务器错误');
        }
    });
}
function sendCode(){
    var email = $('#reg-email').val();
    if(!email){
        layer.msg('请先输入邮箱');
        return;
    }
    var ii = layer.load(2, {shade:[0.1,'#fff']});
    $.ajax({
        type : "POST",
        url : "login.php?act=sendcode",
        data : {email:email},
        dataType : 'json',
        success : function(data) {
            layer.close(ii);
            if(data.code == 0){
                layer.msg(data.msg, {icon: 1});
            }else{
                layer.alert(data.msg, {icon: 7});
            }
        },
        error : function(){
            layer.close(ii);
            layer.msg('服务器错误');
        }
    });
}
</script>
</body>
</html>
