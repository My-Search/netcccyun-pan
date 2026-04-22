<?php
include("./includes/common.php");

$title = '文件夹分享 - '.$conf['title'];
$is_file = false;

$csrf_token = md5(mt_rand(0,999).time());
$_SESSION['csrf_token'] = $csrf_token;

include SYSTEM_ROOT.'header.php';

$token = isset($_GET['token'])?trim($_GET['token']):'';
$pwd = isset($_GET['pwd'])?trim($_GET['pwd']):'';

if(empty($token) || !preg_match('/^[0-9a-z]{32}$/i', $token)){
    showmsg('分享链接无效', 4);
}

$share = $DB->getRow("SELECT * FROM pre_share WHERE token=:token", [':token'=>$token]);
if(!$share){
    showmsg('分享不存在或已失效', 4);
}

$folder = $DB->getRow("SELECT * FROM pre_folder WHERE id=:id", [':id'=>$share['folder_id']]);
if(!$folder){
    showmsg('分享的文件夹不存在', 4);
}

$shareUser = $DB->getRow("SELECT nickname FROM pre_user WHERE uid=:uid", [':uid'=>$share['uid']]);
$shareNickname = $shareUser ? $shareUser['nickname'] : '匿名';

// 更新访问次数
$DB->exec("UPDATE pre_share SET views=views+1 WHERE id=:id", [':id'=>$share['id']]);

$needPwd = !empty($share['pwd']) && $share['pwd'] != $pwd;

