<?php
include("./includes/common.php");
@header('Referrer-Policy: no-referrer');

$title = '邀请上传 - '.$conf['title'];
$is_file = false;
$csrf_token = md5(mt_rand(0,999).time());
$_SESSION['csrf_token'] = $csrf_token;

$token = isset($_GET['token'])?trim($_GET['token']):'';
$pwd = isset($_GET['pwd'])?trim($_GET['pwd']):'';
if(empty($token) || !preg_match('/^[0-9a-z]{32}$/i', $token)){
    showmsg('邀请链接无效', 4);
}

$invite = $DB->getRow("SELECT * FROM pre_upload_invite WHERE token=:token", [':token'=>$token]);
if(!$invite){
    showmsg('邀请不存在或已失效', 4);
}
if(intval($invite['enable']) !== 1){
    showmsg('邀请已失效', 4);
}
if(!empty($invite['expire_time']) && strtotime($invite['expire_time']) < time()){
    showmsg('邀请已失效', 4);
}
if(intval($invite['folder_id']) > 0){
    $folder = $DB->getRow("SELECT id FROM pre_folder WHERE id=:id AND uid=:uid", [':id'=>$invite['folder_id'], ':uid'=>$invite['uid']]);
    if(!$folder){
        showmsg('邀请已失效', 4);
    }
}
if(!preg_match('/^\d{4}$/', $pwd) || $pwd !== $invite['pwd']){
    showmsg('邀请链接无效或已失效', 4);
}

$inviteMaxSize = intval($invite['max_size']);
$inviteMaxSizeText = $inviteMaxSize > 0 ? size_format($inviteMaxSize) : '不限制';

