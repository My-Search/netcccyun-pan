<?php
include("./includes/common.php");
if(!$islogin2){
    @header('Content-Type: text/html; charset=UTF-8');
    exit("<script language='javascript'>alert('请先登录');window.location.href='./login.php';</script>");
}
$title = '我的分享 - ' . $conf['title'];
$is_file = false;
$csrf_token = createCsrfToken();
include SYSTEM_ROOT.'header.php';
?>
<style>
.shares-container { max-width: 900px; margin: 0 auto; padding: 20px; }
.shares-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.shares-header h3 { margin: 0; font-size: 18px; }
.share-card {
    background: #fff; border-radius: 10px; padding: 18px 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #eee;
    margin-bottom: 12px; display: flex; align-items: center; gap: 16px;
    transition: all 0.2s;
}
.share-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.share-icon { font-size: 40px; color: #5bc0de; flex-shrink: 0; width: 48px; text-align: center; }
.share-info { flex: 1; min-width: 0; }
.share-title { font-size: 15px; font-weight: 500; color: #333; margin-bottom: 6px; word-break: break-all; }
.share-meta { font-size: 12px; color: #999; }
.share-meta span { margin-right: 14px; }
.share-meta i { margin-right: 3px; }
.share-actions { flex-shrink: 0; display: flex; gap: 6px; }
.share-actions .btn { padding: 5px 12px; font-size: 12px; }
.empty-tip { text-align:center; padding:60px 20px; color:#999; }
.empty-tip i { font-size:48px; margin-bottom:12px; display:block; color:#ddd; }
</style>

<div class="container shares-container" id="sharesApp">
    <div class="shares-header">
        <h3><i class="fa fa-share-alt" style="color:#5bc0de; margin-right:6px;"></i>我的分享</h3>
        <a href="./mine.php" class="btn btn-default btn-sm"><i class="fa fa-folder"></i> 返回文件管理</a>
    </div>
    <div id="sharesList">
        <div class="empty-tip"><i class="fa fa-spinner fa-spin" style="font-size:24px; color:#999;"></i><p>加载中...</p></div>
    </div>
</div>

<?php include SYSTEM_ROOT.'footer.php';?>
<script src="https://s4.zstatic.net/ajax/libs/layer/2.3/layer.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/clipboard.js/1.7.1/clipboard.min.js"></script>
<script>
var siteurl = '<?php echo $siteurl; ?>';
var csrf_token = '<?php echo $csrf_token; ?>';

$(function(){
    loadShares();
});

function loadShares(){
    var ii = layer.load(1, {shade:[0.1,'#fff']});
    $.get('ajax.php?act=listMyShares', function(res){
        layer.close(ii);
        if(res.code == 0){
            renderShares(res.data);
        }else{
            $('#sharesList').html('<div class="empty-tip"><i class="fa fa-exclamation-circle"></i><p>'+escapeHtml(res.msg)+'</p></div>');
        }
    }, 'json');
}

function renderShares(shares){
    if(!shares || shares.length == 0){
        $('#sharesList').html('<div class="empty-tip"><i class="fa fa-share-alt"></i><p>暂无分享记录</p><p style="font-size:13px; margin-top:8px;">在文件管理页面右键点击文件夹即可创建分享</p></div>');
        return;
    }
    var html = '';
    for(var i=0; i<shares.length; i++){
        var s = shares[i];
        html += '<div class="share-card">';
        html += '<div class="share-icon"><i class="fa fa-folder-open-o"></i></div>';
        html += '<div class="share-info">';
        html += '<div class="share-title">'+escapeHtml(s.folder_name || '未知文件夹')+'</div>';
        html += '<div class="share-meta">';
        html += '<span><i class="fa fa-clock-o"></i> '+s.addtime+'</span>';
        html += '<span><i class="fa fa-eye"></i> '+s.views+' 次访问</span>';
        if(s.has_pwd){
            html += '<span style="color:#f0ad4e;"><i class="fa fa-lock"></i> 有密码</span>';
        }else{
            html += '<span style="color:#5cb85c;"><i class="fa fa-unlock"></i> 公开</span>';
        }
        html += '</div>';
        html += '</div>';
        html += '<div class="share-actions">';
        html += '<button class="btn btn-primary btn-sm" onclick="copyShareLink(\''+escapeHtml(s.shareurl)+'\')"><i class="fa fa-link"></i> 复制链接</button>';
        html += '<button class="btn btn-warning btn-sm" onclick="editSharePwd('+s.id+', '+s.has_pwd+')"><i class="fa fa-key"></i> '+(s.has_pwd?'修改':'设置')+'密码</button>';
        html += '<button class="btn btn-danger btn-sm" onclick="cancelShare('+s.id+')"><i class="fa fa-times"></i> 取消分享</button>';
        html += '</div>';
        html += '</div>';
    }
    $('#sharesList').html(html);
}

function copyShareLink(url){
    var $temp = $('<input>');
    $('body').append($temp);
    $temp.val(url).select();
    document.execCommand('copy');
    $temp.remove();
    layer.msg('分享链接已复制到剪贴板', {icon:1});
}

function editSharePwd(id, hasPwd){
    var title = hasPwd ? '修改分享密码' : '设置分享密码';
    var html = '<div style="padding:15px;">';
    html += '<p>访问密码（留空表示取消密码，公开分享）：</p>';
    html += '<input type="text" id="editSharePwdInput" class="form-control" placeholder="请输入密码" maxlength="16">';
    html += '<p style="margin-top:10px; color:#999; font-size:12px;">密码只能为字母和数字</p>';
    html += '</div>';
    layer.open({
        type: 1,
        title: title,
        area: ['400px', '240px'],
        content: html,
        btn: ['确认', '取消'],
        yes: function(index){
            var pwd = $('#editSharePwdInput').val().trim();
            if(pwd && !/^[a-zA-Z0-9]+$/.test(pwd)){
                layer.msg('密码只能为字母和数字', {icon:2});
                return;
            }
            var ii = layer.load(1);
            $.post('ajax.php?act=updateSharePwd', {id:id, pwd:pwd, csrf_token:csrf_token}, function(res){
                layer.close(ii);
                if(res.code == 0){
                    layer.close(index);
                    layer.msg(res.msg, {icon:1});
                    loadShares();
                }else{
                    layer.msg(res.msg, {icon:2});
                }
            }, 'json');
        }
    });
}

function cancelShare(id){
    layer.confirm('确定取消该分享吗？取消后分享链接将失效。', function(index){
        var ii = layer.load(1);
        $.post('ajax.php?act=deleteShare', {id:id}, function(res){
            layer.close(ii);
            if(res.code == 0){
                layer.close(index);
                layer.msg('分享已取消', {icon:1});
                loadShares();
            }else{
                layer.msg(res.msg, {icon:2});
            }
        }, 'json');
    });
}

function escapeHtml(text){
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}
</script>
</body>
</html>