if(!$needPwd){
    $files = $DB->getAll("SELECT id, name, type, size, hash, addtime, count FROM pre_file WHERE folder_id=:folder_id ORDER BY id DESC", [':folder_id'=>$share['folder_id']]);
}
?>
<style>
.share-container { max-width: 900px; margin: 30px auto; }
.share-header { background: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
.share-header h3 { margin: 0 0 10px 0; }
.share-header .meta { color: #666; font-size: 13px; }
.file-list-table th, .file-list-table td { vertical-align: middle !important; }
.pwd-box { max-width: 400px; margin: 60px auto; text-align: center; }
.pwd-box .lock-icon { font-size: 48px; color: #f0ad4e; margin-bottom: 15px; }
.empty-tip { text-align:center; padding:40px; color:#999; }
</style>

<div class="container share-container">
    <div class="share-header">
        <h3><i class="fa fa-folder-open-o"></i> <?php echo htmlspecialchars($folder['name'])?></h3>
        <div class="meta">
            <span><i class="fa fa-user"></i> 分享者：<?php echo htmlspecialchars($shareNickname)?></span>
            <span style="margin-left:15px;"><i class="fa fa-clock-o"></i> 分享时间：<?php echo $share['addtime']?></span>
            <span style="margin-left:15px;"><i class="fa fa-eye"></i> 访问次数：<?php echo $share['views']+1?></span>
        </div>
        <?php if(!$needPwd){ ?>
        <div style="margin-top:15px;">
            <button class="btn btn-success btn-sm" onclick="saveShare('<?php echo htmlspecialchars($token)?>', '<?php echo htmlspecialchars($pwd)?>')"><i class="fa fa-save"></i> 转存到我的网盘</button>
        </div>
        <?php } ?>
    </div>

<?php if($needPwd){ ?>
    <div class="pwd-box">
        <div class="lock-icon"><i class="fa fa-lock"></i></div>
        <h4>该分享已加密</h4>
        <p style="color:#999; margin-bottom:20px;">请输入访问密码</p>
        <form method="get" action="">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token)?>">
            <div class="input-group">
                <input type="password" name="pwd" class="form-control" placeholder="请输入密码" autofocus>
                <span class="input-group-btn">
                    <button class="btn btn-primary" type="submit">进入</button>
                </span>
            </div>
        </form>
        <?php if(!empty($pwd)){ ?>
        <p style="color:#d9534f; margin-top:10px;">密码错误，请重新输入</p>
        <?php } ?>
    </div>
<?php }else{ ?>
    <div class="well bs-component">
        <div class="table-responsive">
            <table class="table table-striped table-hover file-list-table">
                <thead>
                    <tr>
                        <th>文件名</th>
                        <th>大小</th>
                        <th>格式</th>
                        <th>上传时间</th>
                        <th>下载次数</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
<?php
if(count($files) > 0){
    foreach($files as $f){
        $downurl = './down.php/'.$f['hash'].'.'.($f['type']?$f['type']:'file');
        $viewurl = './file.php?hash='.$f['hash'];
        echo '<tr>';
        echo '<td><i class="fa '.type_to_icon($f['type']).' fa-fw"></i> <a href="'.$viewurl.'" target="_blank">'.htmlspecialchars($f['name']).'</a></td>';
        echo '<td>'.size_format($f['size']).'</td>';
        echo '<td>'.($f['type']?$f['type']:'未知').'</td>';
        echo '<td>'.$f['addtime'].'</td>';
        echo '<td>'.$f['count'].'</td>';
        echo '<td><a href="'.$downurl.'" class="btn btn-xs btn-primary"><i class="fa fa-download"></i> 下载</a></td>';
        echo '</tr>';
    }
}else{
    echo '<tr><td colspan="6" class="empty-tip"><i class="fa fa-folder-open-o" style="font-size:48px;"></i><p>该文件夹下没有文件</p></td></tr>';
}
?>
                </tbody>
            </table>
        </div>
    </div>
<?php } ?>
</div>

<script src="https://s4.zstatic.net/ajax/libs/layer/2.3/layer.js"></script>
<script>
function escapeHtml(text){
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}

function saveShare(token, pwd){
    <?php if($islogin2){ ?>
    showFolderSelector(token, pwd);
    <?php }else{ ?>
    localStorage.setItem('pendingSaveShare', JSON.stringify({token: token, pwd: pwd}));
    window.location.href = './login.php?redirect=' + encodeURIComponent(location.href);
    <?php } ?>
}

function showFolderSelector(token, pwd){
    var ii = layer.load(1, {shade:[0.1,'#fff']});
    $.get('ajax.php?act=listMine&folder_id=0', function(res){
        layer.close(ii);
        if(res.code == 0){
            var html = '<div style="padding:15px;">';
            html += '<p>选择要转存到的目录：</p>';
            html += '<div class="list-group">';
            html += '<a class="list-group-item active" id="folder-0" onclick="selectFolder(0)">根目录</a>';
            for(var i=0; i<res.folders.length; i++){
                html += '<a class="list-group-item" id="folder-'+res.folders[i].id+'" onclick="selectFolder('+res.folders[i].id+')">'+escapeHtml(res.folders[i].name)+'</a>';
            }
            html += '</div></div>';
            window._saveShareToken = token;
            window._saveSharePwd = pwd;
            window._selectedFolderId = 0;
            layer.open({
                type: 1,
                title: '选择转存目录',
                area: ['350px','300px'],
                content: html,
                btn: ['确认转存', '取消'],
                yes: function(index){
                    layer.close(index);
                    doSaveShare(window._saveShareToken, window._saveSharePwd, window._selectedFolderId);
                }
            });
        }else{
            layer.msg(res.msg, {icon:2});
        }
    }, 'json');
}

function selectFolder(id){
    window._selectedFolderId = id;
    $('.list-group-item').removeClass('active');
    $('#folder-'+id).addClass('active');
}

function doSaveShare(token, pwd, folderId){
    var ii = layer.load(1, {shade:[0.1,'#fff']});
    $.post('ajax.php?act=saveShare', {
        token: token,
        pwd: pwd,
        folder_id: folderId,
        csrf_token: '<?php echo $csrf_token; ?>'
    }, function(res){
        layer.close(ii);
        if(res.code == 0){
            layer.msg(res.msg, {icon:1});
        }else{
            layer.msg(res.msg, {icon:2});
        }
    }, 'json');
}

$(function(){
    var pending = localStorage.getItem('pendingSaveShare');
    if(pending && <?php echo $islogin2 ? 'true' : 'false'; ?>){
        try{
            var data = JSON.parse(pending);
            if(data.token === '<?php echo $token; ?>'){
                localStorage.removeItem('pendingSaveShare');
                showFolderSelector(data.token, data.pwd);
            }
        }catch(e){}
    }
});
</script>
<?php include SYSTEM_ROOT.'footer.php';?>
</body>
</html>