include SYSTEM_ROOT.'header.php';
?>
<style>
.invite-upload-container { max-width: 760px; margin: 30px auto; }
.invite-upload-header { background: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
.invite-upload-header h3 { margin: 0 0 10px 0; }
.invite-upload-header .meta { color: #666; font-size: 13px; }
.invite-upload-box { display:block; }
.invite-dropzone { border: 2px dashed #ccc; border-radius: 8px; padding: 45px 20px; text-align:center; color:#999; background:#fff; transition: all .2s; }
.invite-dropzone.dragover { border-color:#337ab7; background:#f0f8ff; color:#337ab7; }
.invite-progress-wrap { display:none; margin-top:20px; }
</style>

<div class="container invite-upload-container" id="inviteUploadApp">
    <div class="invite-upload-header">
        <h3><i class="fa fa-cloud-upload"></i> 邀请上传</h3>
        <div class="meta">
            <span><i class="fa fa-hdd-o"></i> 单文件限制：<?php echo $inviteMaxSizeText?></span>
            <span style="margin-left:15px;"><i class="fa fa-calendar"></i> 有效期：<?php echo !empty($invite['expire_time']) ? $invite['expire_time'] : '长期有效'?></span>
        </div>
    </div>

    <div class="well invite-upload-box" id="uploadBox">
        <input type="hidden" id="csrf_token" value="<?php echo $csrf_token?>">
        <input type="file" id="inviteFile" style="display:none" onchange="selectInviteFile(this.files)">
        <div class="invite-dropzone" id="inviteDropzone" onclick="document.getElementById('inviteFile').click()">
            <p><i class="fa fa-cloud-upload" style="font-size:44px;"></i></p>
            <h4>点击选择文件，或拖拽文件到这里</h4>
            <p>无需登录，打开邀请链接即可上传文件</p>
        </div>
        <div class="invite-progress-wrap" id="progressWrap">
            <div class="progress progress-striped active"><div class="progress-bar" id="progressBar" style="width:0%">0%</div></div>
            <div class="text-center" id="uploadStatus"></div>
        </div>
    </div>
</div>

<?php include SYSTEM_ROOT.'footer.php';?>
<script src="https://s4.zstatic.net/ajax/libs/layer/3.1.1/layer.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/spark-md5/3.0.2/spark-md5.min.js"></script>
<script>
var inviteToken = '<?php echo htmlspecialchars($token)?>';
var invitePwd = '<?php echo htmlspecialchars($pwd)?>';
var uploadHash = '';
var uploadSize = 0;
var inviteMaxSize = <?php echo $inviteMaxSize; ?>;

function selectInviteFile(files){
    if(!files || files.length === 0) return;
    uploadInviteFile(files[0]);
}

function initInviteDropzone(){
    var zone = document.getElementById('inviteDropzone');
    zone.addEventListener('dragover', function(e){ e.preventDefault(); zone.classList.add('dragover'); });
    zone.addEventListener('dragleave', function(e){ e.preventDefault(); zone.classList.remove('dragover'); });
    zone.addEventListener('drop', function(e){
        e.preventDefault();
        zone.classList.remove('dragover');
        if(e.dataTransfer.files && e.dataTransfer.files.length > 0){
            uploadInviteFile(e.dataTransfer.files[0]);
        }
    });
}

function setProgress(percent, text){
    percent = Math.max(0, Math.min(100, percent));
    $('#progressWrap').show();
    $('#progressBar').css('width', percent + '%').text(percent + '%');
    $('#uploadStatus').text(text || '');
}

/**
 * 主流程：计算文件 MD5 后调用预上传，再按服务端返回的分片配置上传并完成保存。
 */
async function uploadInviteFile(file){
    if(!invitePwd){
        layer.msg('请先输入上传密码', {icon:2});
        return;
    }
    if(inviteMaxSize > 0 && file.size > inviteMaxSize){
        layer.msg('文件超过邀请允许的大小上限：<?php echo $inviteMaxSizeText?>', {icon:2});
        return;
    }
    setProgress(0, '正在计算文件指纹...');
    uploadHash = await computeFileHash(file);
    uploadSize = file.size;
    if(!uploadHash){
        layer.msg('文件读取失败，请重试', {icon:2});
        return;
    }
    $.post('ajax.php?act=pre_upload', {
        csrf_token: $('#csrf_token').val(),
        invite_token: inviteToken,
        invite_pwd: invitePwd,
        name: file.name,
        hash: uploadHash,
        size: file.size,
        ispwd: 0,
        pwd: '',
        folder_id: 0,
        relative_path: ''
    }, function(res){
        if(res.code == 1){
            setProgress(100, res.msg || '上传完成');
            layer.msg('上传成功', {icon:1});
            return;
        }
        if(res.code != 0){
            layer.msg(res.msg || '预上传失败', {icon:2});
            setProgress(0, res.msg || '预上传失败');
            return;
        }
        if(res.third){
            uploadThird(res.url, res.post, file);
        }else{
            uploadChunks(file, res.chunksize, res.chunks, 1);
        }
    }, 'json');
}

function uploadChunks(file, chunkSize, chunks, chunk){
    var start = (chunk - 1) * chunkSize;
    var end = Math.min(start + chunkSize, file.size);
    var formData = new FormData();
    formData.append('file', file.slice(start, end));
    formData.append('chunk', chunk);
    formData.append('hash', uploadHash);
    formData.append('csrf_token', $('#csrf_token').val());
    $.ajax({
        url: 'ajax.php?act=upload_part',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(res){
            if(res.code == 0 && chunk < chunks){
                setProgress(Math.floor(chunk / chunks * 100), '正在上传第 ' + chunk + '/' + chunks + ' 个分片');
                uploadChunks(file, chunkSize, chunks, chunk + 1);
            }else if(res.code == 1){
                setProgress(100, '上传完成');
                layer.msg('上传成功', {icon:1});
            }else{
                setProgress(0, res.msg || '上传失败');
                layer.msg(res.msg || '上传失败', {icon:2});
            }
        },
        error: function(){
            setProgress(0, '网络错误，请重试');
            layer.msg('网络错误，请重试', {icon:2});
        }
    });
}

function uploadThird(url, postdata, file){
    var data = new FormData();
    for(var key in postdata){
        data.append(key, postdata[key]);
    }
    data.append('file', file);
    $.ajax({
        url: url,
        type: 'POST',
        data: data,
        processData: false,
        contentType: false,
        success: function(){ completeInviteUpload(); },
        error: function(){ layer.msg('第三方上传失败', {icon:2}); }
    });
}

function completeInviteUpload(){
    $.post('ajax.php?act=complete_upload', {hash: uploadHash, csrf_token: $('#csrf_token').val()}, function(res){
        if(res.code == 1 || res.code == 0){
            setProgress(100, '上传完成');
            layer.msg('上传成功', {icon:1});
        }else{
            setProgress(0, res.msg || '保存失败');
            layer.msg(res.msg || '保存失败', {icon:2});
        }
    }, 'json');
}

function computeFileHash(file){
    return new Promise(function(resolve){
        var chunkSize = 2 * 1024 * 1024;
        var chunks = Math.ceil(file.size / chunkSize);
        var spark = new SparkMD5.ArrayBuffer();
        var fileReader = new FileReader();
        var currentChunk = 0;
        fileReader.onload = function(e){
            spark.append(e.target.result);
            currentChunk++;
            setProgress(Math.floor(currentChunk / chunks * 20), '正在计算文件指纹...');
            if(currentChunk < chunks){
                loadNext();
            }else{
                resolve(spark.end());
            }
        };
        fileReader.onerror = function(){ resolve(''); };
        function loadNext(){
            var start = currentChunk * chunkSize;
            var end = Math.min(start + chunkSize, file.size);
            fileReader.readAsArrayBuffer(file.slice(start, end));
        }
        loadNext();
    });
}

$(function(){ initInviteDropzone(); });
</script>
</body>
</html>
