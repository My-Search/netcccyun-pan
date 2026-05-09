<?php
include("./includes/common.php");
if(!$islogin2){
    @header('Content-Type: text/html; charset=UTF-8');
    exit("<script language='javascript'>alert('请先登录');window.location.href='./login.php';</script>");
}
$title = '我的邀请 - ' . $conf['title'];
$is_file = false;
$csrf_token = md5(mt_rand(0,999).time());
$_SESSION['csrf_token'] = $csrf_token;
include SYSTEM_ROOT.'header.php';
?>
<style>
.invites-container { max-width: 980px; margin: 0 auto; padding: 20px; }
.invites-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.invites-header h3 { margin: 0; font-size: 18px; }
.invite-card { background:#fff; border-radius:10px; padding:18px 20px; box-shadow:0 2px 8px rgba(0,0,0,0.06); border:1px solid #eee; margin-bottom:12px; display:flex; align-items:center; gap:16px; }
.invite-icon { font-size:40px; color:#f0ad4e; flex-shrink:0; width:48px; text-align:center; }
.invite-info { flex:1; min-width:0; }
.invite-title { font-size:15px; font-weight:500; color:#333; margin-bottom:6px; word-break:break-all; }
.invite-meta { font-size:12px; color:#999; line-height:1.8; }
.invite-meta span { margin-right:14px; }
.invite-remark { color:#8a6d3b; font-size:12px; line-height:1.5; max-width:560px; overflow:hidden; text-overflow:ellipsis; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; }
.invite-actions { flex-shrink:0; display:flex; gap:6px; flex-wrap:wrap; justify-content:flex-end; }
.invite-actions .btn { padding:5px 12px; font-size:12px; }
.empty-tip { text-align:center; padding:60px 20px; color:#999; }
.empty-tip i { font-size:48px; margin-bottom:12px; display:block; color:#ddd; }
</style>

<div class="container invites-container" id="invitesApp">
    <div class="invites-header">
        <h3><i class="fa fa-user-plus" style="color:#f0ad4e; margin-right:6px;"></i>我的上传邀请</h3>
        <a href="./mine.php" class="btn btn-default btn-sm"><i class="fa fa-folder"></i> 返回文件管理</a>
    </div>
    <div id="invitesList">
        <div class="empty-tip"><i class="fa fa-spinner fa-spin" style="font-size:24px; color:#999;"></i><p>加载中...</p></div>
    </div>
</div>

<?php include SYSTEM_ROOT.'footer.php';?>
<script src="https://s4.zstatic.net/ajax/libs/layer/2.3/layer.js"></script>
<script>
var csrf_token = '<?php echo $csrf_token; ?>';

$(function(){ loadInvites(); });

function loadInvites(){
    var ii = layer.load(1, {shade:[0.1,'#fff']});
    $.get('ajax.php?act=listMyUploadInvites', function(res){
        layer.close(ii);
        if(res.code == 0){
            renderInvites(res.data);
        }else{
            $('#invitesList').html('<div class="empty-tip"><i class="fa fa-exclamation-circle"></i><p>'+escapeHtml(res.msg)+'</p></div>');
        }
    }, 'json');
}

function renderInvites(invites){
    if(!invites || invites.length == 0){
        $('#invitesList').html('<div class="empty-tip"><i class="fa fa-user-plus"></i><p>暂无上传邀请</p><p style="font-size:13px; margin-top:8px;">进入文件夹后点击“邀请上传”即可创建</p></div>');
        return;
    }
    var html = '';
    for(var i=0; i<invites.length; i++){
        var item = invites[i];
        var enabled = parseInt(item.enable, 10) === 1;
        html += '<div class="invite-card">';
        html += '<div class="invite-icon"><i class="fa fa-cloud-upload"></i></div>';
        html += '<div class="invite-info">';
        html += '<div class="invite-title">'+escapeHtml(item.folder_name || '未知文件夹')+'</div>';
        html += '<div class="invite-meta">';
        html += '<span><i class="fa fa-clock-o"></i> 创建：'+item.addtime+'</span>';
        html += '<span><i class="fa fa-upload"></i> 成功上传：'+item.uploads+' 次</span>';
        html += '<span><i class="fa fa-hdd-o"></i> 限制：'+item.max_size_mb+' MB</span>';
        html += '<span><i class="fa fa-calendar"></i> 有效期：'+(item.expire_time || '长期有效')+'</span>';
        html += '<span style="color:'+(enabled && !item.expired ? '#5cb85c' : '#d9534f')+';"><i class="fa fa-circle"></i> '+escapeHtml(item.status_text)+'</span>';
        html += '</div>';
        if(item.remark){
            html += '<div class="invite-remark" title="'+escapeHtml(item.remark)+'"><i class="fa fa-sticky-note-o"></i> '+escapeHtml(item.remark)+'</div>';
        }
        html += '</div>';
        html += '<div class="invite-actions">';
        html += '<button class="btn btn-primary btn-sm" onclick="copyInviteLink(\''+escapeJs(item.inviteurl)+'\')"><i class="fa fa-link"></i> 复制链接</button>';
        html += '<button class="btn btn-warning btn-sm" onclick="editInvite('+item.id+', '+item.max_size_mb+', '+(enabled?1:0)+', \''+escapeJs(item.remark || '')+'\')"><i class="fa fa-cog"></i> 设置</button>';
        html += '<button class="btn btn-info btn-sm" onclick="toggleInvite('+item.id+', '+(enabled?0:1)+')"><i class="fa fa-power-off"></i> '+(enabled?'停用':'启用')+'</button>';
        html += '<button class="btn btn-danger btn-sm" onclick="deleteInvite('+item.id+')"><i class="fa fa-trash"></i> 删除</button>';
        html += '</div></div>';
    }
    $('#invitesList').html(html);
}

function copyInviteLink(url){
    var $temp = $('<input>');
    $('body').append($temp);
    $temp.val(url).select();
    document.execCommand('copy');
    $temp.remove();
    layer.msg('邀请链接已复制到剪贴板', {icon:1});
}

function editInvite(id, maxSizeMb, enable, remark){
    var html = '<div style="padding:15px;">';
    html += '<p>单文件大小限制（MB）：</p>';
    html += '<input type="number" id="editInviteMaxSizeMb" class="form-control" min="1" value="'+maxSizeMb+'">';
    html += '<p style="margin-top:12px;">从现在起重设有效时长（小时，留空保持不变，0 表示长期有效）：</p>';
    html += '<input type="number" id="editInviteExpireHours" class="form-control" min="0" placeholder="留空保持不变">';
    html += '<p style="margin-top:12px;">上传文件备注（可选）：</p>';
    html += '<textarea id="editInviteRemark" class="form-control" maxlength="200" rows="3" placeholder="上传成功后显示在文件名下方">'+escapeHtml(remark || '')+'</textarea>';
    html += '<div class="checkbox" style="margin-top:12px;"><label><input type="checkbox" id="editInviteEnable" '+(enable ? 'checked' : '')+'> 启用邀请</label></div>';
    html += '</div>';
    layer.open({
        type: 1,
        title: '修改邀请设置',
        area: ['450px', '440px'],
        content: html,
        btn: ['保存', '取消'],
        yes: function(index){
            var maxSize = parseInt($('#editInviteMaxSizeMb').val(), 10) || 1024;
            var expireRaw = $('#editInviteExpireHours').val().trim();
            if(expireRaw !== '' && !/^\d+$/.test(expireRaw)){
                layer.msg('有效时长必须为非负整数', {icon:2});
                return;
            }
            var expireHours = expireRaw === '' ? '' : parseInt(expireRaw, 10);
            var enabled = $('#editInviteEnable').is(':checked') ? 1 : 0;
            var remark = $('#editInviteRemark').val().trim();
            if(maxSize <= 0){ layer.msg('大小限制必须大于0', {icon:2}); return; }
            if(expireHours !== '' && expireHours < 0){ layer.msg('有效时长不能小于0', {icon:2}); return; }
            $.post('ajax.php?act=updateUploadInvite', {id:id, max_size_mb:maxSize, expire_hours:expireHours, remark:remark, enable:enabled, csrf_token:csrf_token}, function(res){
                if(res.code == 0){
                    layer.close(index);
                    layer.msg(res.msg, {icon:1});
                    loadInvites();
                }else{
                    layer.msg(res.msg, {icon:2});
                }
            }, 'json');
        }
    });
}

function toggleInvite(id, enable){
    $.post('ajax.php?act=toggleUploadInvite', {id:id, enable:enable, csrf_token:csrf_token}, function(res){
        if(res.code == 0){
            layer.msg(res.msg, {icon:1});
            loadInvites();
        }else{
            layer.msg(res.msg, {icon:2});
        }
    }, 'json');
}

function deleteInvite(id){
    layer.confirm('确定删除该邀请吗？删除后邀请链接将失效。', function(index){
        $.post('ajax.php?act=deleteUploadInvite', {id:id, csrf_token:csrf_token}, function(res){
            if(res.code == 0){
                layer.close(index);
                layer.msg('邀请已删除', {icon:1});
                loadInvites();
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

function escapeJs(text){
    return String(text).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/\r/g, '\\r').replace(/\n/g, '\\n');
}
</script>
</body>
</html>
