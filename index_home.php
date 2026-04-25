<?php
$title = '首页 - ' . $conf['title'];
$is_file = false;
$csrf_token = md5(mt_rand(0,999).time());
$_SESSION['csrf_token'] = $csrf_token;
include SYSTEM_ROOT.'header.php';
?>
<style>
.home-container { max-width: 1100px; margin: 0 auto; padding: 20px; }

/* 统计卡片 */
.stats-row { display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
.stat-card {
    flex: 1; min-width: 200px;
    color: white;
    border-radius: 10px; padding: 20px; text-align: center;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: transform 0.2s;
}
.stat-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,0.15); }
.stat-card.blue { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.stat-card.green { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
.stat-card.orange { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
.stat-card.red { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.stat-icon { font-size: 32px; margin-bottom: 10px; color: rgba(255,255,255,0.9); }
.stat-value { font-size: 28px; font-weight: 700; color: white; margin-bottom: 4px; }
.stat-label { font-size: 13px; color: rgba(255,255,255,0.85); }

/* 分类浏览区 */
.section-title { font-size: 16px; font-weight: 600; margin-bottom: 16px; color: #333; }
.section-title i { margin-right: 6px; color: #666; }
.category-row { display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; }
.category-card {
    flex: 1; min-width: 140px; background: #fff;
    border-radius: 10px; padding: 18px; text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    border: 1px solid #eee; cursor: pointer; transition: all 0.2s;
    text-decoration: none; color: #333;
}
.category-card:hover {
    transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border-color: #ccc; color: #333; text-decoration: none;
}
.category-icon { font-size: 32px; margin-bottom: 8px; }
.category-name { font-size: 14px; font-weight: 500; }
.category-count { font-size: 12px; color: #999; margin-top: 4px; }
.category-image .category-icon { color: #5cb85c; }
.category-video .category-icon { color: #d9534f; }
.category-audio .category-icon { color: #5bc0de; }
.category-document .category-icon { color: #f0ad4e; }
.category-other .category-icon { color: #999; }

/* 最近文件 */
.recent-section { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #eee; }
.recent-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.recent-header a { font-size: 13px; color: #337ab7; }
.recent-grid { display: flex; gap: 12px; flex-wrap: wrap; }
.recent-item {
    width: 120px; text-align: center; padding: 12px; border-radius: 8px;
    cursor: pointer; transition: background 0.2s; text-decoration: none; color: #333;
}
.recent-item:hover { background: #f5f5f5; text-decoration: none; color: #333; }
.recent-item .icon-wrap { font-size: 40px; height: 48px; display: flex; align-items: center; justify-content: center; }
.recent-item .item-name { font-size: 12px; word-break: break-all; line-height: 1.3; max-height: 32px; overflow: hidden; margin-top: 6px; }
.recent-item .item-time { font-size: 11px; color: #999; margin-top: 4px; }
.recent-item .fa-folder { color: #f0ad4e; }
.recent-item .fa-file-image-o { color: #5cb85c; }
.recent-item .fa-file-audio-o { color: #5bc0de; }
.recent-item .fa-file-video-o { color: #d9534f; }
.recent-item .fa-file-archive-o { color: #f0ad4e; }
.recent-item .fa-file-text-o { color: #777; }
.recent-item .fa-file-o { color: #999; }
.empty-tip { text-align: center; padding: 40px; color: #999; }

/* 快捷操作 */
.quick-actions { display: flex; gap: 12px; margin-bottom: 24px; }
.quick-actions a {
    flex: 1; padding: 14px; border-radius: 10px; text-align: center;
    background: #fff; border: 1px solid #eee; text-decoration: none;
    color: #333; transition: all 0.2s;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.quick-actions a:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); text-decoration: none; color: #333; }
.quick-actions a i { font-size: 24px; display: block; margin-bottom: 6px; }
.quick-actions a span { font-size: 13px; }
</style>

<div class="home-container" id="homeApp">
    <!-- 统计卡片 -->
    <div class="stats-row" id="statsRow">
        <div class="stat-card blue">
            <div class="stat-icon"><i class="fa fa-files-o"></i></div>
            <div class="stat-value" id="statFiles">-</div>
            <div class="stat-label">我的文件</div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon"><i class="fa fa-hdd-o"></i></div>
            <div class="stat-value" id="statSize">-</div>
            <div class="stat-label">已用空间</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-icon"><i class="fa fa-cloud-upload"></i></div>
            <div class="stat-value" id="statToday">-</div>
            <div class="stat-label">今日上传</div>
        </div>
        <div class="stat-card red">
            <div class="stat-icon"><i class="fa fa-eye"></i></div>
            <div class="stat-value" id="statViews">-</div>
            <div class="stat-label">分享浏览</div>
        </div>
    </div>

    <!-- 快捷操作 -->
    <div class="quick-actions">
        <a href="./mine.php"><i class="fa fa-folder" style="color:#f0ad4e"></i><span>文件管理</span></a>
        <a href="javascript:void(0)" onclick="openUpload()"><i class="fa fa-cloud-upload" style="color:#5cb85c"></i><span>上传文件</span></a>
        <a href="./my_shares.php"><i class="fa fa-share-alt" style="color:#5bc0de"></i><span>我的分享</span></a>
    </div>

    <!-- 分类浏览 -->
    <div class="section-title"><i class="fa fa-th-large"></i> 分类浏览</div>
    <div class="category-row" id="categoryRow">
        <a class="category-card category-image" href="./mine.php?type=image">
            <div class="category-icon"><i class="fa fa-file-image-o"></i></div>
            <div class="category-name">图片</div>
            <div class="category-count" id="countImage">0 个文件</div>
        </a>
        <a class="category-card category-video" href="./mine.php?type=video">
            <div class="category-icon"><i class="fa fa-file-video-o"></i></div>
            <div class="category-name">视频</div>
            <div class="category-count" id="countVideo">0 个文件</div>
        </a>
        <a class="category-card category-audio" href="./mine.php?type=audio">
            <div class="category-icon"><i class="fa fa-file-audio-o"></i></div>
            <div class="category-name">音乐</div>
            <div class="category-count" id="countAudio">0 个文件</div>
        </a>
        <a class="category-card category-document" href="./mine.php?type=document">
            <div class="category-icon"><i class="fa fa-file-text-o"></i></div>
            <div class="category-name">文档</div>
            <div class="category-count" id="countDocument">0 个文件</div>
        </a>
        <a class="category-card category-other" href="./mine.php?type=other">
            <div class="category-icon"><i class="fa fa-file-o"></i></div>
            <div class="category-name">其他</div>
            <div class="category-count" id="countOther">0 个文件</div>
        </a>
    </div>

    <!-- 最近文件 -->
    <div class="recent-section">
        <div class="recent-header">
            <div class="section-title" style="margin-bottom:0"><i class="fa fa-clock-o"></i> 最近文件</div>
            <a href="./mine.php">查看全部 <i class="fa fa-angle-right"></i></a>
        </div>
        <div class="recent-grid" id="recentGrid">
            <div class="empty-tip"><i class="fa fa-spinner fa-spin"></i> 加载中...</div>
        </div>
    </div>
</div>

<?php include SYSTEM_ROOT.'footer.php'; ?>
<script src="https://s4.zstatic.net/ajax/libs/layer/2.3/layer.js"></script>
<script>
var siteurl = '<?php echo $siteurl; ?>';

$(function(){
    loadStats();
    loadRecentFiles();
});

function loadStats(){
    $.get('ajax.php?act=getUserStats', function(res){
        if(res.code == 0){
            $('#statFiles').text(res.data.totalFiles);
            $('#statSize').text(res.data.formattedSize);
            $('#statToday').text(res.data.todayFiles);
            $('#statViews').text(res.data.totalViews);
            $('#countImage').text(res.data.typeStats.image + ' 个文件');
            $('#countVideo').text(res.data.typeStats.video + ' 个文件');
            $('#countAudio').text(res.data.typeStats.audio + ' 个文件');
            $('#countDocument').text(res.data.typeStats.document + ' 个文件');
            $('#countOther').text(res.data.typeStats.other + ' 个文件');
        }else{
            layer.msg(res.msg, {icon:2});
        }
    }, 'json');
}

function loadRecentFiles(){
    $.get('ajax.php?act=getRecentFiles&limit=8', function(res){
        if(res.code == 0){
            renderRecentFiles(res.data);
        }else{
            $('#recentGrid').html('<div class="empty-tip">'+res.msg+'</div>');
        }
    }, 'json');
}

function renderRecentFiles(files){
    if(!files || files.length == 0){
        $('#recentGrid').html('<div class="empty-tip"><i class="fa fa-folder-open-o" style="font-size:36px;"></i><p>暂无文件，快去上传吧！</p></div>');
        return;
    }
    var html = '';
    for(var i=0; i<files.length; i++){
        var f = files[i];
        var viewurl = './file.php?hash='+f.hash;
        html += '<a class="recent-item" href="'+viewurl+'" target="_blank">';
        html += '<div class="icon-wrap"><i class="fa '+typeToIcon(f.type)+'"></i></div>';
        html += '<div class="item-name">'+escapeHtml(f.name)+'</div>';
        html += '<div class="item-time">'+formatTime(f.addtime)+'</div>';
        html += '</a>';
    }
    $('#recentGrid').html(html);
}

function openUpload(){
    layer.msg('请前往 我的文件 页面进行上传', {icon:0});
    setTimeout(function(){ window.location.href='./mine.php'; }, 1500);
}

function typeToIcon(type){
    var img = ['png','jpg','jpeg','gif','bmp','webp','ico','svg'];
    var audio = ['mp3','wav','ogg','m4a','flac','aac'];
    var video = ['mp4','webm','flv','mov','avi','mkv'];
    if(img.indexOf(type)>-1) return 'fa-file-image-o';
    if(audio.indexOf(type)>-1) return 'fa-file-audio-o';
    if(video.indexOf(type)>-1) return 'fa-file-video-o';
    if(type=='zip'||type=='rar'||type=='7z') return 'fa-file-archive-o';
    if(type=='txt'||type=='md') return 'fa-file-text-o';
    return 'fa-file-o';
}

function escapeHtml(text){
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}

function formatTime(time){
    var d = new Date(time);
    var now = new Date();
    var diff = (now - d) / 1000;
    if(diff < 60) return '刚刚';
    if(diff < 3600) return Math.floor(diff/60) + '分钟前';
    if(diff < 86400) return Math.floor(diff/3600) + '小时前';
    if(diff < 604800) return Math.floor(diff/86400) + '天前';
    return (d.getMonth()+1)+'/'+d.getDate();
}
</script>
</body>
</html>
