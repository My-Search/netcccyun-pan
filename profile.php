<?php
include("./includes/common.php");
if(!$islogin2){
    @header('Content-Type: text/html; charset=UTF-8');
    exit("<script language='javascript'>alert('请先登录');window.location.href='./login.php';</script>");
}
$title = '个人信息 - ' . $conf['title'];
$csrf_token = createCsrfToken();
include SYSTEM_ROOT.'header.php';

$typeMap = ['local'=>'本地账号','qq'=>'QQ登录','wx'=>'微信登录'];
$avatar = $userrow['faceimg'] ?: '';
$firstLetter = mb_substr($userrow['nickname'],0,1);
?>
<style>
.profile-card { max-width: 480px; margin: 0 auto; }
.profile-avatar-wrap { position: relative; width: 120px; height: 120px; margin: 0 auto 20px; cursor: pointer; }
.profile-avatar-wrap img, .profile-avatar-wrap .avatar-placeholder { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid #eee; background: #fff; display: flex; align-items: center; justify-content: center; font-size: 48px; color: #fff; }
.profile-avatar-wrap .avatar-mask { position: absolute; top: 0; left: 0; width: 120px; height: 120px; border-radius: 50%; background: rgba(0,0,0,0.45); display: none; align-items: center; justify-content: center; color: #fff; font-size: 14px; flex-direction: column; }
.profile-avatar-wrap:hover .avatar-mask { display: flex; }
.profile-avatar-wrap .avatar-mask i { font-size: 24px; margin-bottom: 4px; }
.profile-info-row { padding: 10px 0; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; }
.profile-info-row:last-child { border-bottom: none; }
.profile-info-label { color: #999; font-size: 13px; }
.profile-info-value { font-size: 14px; color: #333; }
</style>

<div class="container">
    <div class="panel panel-default profile-card">
        <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-user-circle"></i> 个人信息</h3>
        </div>
        <div class="panel-body" style="text-align:center; padding-top:30px;">

            <div class="profile-avatar-wrap" onclick="selectAvatar()" title="点击更换头像">
                <?php if($avatar && (strpos($avatar,'http')===0 || strpos($avatar,'/')===0 || strpos($avatar,'assets/')===0 || strpos($avatar,'data:')===0)){?>
                    <img id="profileAvatar" src="<?php echo (strpos($avatar,'http')===0||strpos($avatar,'data:')===0)?$avatar:'./'.$avatar;?>" alt="头像">
                <?php }else{?>
                    <div id="profileAvatar" class="avatar-placeholder" style="background:#337ab7;"><?php echo $firstLetter?></div>
                <?php }?>
                <div class="avatar-mask">
                    <i class="fa fa-camera"></i>
                    <span>更换头像</span>
                </div>
            </div>
            <input type="file" id="avatarInput" accept="image/*" style="display:none;" onchange="uploadAvatar(this)">

            <div style="text-align:left; max-width:360px; margin:0 auto; padding-top:10px;">
                <div class="profile-info-row">
                    <span class="profile-info-label">昵称</span>
                    <span class="profile-info-value" id="displayNickname"><?php echo htmlspecialchars($userrow['nickname'])?></span>
                </div>
                <div class="profile-info-row">
                    <span class="profile-info-label">用户名</span>
                    <span class="profile-info-value"><?php echo $userrow['username'] ? htmlspecialchars($userrow['username']) : '<span style="color:#999;">无</span>'?></span>
                </div>
                <div class="profile-info-row">
                    <span class="profile-info-label">邮箱</span>
                    <span class="profile-info-value"><?php echo $userrow['email'] ? htmlspecialchars($userrow['email']) : '<span style="color:#999;">未绑定</span>'?></span>
                </div>
                <div class="profile-info-row">
                    <span class="profile-info-label">账号类型</span>
                    <span class="profile-info-value">
                        <?php if($userrow['type']=='qq'){?><span class="label label-info">QQ登录</span><?php }elseif($userrow['type']=='wx'){?><span class="label label-success">微信登录</span><?php }else{?><span class="label label-default">本地账号</span><?php }?>
                    </span>
                </div>
                <div class="profile-info-row">
                    <span class="profile-info-label">UID</span>
                    <span class="profile-info-value"><?php echo $userrow['uid']?></span>
                </div>
                <div class="profile-info-row">
                    <span class="profile-info-label">注册时间</span>
                    <span class="profile-info-value"><?php echo $userrow['addtime']?></span>
                </div>
                <div class="profile-info-row">
                    <span class="profile-info-label">存储空间</span>
                    <span class="profile-info-value" id="storageInfo"><i class="fa fa-spinner fa-spin"></i> 加载中...</span>
                </div>
            </div>

            <div style="margin-top:25px; text-align:left; max-width:360px; margin:25px auto 0;">
                <h5 style="border-left:3px solid #337ab7; padding-left:8px; margin-bottom:15px;">修改资料</h5>
                <div class="form-group">
                    <label for="nicknameInput">昵称</label>
                    <input type="text" class="form-control" id="nicknameInput" value="<?php echo htmlspecialchars($userrow['nickname'])?>" maxlength="32" placeholder="请输入昵称">
                </div>
                <button class="btn btn-primary btn-raised" onclick="saveProfile()"><i class="fa fa-save"></i> 保存修改</button>
            </div>

        </div>
    </div>
</div>

<?php include SYSTEM_ROOT.'footer.php';?>
<script src="https://s4.zstatic.net/ajax/libs/layer/2.3/layer.js"></script>
<script>
function selectAvatar(){
    $('#avatarInput').click();
}
function uploadAvatar(input){
    if(!input.files || input.files.length === 0) return;
    var file = input.files[0];
    var allowTypes = ['image/jpeg','image/png','image/gif','image/webp'];
    if(allowTypes.indexOf(file.type) === -1){
        layer.msg('仅支持 jpg, png, gif, webp 格式的图片', {icon:2});
        input.value = '';
        return;
    }
    if(file.size > 2 * 1024 * 1024){
        layer.msg('头像文件大小不能超过2MB', {icon:2});
        input.value = '';
        return;
    }
    var formData = new FormData();
    formData.append('avatar', file);
    formData.append('csrf_token', '<?php echo $csrf_token; ?>');
    var ii = layer.load(1, {shade:[0.1,'#fff']});
    $.ajax({
        url: 'ajax.php?act=uploadAvatar',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(res){
            layer.close(ii);
            if(res.code == 0){
                layer.msg('头像上传成功', {icon:1});
                var imgUrl = './'+res.avatar+'?t='+Date.now();
                var $img = $('#profileAvatar');
                if($img.is('img')){
                    $img.attr('src', imgUrl);
                }else{
                    $img.replaceWith('<img id="profileAvatar" src="'+imgUrl+'" alt="头像" style="width:120px; height:120px; border-radius:50%; object-fit:cover; border:3px solid #eee; background:#fff;">');
                }
                $('#navUserAvatar').attr('src', imgUrl).show();
                $('#navUserIcon').hide();
            }else{
                layer.msg(res.msg, {icon:2});
            }
            input.value = '';
        },
        error: function(){
            layer.close(ii);
            layer.msg('上传失败，请稍后重试', {icon:2});
            input.value = '';
        }
    });
}
function saveProfile(){
    var nickname = $('#nicknameInput').val().trim();
    if(!nickname){
        layer.msg('昵称不能为空', {icon:2});
        return;
    }
    if(nickname.length > 32){
        layer.msg('昵称不能超过32个字符', {icon:2});
        return;
    }
    var ii = layer.load(1, {shade:[0.1,'#fff']});
    $.post('ajax.php?act=updateProfile', {
        nickname: nickname,
        csrf_token: '<?php echo $csrf_token; ?>'
    }, function(res){
        layer.close(ii);
        if(res.code == 0){
            layer.msg('保存成功', {icon:1});
            $('#displayNickname').text(nickname);
        }else{
            layer.msg(res.msg, {icon:2});
        }
    }, 'json');
}

// 加载用户容量信息
function loadStorageInfo(){
    $.get('ajax.php?act=getUserStorage', function(res){
        if(res.code == 0){
            var data = res.data;
            if(data.limit > 0){
                var percent = data.percent;
                var color = percent >= 90 ? '#d9534f' : (percent >= 70 ? '#f0ad4e' : '#5cb85c');
                var html = '<div style="width:120px; display:inline-block; vertical-align:middle;">'
                    + '<div style="height:8px; background:#f0f0f0; border-radius:4px; overflow:hidden;">'
                    + '<div style="height:100%; width:'+Math.min(percent,100)+'%; background:'+color+'; border-radius:4px;"></div>'
                    + '</div>'
                    + '</div>'
                    + ' <span style="font-size:12px; color:#666;">'+data.usedFormatted+' / '+data.limitFormatted+'</span>';
                $('#storageInfo').html(html);
            }else{
                $('#storageInfo').html('<span style="color:#999;">无限制</span>');
            }
        }else{
            $('#storageInfo').html('<span style="color:#999;">加载失败</span>');
        }
    }, 'json');
}

$(document).ready(function(){
    loadStorageInfo();
});
</script>
</body>
</html>
