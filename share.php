<?php
include("./includes/common.php");

$title = '文件夹分享 - '.$conf['title'];
$is_file = false;

$csrf_token = md5(mt_rand(0,999).time());
$_SESSION['csrf_token'] = $csrf_token;

include SYSTEM_ROOT.'header.php';

$token = isset($_GET['token'])?trim($_GET['token']):'';
$urlPwd = isset($_GET['pwd'])?trim($_GET['pwd']):'';

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

$needPwd = !empty($share['pwd']);
$isVerified = false;

if($needPwd){
    if(!empty($urlPwd) && $share['pwd'] == $urlPwd){
        $isVerified = true;
    }
}else{
    $isVerified = true;
}

if($isVerified){
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
        <div id="saveShareBtn" style="margin-top:15px;<?php echo !$isVerified?'display:none;':''?>">
            <button class="btn btn-success btn-sm" onclick="saveShare('<?php echo htmlspecialchars($token)?>', window._sharePwd||'<?php echo htmlspecialchars($urlPwd)?>')"><i class="fa fa-save"></i> 转存到我的网盘</button>
        </div>
    </div>

<?php if($needPwd && !$isVerified){ ?>
    <div class="pwd-box" id="pwdBox">
        <div class="lock-icon"><i class="fa fa-lock"></i></div>
        <h4>该分享已加密</h4>
        <p style="color:#999; margin-bottom:20px;">请输入访问密码</p>
        <form id="pwdForm" onsubmit="event.preventDefault(); checkSharePwd();">
            <div class="input-group">
                <input type="password" id="sharePwd" class="form-control" placeholder="请输入密码" autofocus>
                <span class="input-group-btn">
                    <button class="btn btn-primary" type="submit">进入</button>
                </span>
            </div>
        </form>
        <p id="pwdError" style="color:#d9534f; margin-top:10px; display:none;">密码错误，请重新输入</p>
    </div>
<?php } ?>

<div id="fileListContainer" <?php echo !$isVerified?'style="display:none;"':''?>>
<?php if($isVerified){ ?>
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
</div>

<script src="https://s4.zstatic.net/ajax/libs/layer/2.3/layer.js"></script>
<script>
function escapeHtml(text){
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}

var shareToken = '<?php echo htmlspecialchars($token)?>';
<?php if($isVerified){ ?>
window._sharePwd = '<?php echo htmlspecialchars($urlPwd)?>';
<?php } ?>

function typeToIcon(type){
    var type_image = ['png','jpg','jpeg','gif','bmp','webp','ico','svg','svgz','tif','tiff','heic','psd','exif','pcx','tga','fpx','cdr','pcd','eps','ai','wmf','raw','ufo','jpc','jp2','jpx','xbm','wbmp','avif'];
    var type_audio = ['mp3','wav','wma','ogg','m4a','flac','ape','aac','ra','cda','midi','mid','aif','au','voc'];
    var type_video = ['mp4','webm','flv','f4v','mov','3gp','3gpp','avi','mpg','mpeg','wmv','mkv','ts','dat','asf','rm','rmvb','ram','divx','vob','qt','fli','flc','mod','m2t','swf','mts','m2ts','mpe','div','lavf','m3u8','m4v','ogm','ogv'];
    var type_text = ['txt','text','log','md','yaml','yml','conf','config','ini'];
    var type_code = ['c','cpp','cxx','rc','php','py','cs','h','htm','html','css','less','js','hdml','dtd','wml','xml','vbs','vb','rtx','xsd','dpr','sql','java','go','jsp','asp','aspx','asa','asax','pl','bat','cmd','rb','reg','sh','json','lua','r','mm','mak','swift','tpl'];
    var type_archive = ['zip','7z','rar','tgz','gz','xz','tar','jar','iso','z','zipx','cab','bz2','arj','lz','lzh'];
    var type_word = ['doc','docx','xps','rtf','wps','odt'];
    var type_excel = ['xls','xlsx','ods'];
    var type_pdf = ['pdf'];
    var type_powerpoint = ['ppt','pptx','pptm'];
    var type_android = ['apk'];
    var type_apple = ['ipa','dmg'];
    var type_windows = ['exe','appx','msi'];
    var type_linux = ['deb','rpm'];

    if(type_image.indexOf(type) !== -1) return 'fa-file-image-o';
    if(type_audio.indexOf(type) !== -1) return 'fa-file-audio-o';
    if(type_video.indexOf(type) !== -1) return 'fa-file-video-o';
    if(type_text.indexOf(type) !== -1) return 'fa-file-text-o';
    if(type_code.indexOf(type) !== -1) return 'fa-file-code-o';
    if(type_archive.indexOf(type) !== -1) return 'fa-file-archive-o';
    if(type_word.indexOf(type) !== -1) return 'fa-file-word-o';
    if(type_excel.indexOf(type) !== -1) return 'fa-file-excel-o';
    if(type_pdf.indexOf(type) !== -1) return 'fa-file-pdf-o';
    if(type_powerpoint.indexOf(type) !== -1) return 'fa-file-powerpoint-o';
    if(type_android.indexOf(type) !== -1) return 'fa-android';
    if(type_apple.indexOf(type) !== -1) return 'fa-apple';
    if(type_windows.indexOf(type) !== -1) return 'fa-windows';
    if(type_linux.indexOf(type) !== -1) return 'fa-linux';
    return 'fa-file-o';
}

function sizeFormat(size){
    size = parseInt(size);
    if(size < 1024) return size + ' B';
    size /= 1024;
    if(size < 1024) return size.toFixed(2) + ' KB';
    size /= 1024;
    if(size < 1024) return size.toFixed(2) + ' MB';
    size /= 1024;
    return size.toFixed(2) + ' GB';
}

function renderFileList(files){
    var html = '<div class="well bs-component"><div class="table-responsive"><table class="table table-striped table-hover file-list-table"><thead><tr><th>文件名</th><th>大小</th><th>格式</th><th>上传时间</th><th>下载次数</th><th>操作</th></tr></thead><tbody>';
    if(files && files.length > 0){
        for(var i = 0; i < files.length; i++){
            var f = files[i];
            var downurl = './down.php/' + f.hash + '.' + (f.type ? f.type : 'file');
            var viewurl = './file.php?hash=' + f.hash;
            html += '<tr>';
            html += '<td><i class="fa ' + typeToIcon(f.type) + ' fa-fw"></i> <a href="' + viewurl + '" target="_blank">' + escapeHtml(f.name) + '</a></td>';
            html += '<td>' + sizeFormat(f.size) + '</td>';
            html += '<td>' + (f.type ? f.type : '未知') + '</td>';
            html += '<td>' + f.addtime + '</td>';
            html += '<td>' + f.count + '</td>';
            html += '<td><a href="' + downurl + '" class="btn btn-xs btn-primary"><i class="fa fa-download"></i> 下载</a></td>';
            html += '</tr>';
        }
    }else{
        html += '<tr><td colspan="6" class="empty-tip"><i class="fa fa-folder-open-o" style="font-size:48px;"></i><p>该文件夹下没有文件</p></td></tr>';
    }
    html += '</tbody></table></div></div>';
    $('#fileListContainer').html(html).show();
}

function checkSharePwd(){
    var pwd = $('#sharePwd').val();
    if(!pwd){
        layer.msg('请输入密码', {icon:2});
        return;
    }
    $('#pwdError').hide();
    var ii = layer.load(1, {shade:[0.1,'#fff']});
    $.post('ajax.php?act=checkSharePwd', {
        token: shareToken,
        pwd: pwd
    }, function(res){
        layer.close(ii);
        if(res.code == 0){
            window._sharePwd = pwd;
            $('#pwdBox').hide();
            $('#saveShareBtn').show();
            renderFileList(res.files);
        }else{
            $('#pwdError').show();
        }
    }, 'json');
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
