<?php
$title = '首页 - ' . $conf['title'];
$is_file = false;
$csrf_token = createCsrfToken();
include SYSTEM_ROOT.'header.php';
?>
<style>
body { background: #f4f6f8; }
.home-container { max-width: 1120px; margin: 0 auto; padding: 24px 20px 40px; }
.home-page-title { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 18px; }
.home-page-title h3 { margin: 0; font-size: 20px; font-weight: 700; color: #111827; }
.home-page-title p { margin: 6px 0 0; color: #6b7280; font-size: 13px; }
.home-section { margin-top: 22px; }

/* 统计卡片 */
.stats-row { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 18px; }
.stat-card {
    background: #fff; border-radius: 12px; padding: 20px; text-align: left;
    border: 1px solid #e5e7eb; box-shadow: 0 8px 24px rgba(15,23,42,0.04);
    transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(15,23,42,0.08); border-color: #cbd5e1; }
.stat-card.blue { color: #2563eb; }
.stat-card.green { color: #059669; }
.stat-card.orange { color: #d97706; }
.stat-card.red { color: #db2777; }
.stat-icon { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-bottom: 14px; font-size: 22px; color: currentColor; background: rgba(37,99,235,0.08); }
.stat-card.green .stat-icon { background: rgba(5,150,105,0.08); }
.stat-card.orange .stat-icon { background: rgba(217,119,6,0.10); }
.stat-card.red .stat-icon { background: rgba(219,39,119,0.08); }
.stat-value { font-size: 28px; line-height: 1; font-weight: 700; color: #111827; margin-bottom: 8px; letter-spacing: -0.02em; }
.stat-label { font-size: 13px; color: #6b7280; }

/* 快捷操作 */
.quick-actions { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin-bottom: 22px; }
.quick-actions a {
    display: flex; align-items: center; justify-content: center; gap: 10px;
    padding: 16px; border-radius: 12px; text-align: center;
    background: #fff; border: 1px solid #e5e7eb; text-decoration: none;
    color: #374151; transition: all 0.2s; box-shadow: 0 6px 18px rgba(15,23,42,0.04);
}
.quick-actions a:hover { transform: translateY(-2px); border-color: #93c5fd; box-shadow: 0 10px 24px rgba(37,99,235,0.10); text-decoration: none; color: #1f2937; }
.quick-actions a i { font-size: 20px; width: 24px; text-align: center; }
.quick-actions a span { font-size: 14px; font-weight: 600; }

/* 分类浏览区 */
.section-title { font-size: 16px; font-weight: 700; margin-bottom: 14px; color: #111827; display: flex; align-items: center; }
.section-title i { margin-right: 8px; color: #4b5563; }
.category-row { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 14px; margin-bottom: 22px; }
.category-card {
    background: #fff; border-radius: 12px; padding: 20px 16px; text-align: center;
    box-shadow: 0 6px 18px rgba(15,23,42,0.04);
    border: 1px solid #e5e7eb; cursor: pointer; transition: all 0.2s;
    text-decoration: none; color: #1f2937;
}
.category-card:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(15,23,42,0.08); border-color: #cbd5e1; color: #111827; text-decoration: none; }
.category-icon { width: 44px; height: 44px; margin: 0 auto 10px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; background: #f8fafc; }
.category-name { font-size: 14px; font-weight: 700; }
.category-count { font-size: 12px; color: #6b7280; margin-top: 5px; }
.category-image .category-icon { color: #16a34a; background: #f0fdf4; }
.category-video .category-icon { color: #dc2626; background: #fef2f2; }
.category-audio .category-icon { color: #0284c7; background: #f0f9ff; }
.category-document .category-icon { color: #d97706; background: #fffbeb; }
.category-other .category-icon { color: #64748b; background: #f8fafc; }

/* 最近文件 */
.recent-section { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 8px 24px rgba(15,23,42,0.04); border: 1px solid #e5e7eb; }
.recent-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.recent-header .section-title { margin-bottom: 0; }
.recent-header a { font-size: 13px; color: #2563eb; font-weight: 600; }
.recent-grid { display: grid; grid-template-columns: repeat(8, minmax(0, 1fr)); gap: 12px; }
.recent-item { text-align: center; padding: 12px 8px; border-radius: 10px; cursor: pointer; transition: all 0.2s; text-decoration: none; color: #374151; border: 1px solid transparent; }
.recent-item:hover { background: #f8fafc; border-color: #e5e7eb; text-decoration: none; color: #111827; }
.recent-item .icon-wrap { font-size: 34px; height: 42px; display: flex; align-items: center; justify-content: center; }
.recent-item .item-name { font-size: 12px; word-break: break-all; line-height: 1.35; max-height: 34px; overflow: hidden; margin-top: 7px; }
.recent-item .item-time { font-size: 11px; color: #9ca3af; margin-top: 4px; }
.recent-item .fa-folder { color: #d97706; }
.recent-item .fa-file-image-o { color: #16a34a; }
.recent-item .fa-file-audio-o { color: #0284c7; }
.recent-item .fa-file-video-o { color: #dc2626; }
.recent-item .fa-file-archive-o { color: #d97706; }
.recent-item .fa-file-text-o { color: #64748b; }
.recent-item .fa-file-o { color: #94a3b8; }
.empty-tip { text-align: center; padding: 40px; color: #9ca3af; grid-column: 1 / -1; }
@media (max-width: 991px) {
    .stats-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .category-row { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .recent-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}
@media (max-width: 640px) {
    .home-page-title { display: block; }
    .stats-row, .quick-actions, .category-row { grid-template-columns: 1fr; }
    .recent-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>

<div class="home-container" id="homeApp">
    <div class="home-page-title">
        <div>
            <h3>网盘概览</h3>
            <p>集中查看文件资产、分类统计与最近动态</p>
        </div>
    </div>

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
        <a href="./my_invites.php"><i class="fa fa-user-plus" style="color:#5cb85c"></i><span>我的邀请</span></a>
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
