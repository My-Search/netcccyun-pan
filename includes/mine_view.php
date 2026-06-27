<?php
$title = '我的文件 - ' . $conf['title'];
$is_file = false;
$csrf_token = createCsrfToken();
include SYSTEM_ROOT.'header.php';
?>
<link rel="stylesheet" href="./assets/css/mine.css?v=<?php echo VERSION?>">

<div class="container" id="mineApp">
    <div class="mine-toolbar">
        <div class="mine-toolbar-breadcrumb-row">
            <div class="mine-breadcrumb" id="breadcrumb">
                <a onclick="loadFolder(0)">根目录</a>
            </div>
        </div>
        <div class="mine-toolbar-actions-row pull-right mine-toolbar-actions">
            <div class="input-group input-group-sm mine-search">
                <input type="text" class="form-control" id="mineSearchKw" placeholder="搜索文件/文件夹">
                <span class="input-group-btn">
                    <button class="btn btn-default" type="button" onclick="applyMineSearch()" title="搜索" aria-label="搜索"><i class="fa fa-search"></i></button>
                    <button class="btn btn-default" type="button" onclick="clearMineSearch()" title="清空搜索" aria-label="清空搜索"><i class="fa fa-times"></i></button>
                </span>
            </div>
            <button class="btn btn-sm btn-success" onclick="openUploadModal()"><i class="fa fa-cloud-upload"></i> 上传文件</button>
            <button class="btn btn-sm btn-warning" onclick="createUploadInvite()"><i class="fa fa-user-plus"></i> 邀请上传</button>
            <button class="btn btn-sm btn-primary" onclick="createNewFolder()"><i class="fa fa-folder"></i> 新建文件夹</button>
            <button class="btn btn-sm btn-default" onclick="refresh()"><i class="fa fa-refresh"></i> 刷新</button>
        </div>
        <div class="clearfix"></div>
    </div>

    <div class="well bs-component">
        <div id="itemList" class="item-grid"></div>
        <div id="emptyTip" style="display:none; text-align:center; padding:40px; color:#999;">
            <i class="fa fa-folder-open-o" style="font-size:48px;"></i>
            <p>该目录下没有文件</p>
        </div>
    </div>
</div>

<div id="contextMenu" class="context-menu"></div>

<?php include SYSTEM_ROOT.'footer.php';?>
<script src="https://s4.zstatic.net/ajax/libs/layer/2.3/layer.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/spark-md5/3.0.2/spark-md5.min.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/clipboard.js/1.7.1/clipboard.min.js"></script>
<script>
var currentFolderId = 0;
var currentData = {folders:[], files:[]};
var siteurl = '<?php echo $siteurl; ?>';
var currentFilterType = '';
var currentSearchKw = '';
var uploadRefreshTimer = null;
var folderLoadRequest = null;
var folderWatchTimer = null;
var folderVersionRequest = null;
var currentFolderVersion = '';
var folderWatchRetryInterval = 1000;

function getUrlParam(name){
    var reg = new RegExp('(^|&)'+name+'=([^&]*)(&|$)');
    var r = window.location.search.substr(1).match(reg);
    if(r!=null) return decodeURIComponent(r[2]); return null;
}

function normalizeFolderId(folder_id){
    var parsed = parseInt(folder_id, 10);
    return isNaN(parsed) || parsed < 0 ? 0 : parsed;
}

function getInitialFolderId(){
    var folderId = getUrlParam('folder_id');
    if(folderId === null) folderId = getUrlParam('folder');
    if(folderId === null) folderId = getUrlParam('fid');
    return normalizeFolderId(folderId);
}

function normalizeSearchKeyword(value){
    return $.trim(value || '');
}

function getInitialSearchKw(){
    return normalizeSearchKeyword(getUrlParam('kw'));
}

function updateFolderUrl(folder_id, replace){
    if(!window.history || !window.history.pushState) return;

    var normalizedFolderId = normalizeFolderId(folder_id);
    var params = new URLSearchParams(window.location.search);
    var normalizedSearchKw = normalizeSearchKeyword(currentSearchKw);
    if(normalizedFolderId > 0){
        params.set('folder_id', normalizedFolderId);
    }else{
        params.delete('folder_id');
    }
    if(normalizedSearchKw){
        params.set('kw', normalizedSearchKw);
    }else{
        params.delete('kw');
    }
    params.delete('folder');
    params.delete('fid');

    var query = params.toString();
    var newUrl = window.location.pathname + (query ? '?' + query : '') + window.location.hash;
    if(newUrl === window.location.pathname + window.location.search + window.location.hash) return;

    if(replace){
        window.history.replaceState({folder_id: normalizedFolderId}, '', newUrl);
    }else{
        window.history.pushState({folder_id: normalizedFolderId}, '', newUrl);
    }
}

function getTypeLabel(type){
    var map = {'image':'图片','video':'视频','audio':'音乐','document':'文档','other':'其他'};
    return map[type] || type;
}

$(function(){
    currentFilterType = getUrlParam('type') || '';
    currentSearchKw = getInitialSearchKw();
    $('#mineSearchKw').val(currentSearchKw).on('keydown', function(e){
        if(e.keyCode === 13){
            applyMineSearch();
        }
    });
    loadFolder(getInitialFolderId(), {replaceUrl:true});
    window.addEventListener('popstate', function(){
        currentSearchKw = getInitialSearchKw();
        $('#mineSearchKw').val(currentSearchKw);
        loadFolder(getInitialFolderId(), {updateUrl:false});
    });
    $(document).on('click', function(){ $('#contextMenu').hide(); });
    initListDropzone();
    // 阻止浏览器默认拖拽打开文件行为
    $(document).on('dragover dragleave drop', function(e){
        e.preventDefault();
    });
    window.addEventListener('beforeunload', function(e){
        stopFolderWatch();
        if(uploadRunning){
            var msg = '文件正在上传中，离开页面将中断上传，确定要离开吗？';
            e.returnValue = msg;
            return msg;
        }
    });
    document.addEventListener('visibilitychange', function(){
        if(document.hidden){
            stopFolderWatch();
        }else{
            startFolderWatch();
        }
    });
});

function showFolderLoading(){
    $('#emptyTip').hide();
    $('#itemList').html('<div class="mine-inline-loading"><i class="fa fa-spinner fa-spin"></i> 正在加载文件列表...</div>');
}

function loadFolder(folder_id, options){
    options = options || {};
    folder_id = normalizeFolderId(folder_id);
    currentFolderId = folder_id;
    // 如果有上传完成标记且当前没有在上传，自动刷新文件列表
    if(uploadNeedRefresh && !uploadRunning){
        uploadNeedRefresh = false;
    }
    if(folderLoadRequest && folderLoadRequest.readyState !== 4){
        folderLoadRequest.abort();
    }
    showFolderLoading();
    currentSearchKw = normalizeSearchKeyword(currentSearchKw);
    var url = 'ajax.php?act=listMine&folder_id='+folder_id;
    if(currentFilterType){
        url += '&type=' + encodeURIComponent(currentFilterType);
    }
    if(currentSearchKw){
        url += '&kw=' + encodeURIComponent(currentSearchKw);
    }
    folderLoadRequest = $.ajax({
        url: url,
        type: 'GET',
        dataType: 'json',
        timeout: 15000,
        success: function(res){
            if(res.code == 0){
                currentData = res;
                currentFolderId = normalizeFolderId(res.folder_id);
                if(options.updateUrl !== false){
                    updateFolderUrl(currentFolderId, options.replaceUrl === true);
                }
                renderBreadcrumb(res.path);
                renderCurrentItems();
                currentFolderVersion = res.version || '';
                startFolderWatch();
            }else{
                $('#itemList').empty();
                layer.msg(res.msg, {icon:2});
            }
        },
        error: function(xhr, status){
            if(status === 'abort') return;
            $('#itemList').html('<div class="mine-inline-error">文件列表加载失败，请稍后重试</div>');
            layer.msg('文件列表加载失败，请稍后重试', {icon:2});
        },
        complete: function(){
            folderLoadRequest = null;
        }
    });
}

function startFolderWatch(){
    stopFolderWatch();
    if(document.hidden || !currentFolderVersion) return;
    checkCurrentFolderVersion();
}

function stopFolderWatch(){
    if(folderWatchTimer){
        clearTimeout(folderWatchTimer);
        folderWatchTimer = null;
    }
    if(folderVersionRequest && folderVersionRequest.readyState !== 4){
        folderVersionRequest.abort();
    }
    folderVersionRequest = null;
}

function checkCurrentFolderVersion(){
    if(document.hidden){
        stopFolderWatch();
        return;
    }
    if(folderVersionRequest && folderVersionRequest.readyState !== 4) return;

    var watchedFolderId = currentFolderId;
    var shouldContinueWatch = true;
    var url = 'ajax.php?act=mineFolderVersion&folder_id=' + watchedFolderId;
    if(currentFolderVersion){
        url += '&wait=1&since=' + encodeURIComponent(currentFolderVersion);
    }

    folderVersionRequest = $.ajax({
        url: url,
        type: 'GET',
        dataType: 'json',
        timeout: 30000,
        success: function(res){
            if(watchedFolderId !== currentFolderId) return;
            if(res.code == 0 && res.version && currentFolderVersion && res.version !== currentFolderVersion){
                shouldContinueWatch = false;
                loadFolder(currentFolderId, {updateUrl:false});
                return;
            }
            if(res.code == 0 && res.version){
                currentFolderVersion = res.version;
            }
        },
        complete: function(xhr, status){
            folderVersionRequest = null;
            if(status !== 'abort' && shouldContinueWatch && !document.hidden){
                folderWatchTimer = setTimeout(checkCurrentFolderVersion, folderWatchRetryInterval);
            }
        }
    });
}

function applyMineSearch(){
    currentSearchKw = normalizeSearchKeyword($('#mineSearchKw').val());
    $('#mineSearchKw').val(currentSearchKw);
    updateFolderUrl(currentFolderId);
    loadFolder(currentFolderId);
}

function clearMineSearch(){
    if(!currentSearchKw && !normalizeSearchKeyword($('#mineSearchKw').val())) return;
    currentSearchKw = '';
    $('#mineSearchKw').val('');
    updateFolderUrl(currentFolderId);
    loadFolder(currentFolderId);
}

function renderBreadcrumb(path){
    var html = '<a onclick="loadFolder(0)">根目录</a>';
    for(var i=0; i<path.length; i++){
        html += ' / <a onclick="loadFolder('+path[i].id+')">'+escapeHtml(path[i].name)+'</a>';
    }
    if(currentFilterType){
        html += ' <span style="color:#999;margin-left:6px;">['+escapeHtml(getTypeLabel(currentFilterType))+']</span>';
    }
    if(currentSearchKw){
        html += ' <span style="color:#999;margin-left:6px;">[搜索：'+escapeHtml(currentSearchKw)+']</span>';
    }
    $('#breadcrumb').html(html);
}

function filterMineItemsByKeyword(items, keyword){
    if(!keyword) return items || [];
    var result = [];
    items = items || [];
    for(var i=0; i<items.length; i++){
        var name = String(items[i].name || '').toLowerCase();
        if(name.indexOf(keyword) !== -1){
            result.push(items[i]);
        }
    }
    return result;
}

function renderCurrentItems(){
    var keyword = normalizeSearchKeyword(currentSearchKw).toLowerCase();
    var folders = filterMineItemsByKeyword(currentData.folders, keyword);
    var files = filterMineItemsByKeyword(currentData.files, keyword);
    renderItems(folders, files);
    var hasContent = folders.length > 0 || files.length > 0;
    $('#emptyTip p').text(keyword ? '没有找到匹配的文件或文件夹' : '该目录下没有文件');
    $('#emptyTip').toggle(!hasContent);
}

function renderItems(folders, files){
    var html = '';
  // 渲染文件夹
  for(var i=0; i<folders.length; i++){
    html += '<div class="item-card folder-item" draggable="true" data-folder-id="'+folders[i].id+'" data-name="'+escapeHtml(folders[i].name)+'">';
    html += '<div class="icon-wrap"><i class="fa fa-folder"></i></div>';
    html += '<div class="item-name">'+escapeHtml(folders[i].name)+'</div>';
    html += '</div>';
  }
    // 渲染文件
    for(var i=0; i<files.length; i++){
        var f = files[i];
        html += '<div class="item-card file-item" draggable="true" data-file-id="'+f.id+'" data-file-hash="'+f.hash+'" data-name="'+escapeHtml(f.name)+'" data-type="'+f.type+'">';
        html += '<div class="icon-wrap"><i class="fa '+typeToIcon(f.type)+'"></i></div>';
        html += '<div class="item-name">'+escapeHtml(f.name)+'</div>';
        if(f.remark){
            html += '<div class="item-remark" title="'+escapeHtml(f.remark)+'">'+escapeHtml(f.remark)+'</div>';
        }
        html += '</div>';
    }
    $('#itemList').html(html);
    initItemEvents();
}

function initItemEvents(){
    // PC 端双击打开文件夹
    $('#itemList').off('dblclick', '.folder-item').on('dblclick', '.folder-item', function(){
        loadFolder($(this).data('folder-id'));
    });
    // PC 端单击打开文件（触摸操作后不触发）
    $('#itemList').off('click', '.file-item').on('click', '.file-item', function(e){
        if($(this).hasClass('touch-action')) return;
        window.open('./file.php?hash='+$(this).data('file-hash'), '_blank');
    });
    // PC 端右键菜单
    $('#itemList').off('contextmenu', '.folder-item').on('contextmenu', '.folder-item', function(e){
        e.preventDefault();
        folderContextMenu(e, $(this).data('folder-id'), $(this).data('name'));
    });
    $('#itemList').off('contextmenu', '.file-item').on('contextmenu', '.file-item', function(e){
        e.preventDefault();
        var $el = $(this);
        fileContextMenu(e, $el.data('file-id'), $el.data('file-hash'), $el.data('name'), $el.data('type'));
    });

    initDragAndDrop();
    initTouchEvents();
}

function initDragAndDrop(){
  var draggedFileId = null;
  var draggedFolderId = null;

  // 使用事件委托处理拖拽
  $('#itemList').off('dragstart', '.file-item').on('dragstart', '.file-item', function(e){
    draggedFileId = $(this).data('file-id');
    draggedFolderId = null;
    e.originalEvent.dataTransfer.effectAllowed = 'move';
    $(this).css('opacity', '0.5');
  });

  $('#itemList').off('dragstart', '.folder-item').on('dragstart', '.folder-item', function(e){
    draggedFileId = null;
    draggedFolderId = $(this).data('folder-id');
    e.originalEvent.dataTransfer.effectAllowed = 'move';
    $(this).css('opacity', '0.5');
  });

  $('#itemList').off('dragend', '.item-card').on('dragend', '.item-card', function(e){
    $(this).css('opacity', '1');
    draggedFileId = null;
    draggedFolderId = null;
  });

  $('#itemList').off('dragover', '.folder-item').on('dragover', '.folder-item', function(e){
    e.preventDefault();
    e.originalEvent.dataTransfer.dropEffect = 'move';
    $(this).addClass('drag-over');
  });

  $('#itemList').off('dragleave', '.folder-item').on('dragleave', '.folder-item', function(e){
    $(this).removeClass('drag-over');
  });

  $('#itemList').off('drop', '.folder-item').on('drop', '.folder-item', function(e){
    e.preventDefault();
    $(this).removeClass('drag-over');
    var targetFolderId = $(this).data('folder-id');
    if(draggedFileId && targetFolderId !== undefined){
      doMoveFile(draggedFileId, targetFolderId);
    }else if(draggedFolderId !== null && targetFolderId !== undefined){
      // 不能移动到自己
      if(draggedFolderId != targetFolderId){
        doMoveFolder(draggedFolderId, targetFolderId);
      }
    }
  });
}

/* ========== 移动端触摸事件：长按菜单 + 拖拽 ========== */
var touchDragGhost = null;
var touchDragData = null;
var touchDragOffset = {x:0, y:0};
var longPressTimer = null;
var touchStartPos = {x:0, y:0};
var touchActive = false;
var isLongPress = false;
var isTouchDragging = false;
var LONG_PRESS_DELAY = 500;
var MOVE_THRESHOLD = 10;

function initTouchEvents(){
    if(!('ontouchstart' in window)) return;

    $('#itemList').off('touchstart touchmove touchend touchcancel');

    $('#itemList').on('touchstart', '.item-card', function(e){
        var touch = e.originalEvent.touches[0];
        touchStartPos = {x: touch.clientX, y: touch.clientY};
        touchActive = true;
        isLongPress = false;
        isTouchDragging = false;
        var $el = $(this);

        longPressTimer = setTimeout(function(){
            if(!touchActive) return;
            isLongPress = true;
            // 阻止默认行为（如系统菜单、文字选择）
            try { e.preventDefault(); } catch(err){}
            if($el.hasClass('folder-item')){
                showMobileFolderMenu($el.data('folder-id'), $el.data('name'));
            }else{
                showMobileFileMenu($el.data('file-id'), $el.data('file-hash'), $el.data('name'), $el.data('type'));
            }
        }, LONG_PRESS_DELAY);
    });

    $('#itemList').on('touchmove', '.item-card', function(e){
      if(!touchActive) return;
      var touch = e.originalEvent.touches[0];
      var dx = Math.abs(touch.clientX - touchStartPos.x);
      var dy = Math.abs(touch.clientY - touchStartPos.y);

      if(dx > MOVE_THRESHOLD || dy > MOVE_THRESHOLD){
        clearTimeout(longPressTimer);
        if(!isLongPress && !isTouchDragging){
          // 开始触摸拖拽 - 支持文件和文件夹
          var $el = $(this);
          if($el.hasClass('file-item') || $el.hasClass('folder-item')){
            isTouchDragging = true;
            $el.addClass('dragging');
            startTouchDrag($el, touch);
          }
        }
      }

        if(isTouchDragging){
            e.preventDefault();
            moveTouchDrag(touch);
        }
    });

    $('#itemList').on('touchend touchcancel', '.item-card', function(e){
        clearTimeout(longPressTimer);
        touchActive = false;
        var $el = $(this);
        if(isTouchDragging){
            endTouchDrag();
            $el.removeClass('dragging');
        }
        if(isLongPress || isTouchDragging){
            $el.addClass('touch-action');
            setTimeout(function(){ $el.removeClass('touch-action'); }, 300);
        }
        isLongPress = false;
        isTouchDragging = false;
    });
}

function startTouchDrag($el, touch){
  var rect = $el[0].getBoundingClientRect();
  touchDragOffset = {
    x: touch.clientX - rect.left,
    y: touch.clientY - rect.top
  };

  // 支持文件和文件夹拖拽
  if($el.hasClass('file-item')){
    touchDragData = {
      type: 'file',
      fileId: $el.data('file-id')
    };
  }else if($el.hasClass('folder-item')){
    touchDragData = {
      type: 'folder',
      folderId: $el.data('folder-id')
    };
  }

  touchDragGhost = $el.clone().addClass('drag-ghost').css({
    left: rect.left + 'px',
    top: rect.top + 'px',
    width: rect.width + 'px'
  }).appendTo('body');

  $el.css('opacity', '0.3');
}

function moveTouchDrag(touch){
    if(!touchDragGhost) return;
    touchDragGhost.css({
        left: (touch.clientX - touchDragOffset.x) + 'px',
        top: (touch.clientY - touchDragOffset.y) + 'px'
    });

    // 高亮悬停的文件夹
    touchDragGhost.hide();
    var el = document.elementFromPoint(touch.clientX, touch.clientY);
    touchDragGhost.show();
    var $target = $(el).closest('.folder-item');
    $('.folder-item').removeClass('drag-over');
    if($target.length){
        $target.addClass('drag-over');
    }
}

function endTouchDrag(){
  if(!touchDragGhost) return;
  var ghostRect = touchDragGhost[0].getBoundingClientRect();
  var centerX = ghostRect.left + ghostRect.width / 2;
  var centerY = ghostRect.top + ghostRect.height / 2;

  touchDragGhost.hide();
  var el = document.elementFromPoint(centerX, centerY);
  touchDragGhost.show();

  var $target = $(el).closest('.folder-item');
  if($target.length && touchDragData){
    var targetFolderId = $target.data('folder-id');
    if(targetFolderId !== undefined){
      if(touchDragData.type === 'file' && touchDragData.fileId){
        doMoveFile(touchDragData.fileId, targetFolderId);
      }else if(touchDragData.type === 'folder' && touchDragData.folderId !== undefined){
        // 不能移动到自己
        if(touchDragData.folderId != targetFolderId){
          doMoveFolder(touchDragData.folderId, targetFolderId);
        }
      }
    }
  }

  touchDragGhost.remove();
  touchDragGhost = null;
  touchDragData = null;
  $('.item-card').css('opacity', '');
  $('.item-card').removeClass('drag-over');
}

function showMobileFileMenu(fileId, hash, name, type){
  var actions = [];
  actions.push({text: '查看', cls: '', fn: function(){ window.open('./file.php?hash='+hash, '_blank'); }});
  actions.push({text: '复制直链', cls: '', fn: function(){ copyDirectLink(hash, type); }});
  if(isEditableType(type)){
    actions.push({text: '在线编辑', cls: '', fn: function(){ openTextEditor(hash, name, type); }});
  }
  actions.push({text: '移动到', cls: '', fn: function(){ openMoveTree('file', fileId, name); }});
  actions.push({text: '删除', cls: 'mobile-menu-danger', fn: function(){ deleteFile(fileId); }});
  showMobileMenu(name, actions);
}

function showMobileFolderMenu(id, name){
  var actions = [];
  actions.push({text: '打开', cls: '', fn: function(){ loadFolder(id); }});
  actions.push({text: '移动到', cls: '', fn: function(){ openMoveTree('folder', id, name); }});
  actions.push({text: '分享', cls: '', fn: function(){ shareFolder(id, name); }});
  actions.push({text: '重命名', cls: '', fn: function(){ renameFolder(id, name); }});
  actions.push({text: '删除', cls: 'mobile-menu-danger', fn: function(){ deleteFolder(id); }});
  showMobileMenu(name, actions);
}

function showMobileMenu(title, actions){
    var html = '<div style="padding:12px 0 0; text-align:center; font-weight:600; color:#333; font-size:14px; max-width:280px; margin:0 auto; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">'+escapeHtml(title)+'</div>';
    html += '<div class="mobile-menu-list">';
    for(var i=0; i<actions.length; i++){
        html += '<div class="mobile-menu-item '+actions[i].cls+'" data-menu-index="'+i+'">'+escapeHtml(actions[i].text)+'</div>';
    }
    html += '</div>';
    html += '<div class="mobile-menu-cancel" data-menu-action="cancel">取消</div>';

    var idx = layer.open({
        type: 1,
        title: false,
        closeBtn: 0,
        area: ['100%', 'auto'],
        offset: 'b',
        content: html,
        shadeClose: true,
        success: function(layero){
            layero.css({borderRadius: '12px 12px 0 0', padding: '0 0 10px'});
            layero.find('.mobile-menu-item').on('click', function(){
                var i = $(this).data('menu-index');
                if(actions[i] && actions[i].fn){
                    actions[i].fn();
                }
                layer.close(idx);
            });
            layero.find('.mobile-menu-cancel').on('click', function(){
                layer.close(idx);
            });
        }
    });
}

function folderContextMenu(e, id, name){
  e.preventDefault();
  var html = '<a onclick="loadFolder('+id+')">打开</a>';
  html += '<a onclick="openMoveTree(\'folder\', '+id+', \''+name+'\')">移动到</a>';
  html += '<a onclick="shareFolder('+id+', \''+name+'\')">分享</a>';
  html += '<a onclick="renameFolder('+id+', \''+name+'\')">重命名</a>';
  html += '<a onclick="deleteFolder('+id+')">删除</a>';
  showContextMenu(e, html);
}

function isEditableType(type){
    var editable = <?php echo json_encode(array_map('strtolower', explode('|', $conf['type_editable'] ?: ''))); ?>;
    return editable.indexOf((type||'').toLowerCase()) > -1;
}

function fileContextMenu(e, fileId, hash, name, type){
  e.preventDefault();
  var html = '<a href="./file.php?hash='+hash+'" target="_blank">查看</a>';
  html += '<a onclick="copyDirectLink(\''+hash+'\', \''+type+'\')">复制直链</a>';
  if(isEditableType(type)){
    html += '<a onclick="openTextEditor(\''+hash+'\', \''+escapeHtml(name)+'\', \''+type+'\')">在线编辑</a>';
  }
  html += '<a onclick="openMoveTree(\'file\', '+fileId+', \''+name+'\')">移动到</a>';
  html += '<a onclick="deleteFile('+fileId+')">删除</a>';
  showContextMenu(e, html);
}

function showContextMenu(e, html){
    $('#contextMenu').html(html).css({left:e.pageX, top:e.pageY}).show();
}

function createNewFolder(){
    layer.prompt({title:'新建文件夹', formType:0}, function(val, index){
        if(!val.trim()){ layer.msg('名称不能为空'); return; }
        var ii = layer.load(1);
        $.post('ajax.php?act=createFolder', {name:val.trim(), parent_id:currentFolderId}, function(res){
            layer.close(ii);
            if(res.code==0){ layer.close(index); loadFolder(currentFolderId); }
            else{ layer.msg(res.msg, {icon:2}); }
        }, 'json');
    });
}

function renameFolder(id, oldName){
    layer.prompt({title:'重命名文件夹', value:oldName, formType:0}, function(val, index){
        if(!val.trim()){ layer.msg('名称不能为空'); return; }
        var ii = layer.load(1);
        $.post('ajax.php?act=renameFolder', {id:id, name:val.trim()}, function(res){
            layer.close(ii);
            if(res.code==0){ layer.close(index); loadFolder(currentFolderId); }
            else{ layer.msg(res.msg, {icon:2}); }
        }, 'json');
    });
}

function deleteFolder(id){
    layer.confirm('确定删除该文件夹吗？', function(index){
        var ii = layer.load(1);
        $.post('ajax.php?act=deleteFolder', {id:id}, function(res){
            layer.close(ii);
            if(res.code==0){ layer.close(index); loadFolder(currentFolderId); }
            else{ layer.msg(res.msg, {icon:2}); }
        }, 'json');
    });
}

function deleteFile(fileId){
    layer.confirm('确定删除该文件吗？', function(index){
        var ii = layer.load(1);
        var csrf = '<?php echo $csrf_token; ?>';
        $.post('ajax.php?act=deleteFile', {file_id:fileId, csrf_token:csrf}, function(res){
            layer.close(ii);
            if(res.code==0){ layer.close(index); loadFolder(currentFolderId); }
            else{ layer.msg(res.msg, {icon:2}); }
        }, 'json');
    });
}

// 移动功能全局变量
var moveTargetType = ''; // 'file' or 'folder'
var moveTargetId = null; // file hash or folder id
var moveTargetName = '';
var selectedTargetFolderId = null;
var moveTreeLayerIndex = null;

function getParentFolderId(){
  if(!currentData.path || currentData.path.length === 0) return null;
  if(currentData.path.length === 1) return 0;
  return currentData.path[currentData.path.length - 2].id;
}

function openMoveTree(type, id, name){
  moveTargetType = type;
  moveTargetId = id;
  moveTargetName = name;
  selectedTargetFolderId = null;

  var html = '<div style="padding:15px;">';
  html += '<div class="move-tree-current">正在移动：<span class="name">'+escapeHtml(name)+'</span></div>';

  // 快捷按钮
  html += '<div class="move-tree-quick-btns">';
  var parentId = getParentFolderId();
  if(parentId !== null){
    html += '<button class="btn btn-sm btn-default" onclick="moveToParentConfirm()">上层目录</button>';
  }
  html += '<button class="btn btn-sm btn-default" onclick="moveToRootConfirm()">根目录</button>';
  html += '</div>';

  // 树形目录选择器
  html += '<p style="margin-bottom:8px; color:#666;">选择目标文件夹：</p>';
  html += '<div class="move-tree" id="moveTreeRoot">';
  html += '<div class="move-tree-item" data-folder-id="0">';
  html += '<div class="move-tree-node">';
  html += '<span class="move-tree-toggle" onclick="toggleMoveTree(0, this)">▶</span>';
  html += '<i class="fa fa-folder move-tree-icon"></i>';
  html += '<span onclick="selectMoveTree(0, this)">根目录</span>';
  html += '</div>';
  html += '<div class="move-tree-children" id="moveTreeChildren_0"></div>';
  html += '</div>';
  html += '</div>';

  // 确认按钮
  html += '<div style="margin-top:15px; text-align:center;">';
  html += '<button class="btn btn-primary" onclick="confirmMove()">确认移动</button>';
  html += '</div>';
  html += '</div>';

  moveTreeLayerIndex = layer.open({
    type: 1,
    title: '移动到',
    area: ['400px', '500px'],
    content: html,
    success: function(){
      // 自动展开根目录子文件夹
      renderMoveTreeNode(0, $('#moveTreeChildren_0'));
    }
  });
}

function moveToParentConfirm(){
  var parentId = getParentFolderId();
  if(parentId === null){
    layer.msg('已在根目录', {icon:0});
    return;
  }
  doMove(moveTargetType, moveTargetId, parentId);
  layer.close(moveTreeLayerIndex);
}

function moveToRootConfirm(){
  doMove(moveTargetType, moveTargetId, 0);
  layer.close(moveTreeLayerIndex);
}

function renderMoveTreeNode(parentId, container){
  container.html('<div style="padding:10px;color:#999;"><i class="fa fa-spinner fa-spin"></i> 加载中...</div>');
  $.get('ajax.php?act=listFolder&parent_id='+parentId, function(res){
    if(res.code !== 0){
      container.html('<div style="padding:10px;color:#d9534f;">加载失败</div>');
      return;
    }
    var folders = res.data || [];
    var html = '';
    for(var i=0; i<folders.length; i++){
      var f = folders[i];
      // 如果是移动文件夹，不能移动到自身及其子文件夹中
      if(moveTargetType === 'folder' && f.id == moveTargetId) continue;
      html += '<div class="move-tree-item" data-folder-id="'+f.id+'">';
      html += '<div class="move-tree-node">';
      html += '<span class="move-tree-toggle" onclick="toggleMoveTree('+f.id+', this)">▶</span>';
      html += '<i class="fa fa-folder move-tree-icon"></i>';
      html += '<span onclick="selectMoveTree('+f.id+', this)">'+escapeHtml(f.name)+'</span>';
      html += '</div>';
      html += '<div class="move-tree-children" id="moveTreeChildren_'+f.id+'"></div>';
      html += '</div>';
    }
    if(html === ''){
      container.html('<div style="padding:5px 10px;color:#999;font-size:12px;">暂无子文件夹</div>');
    }else{
      container.html(html);
      container.addClass('expanded');
      // 如果有子文件夹，展开第一个的toggle图标
      container.siblings('.move-tree-node').find('.move-tree-toggle').first().text('▼');
    }
  }, 'json');
}

function toggleMoveTree(folderId, toggleEl){
  var $toggle = $(toggleEl);
  var $children = $('#moveTreeChildren_'+folderId);
  if($children.hasClass('expanded')){
    $children.removeClass('expanded');
    $toggle.text('▶');
  }else{
    if($children.children().length === 0){
      renderMoveTreeNode(folderId, $children);
    }else{
      $children.addClass('expanded');
    }
    $toggle.text('▼');
  }
}

function selectMoveTree(folderId, nameEl){
  selectedTargetFolderId = folderId;
  $('.move-tree-item').removeClass('selected');
  $(nameEl).closest('.move-tree-item').addClass('selected');
}

function confirmMove(){
  if(selectedTargetFolderId === null){
    layer.msg('请选择目标文件夹', {icon:0});
    return;
  }
  doMove(moveTargetType, moveTargetId, selectedTargetFolderId);
  layer.close(moveTreeLayerIndex);
}

function doMove(type, id, folder_id){
  if(type === 'file'){
    doMoveFile(id, folder_id);
  }else if(type === 'folder'){
    doMoveFolder(id, folder_id);
  }
}

function doMoveFolder(id, parent_id){
  var ii = layer.load(1);
  $.post('ajax.php?act=moveFolder', {id:id, parent_id:parent_id}, function(res){
    layer.close(ii);
    if(res.code === 0){
      layer.msg('移动成功', {icon:1});
      loadFolder(currentFolderId);
    }else{
      layer.msg(res.msg, {icon:2});
    }
  }, 'json');
}

// 保留旧函数名用于兼容性，实际调用openMoveTree
function moveFile(fileId){
  openMoveTree('file', fileId, '文件');
}

function doMoveFile(fileId, folder_id){
    var ii = layer.load(1);
    $.post('ajax.php?act=moveFile', {file_id:fileId, folder_id:folder_id, csrf_token:'<?php echo $csrf_token; ?>'}, function(res){
        layer.close(ii);
        if(res.code==0){ loadFolder(currentFolderId); }
        else{ layer.msg(res.msg, {icon:2}); }
    }, 'json');
}

function refresh(){ loadFolder(currentFolderId); }

function refreshCurrentUploadFolder(){
    uploadNeedRefresh = false;
    if(uploadRefreshTimer){
        clearTimeout(uploadRefreshTimer);
    }
    uploadRefreshTimer = setTimeout(function(){
        uploadRefreshTimer = null;
        loadFolder(currentFolderId, {updateUrl:false});
    }, 300);
}

function copyDirectLink(hash, type){
    var link = siteurl + 'down.php/' + hash + '.' + type;
    var $temp = $('<input>');
    $('body').append($temp);
    $temp.val(link).select();
    document.execCommand('copy');
    $temp.remove();
    layer.msg('直链已复制到剪贴板', {icon:1});
}

var editorLayerIndex = null;
var currentEditHash = '';

var cmModeMap = {
    'js':'javascript','ts':'javascript','tsx':'javascript','jsx':'javascript','json':'javascript',
    'css':'css','html':'htmlmixed','htm':'htmlmixed','vue':'htmlmixed',
    'xml':'xml','php':'php','py':'python','java':'text/x-java',
    'go':'go','c':'text/x-c','cpp':'text/x-c++src','h':'text/x-c++src',
    'sql':'sql','sh':'shell','bat':'shell',
    'md':'markdown','yaml':'yaml','yml':'yaml',
    'lua':'lua','rb':'ruby','pl':'perl'
};

function openTextEditor(hash, name, type){
    currentEditHash = hash;
    var ii = layer.load(1);
    $.get('ajax.php?act=getFileContent&hash='+hash, function(res){
        layer.close(ii);
        if(res.code != 0){
            layer.msg(res.msg, {icon:2});
            return;
        }
        var content = res.content || '';
        editorLayerIndex = layer.open({
            type: 2,
            title: '在线编辑 - '+escapeHtml(name),
            area: ['820px', '520px'],
            content: 'editor.html',
            shadeClose: false,
            anim: -1,
            success: function(layero, index){
                var iframeWin = layero.find('iframe')[0].contentWindow;
                setTimeout(function(){
                    iframeWin.postMessage({
                        action: 'init',
                        hash: hash,
                        content: content,
                        mode: cmModeMap[(type||'').toLowerCase()] || ''
                    }, '*');
                }, 100);
            },
            end: function(){
            }
        });
    }, 'json');
}

function saveTextContent(content){
    var csrf = '<?php echo $csrf_token; ?>';
    var ii = layer.load(1);
    $.post('ajax.php?act=saveFileContent', {hash:currentEditHash, content:content, csrf_token:csrf}, function(res){
        layer.close(ii);
        if(res.code == 0){
            layer.msg(res.msg, {icon:1});
            layer.close(editorLayerIndex);
            loadFolder(currentFolderId);
        }else{
            layer.msg(res.msg, {icon:2});
        }
    }, 'json');
}

window.addEventListener('message', function(e){
    if(e.data.action === 'save'){
        saveTextContent(e.data.content);
    }else if(e.data.action === 'cancel'){
        layer.close(editorLayerIndex);
    }
});

function shareFolder(id, name){
    var html = '<div style="padding:15px;">';
    html += '<p>分享文件夹：<b>'+escapeHtml(name)+'</b></p>';
    html += '<p>访问密码（留空表示无需密码）：</p>';
    html += '<input type="text" id="sharePwd" class="form-control" placeholder="请输入密码" maxlength="16">';
    html += '<p style="margin-top:10px; color:#999; font-size:12px;">密码只能为字母和数字</p>';
    html += '</div>';
    layer.open({
        type: 1,
        title: '分享文件夹',
        area: ['400px', '260px'],
        content: html,
        btn: ['确认分享', '取消'],
        yes: function(index){
            var pwd = $('#sharePwd').val().trim();
            if(pwd && !/^[a-zA-Z0-9]+$/.test(pwd)){
                layer.msg('密码只能为字母和数字', {icon:2});
                return;
            }
            var ii = layer.load(1);
            $.post('ajax.php?act=createShare', {folder_id:id, pwd:pwd}, function(res){
                layer.close(ii);
                if(res.code == 0){
                    layer.close(index);
                    var showHtml = '<div style="padding:15px; text-align:center;">';
                    showHtml += '<p>分享链接：</p>';
                    showHtml += '<div class="input-group" style="margin-bottom:15px;">';
                    showHtml += '<input type="text" class="form-control" id="shareUrlInput" readonly value="'+res.url+'">';
                    showHtml += '<span class="input-group-btn"><button class="btn btn-primary" onclick="copyShareUrl()">复制</button></span>';
                    showHtml += '</div>';
                    showHtml += '</div>';
                    layer.open({
                        type: 1,
                        title: '分享成功',
                        area: ['450px', '200px'],
                        content: showHtml
                    });
                }else{
                    layer.msg(res.msg, {icon:2});
                }
            }, 'json');
        }
    });
}

function copyShareUrl(){
    var url = $('#shareUrlInput').val();
    var $temp = $('<input>');
    $('body').append($temp);
    $temp.val(url).select();
    document.execCommand('copy');
    $temp.remove();
    layer.msg('分享链接已复制', {icon:1});
}

function createUploadInvite(){
    var html = '<div class="upload-invite-form">';
    html += '<p class="upload-invite-location">接收位置：<b>'+(currentFolderId > 0 ? '当前目录' : '根目录')+'</b></p>';
    html += '<div class="upload-invite-field"><label for="inviteMaxSizeMb">单文件大小限制（MB）：</label>';
    html += '<input type="number" id="inviteMaxSizeMb" class="form-control" min="1" value="1024"></div>';
    html += '<div class="upload-invite-field"><label for="inviteExpireHours">有效时长（小时，0 表示长期有效）：</label>';
    html += '<input type="number" id="inviteExpireHours" class="form-control" min="0" value="0"></div>';
    html += '<div class="upload-invite-field"><label for="inviteRemark">上传文件备注（可选，上传后显示在文件名下方）：</label>';
    html += '<textarea id="inviteRemark" class="form-control" maxlength="200" rows="3" placeholder="例如：请上传本次报销凭证"></textarea></div>';
    html += '<p class="upload-invite-tips">默认限制为 1024MB（1GB）。文件未上传成功前可反复重试。</p>';
    html += '</div>';
    layer.open({
        type: 1,
        title: '邀请上传设置',
        area: ['460px', '440px'],
        skin: 'upload-invite-layer',
        content: html,
        btn: ['生成邀请', '取消'],
        yes: function(index){
            var maxSizeMb = parseInt($('#inviteMaxSizeMb').val(), 10) || 1024;
            var expireHours = parseInt($('#inviteExpireHours').val(), 10) || 0;
            var remark = $('#inviteRemark').val().trim();
            if(maxSizeMb <= 0){
                layer.msg('大小限制必须大于0', {icon:2});
                return;
            }
            if(expireHours < 0){
                layer.msg('有效时长不能小于0', {icon:2});
                return;
            }
            var ii = layer.load(1);
            $.post('ajax.php?act=createUploadInvite', {folder_id: currentFolderId, max_size_mb: maxSizeMb, expire_hours: expireHours, remark: remark, csrf_token: '<?php echo $csrf_token; ?>'}, function(res){
                layer.close(ii);
                if(res.code == 0){
                    layer.close(index);
                    var showHtml = '<div class="upload-invite-result">';
                    showHtml += '<p>邀请上传链接：</p>';
                    showHtml += '<div class="input-group upload-invite-url-group">';
                    showHtml += '<input type="text" class="form-control" id="uploadInviteUrlInput" readonly value="'+escapeHtml(res.url)+'">';
                    showHtml += '<span class="input-group-btn"><button class="btn btn-primary" onclick="copyUploadInviteUrl()">复制</button></span>';
                    showHtml += '</div>';
                    showHtml += '<p class="upload-invite-tips">密码已包含在链接中，对方打开即可上传。</p>';
                    showHtml += '<p class="upload-invite-tips">单文件限制：'+maxSizeMb+'MB；有效期：'+(res.expire_time || '长期有效')+'</p>';
                    if(res.remark){ showHtml += '<p class="upload-invite-remark">备注：'+escapeHtml(res.remark)+'</p>'; }
                    showHtml += '</div>';
                    layer.open({
                        type: 1,
                        title: '邀请上传已生成',
                        area: ['520px', '290px'],
                        skin: 'upload-invite-layer upload-invite-result-layer',
                        content: showHtml
                    });
                }else{
                    layer.msg(res.msg, {icon:2});
                }
            }, 'json');
        }
    });
}

function copyUploadInviteUrl(){
    var url = $('#uploadInviteUrlInput').val();
    var $temp = $('<input>');
    $('body').append($temp);
    $temp.val(url).select();
    document.execCommand('copy');
    $temp.remove();
    layer.msg('邀请上传链接已复制', {icon:1});
}

function escapeHtml(text){
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}

function sizeFormat(size){
    if(size<1024) return size+' B';
    size/=1024;
    if(size<1024) return size.toFixed(2)+' KB';
    size/=1024;
    if(size<1024) return size.toFixed(2)+' MB';
    size/=1024;
    return size.toFixed(2)+' GB';
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

var uploadModalIndex = null;
var uploadNeedRefresh = false;

function initListDropzone(){
    var zone = document.querySelector('.well.bs-component');
    if(!zone) return;
    zone.addEventListener('dragover', function(e){
        e.preventDefault();
        e.stopPropagation();
        zone.style.border = '2px dashed #337ab7';
        zone.style.background = '#f0f8ff';
    });
    zone.addEventListener('dragleave', function(e){
        e.preventDefault();
        e.stopPropagation();
        if(!zone.contains(e.relatedTarget)){
            zone.style.border = '';
            zone.style.background = '';
        }
    });
    zone.addEventListener('drop', function(e){
        e.preventDefault();
        e.stopPropagation();
        zone.style.border = '';
        zone.style.background = '';
        try {
            var files = [];
            if(e.dataTransfer.items){
                var items = e.dataTransfer.items;
                var promises = [];
                for(var i=0; i<items.length; i++){
                    var item = null;
                    try {
                        item = items[i].webkitGetAsEntry();
                    } catch(err) {
                        console.error('webkitGetAsEntry error:', err);
                    }
                    if(item) {
                        promises.push(new Promise(function(resolve){
                            var timeout = setTimeout(function(){
                                console.warn('traverseFileTree timeout');
                                resolve();
                            }, 10000);
                            traverseFileTree(item, '', files, function(){
                                clearTimeout(timeout);
                                resolve();
                            });
                        }));
                    }
                }
                Promise.all(promises).then(function(){
                    handleUploadFiles(files);
                }).catch(function(err){
                    console.error('Promise.all error:', err);
                    handleUploadFiles(files);
                });
            }else{
                files = Array.from(e.dataTransfer.files);
                handleUploadFiles(files);
            }
        } catch(err) {
            console.error('Drop handler error:', err);
            var fallbackFiles = Array.from(e.dataTransfer.files);
            if(fallbackFiles.length > 0){
                handleUploadFiles(fallbackFiles);
            }
        }
    });
}

/* ========== 上传弹框组件 ========== */
function getUploadModalArea(){
    if(window.matchMedia && window.matchMedia('(max-width: 767px)').matches){
        return ['calc(100vw - 20px)', 'auto'];
    }
    return ['600px', '500px'];
}

function openUploadModal(prefillFiles){
    var html = '<div class="upload-modal-body">';
    html += '<div class="upload-dropzone" id="uploadDropzone">';
    html += '<p><i class="fa fa-cloud-upload" style="font-size:36px;"></i></p>';
    html += '<p>拖拽文件或文件夹到此处上传</p>';
    html += '<p><button class="btn btn-primary btn-sm" onclick="document.getElementById(\'fileInput\').click()">选择文件</button> ';
    html += '<button class="btn btn-info btn-sm" onclick="document.getElementById(\'folderInput\').click()">选择文件夹</button></p>';
    html += '</div>';
    html += '<input type="file" id="fileInput" multiple style="display:none" onchange="handleFiles(this.files)">';
    html += '<input type="file" id="folderInput" webkitdirectory style="display:none" onchange="handleFiles(this.files)">';
    html += '<div style="margin-top:10px; text-align:right;">';
    html += '<button class="btn btn-default btn-xs" id="toggleQueueBtn" onclick="toggleQueue()"><i class="fa fa-eye-slash"></i> 隐藏列表</button>';
    html += '</div>';
    html += '<div class="upload-queue" id="uploadQueue"></div>';
    html += '</div>';
    uploadModalIndex = layer.open({
        type:1,
        title:'上传文件',
        area:getUploadModalArea(),
        skin:'mine-upload-layer',
        content:html,
        success:function(){
            initDropzone();
            // 恢复之前正在上传的队列
            if(uploadQueue.length > 0){
                renderUploadQueue();
            }
            if(prefillFiles && prefillFiles.length > 0){
                addFilesToQueue(prefillFiles);
            }
        },
        end:function(){
            uploadModalIndex = null;
            // 用户关闭弹框不影响上传；每个文件完成后会自动刷新当前正在查看的列表
        }
    });
}

function toggleQueue(){
    var $queue = $('#uploadQueue');
    var $btn = $('#toggleQueueBtn');
    if($queue.is(':visible')){
        $queue.hide();
        $btn.html('<i class="fa fa-eye"></i> 显示列表');
    }else{
        $queue.show();
        $btn.html('<i class="fa fa-eye-slash"></i> 隐藏列表');
    }
}

function initDropzone(){
    var zone = document.getElementById('uploadDropzone');
    if(!zone) return;
    zone.addEventListener('dragover', function(e){
        e.preventDefault();
        e.stopPropagation();
        zone.classList.add('dragover');
    });
    zone.addEventListener('dragleave', function(e){
        e.preventDefault();
        e.stopPropagation();
        if(!zone.contains(e.relatedTarget)){
            zone.classList.remove('dragover');
        }
    });
    zone.addEventListener('drop', function(e){
        e.preventDefault();
        e.stopPropagation();
        zone.classList.remove('dragover');
        var files = [];
        if(e.dataTransfer.items){
            var items = e.dataTransfer.items;
            var promises = [];
            for(var i=0; i<items.length; i++){
                var item = items[i].webkitGetAsEntry();
                if(item) {
                    promises.push(new Promise(function(resolve){
                        traverseFileTree(item, '', files, resolve);
                    }));
                }
            }
            Promise.all(promises).then(function(){
                handleUploadFiles(files);
            });
        }else{
            files = Array.from(e.dataTransfer.files);
            handleUploadFiles(files);
        }
    });
}

function traverseFileTree(item, path, files, callback){
    // 防止无限递归的安全计数器
    var maxIterations = 1000;
    var iterationCount = 0;

    // 确保 callback 一定被调用（防止卡死）
    var callbackCalled = false;
    var safetyTimeout = setTimeout(function(){
        if(!callbackCalled){
            callbackCalled = true;
            console.warn('traverseFileTree safety timeout triggered');
            if(callback) callback();
        }
    }, 5000); // 5秒安全超时

    function safeCallback(){
        if(!callbackCalled){
            callbackCalled = true;
            clearTimeout(safetyTimeout);
            if(callback) callback();
        }
    }

    try {
        if(!item){
            safeCallback();
            return;
        }

        if(item.isFile){
            item.file(function(file){
                try {
                    file.relativePath = path + file.name;
                    files.push(file);
                } catch(e) {
                    console.error('Error processing file:', e);
                }
                safeCallback();
            }, function(error){
                console.error('Error reading file:', error);
                safeCallback();
            });
        }else if(item.isDirectory){
            var dirReader = item.createReader();
            function readEntries(){
                iterationCount++;
                if(iterationCount > maxIterations){
                    console.error('Max iterations reached, aborting directory read');
                    safeCallback();
                    return;
                }
                dirReader.readEntries(function(entries){
                    if(entries.length > 0){
                        var promises = [];
                        for(var i=0; i<entries.length; i++){
                            promises.push(new Promise(function(resolve){
                                try {
                                    traverseFileTree(entries[i], path + item.name + '/', files, resolve);
                                } catch(e) {
                                    console.error('Error traversing entry:', e);
                                    resolve();
                                }
                            }));
                        }
                        Promise.all(promises).then(function(){
                            readEntries();
                        }).catch(function(e){
                            console.error('Promise.all error:', e);
                            safeCallback();
                        });
                    }else{
                        safeCallback();
                    }
                }, function(error){
                    console.error('Error reading directory entries:', error);
                    safeCallback();
                });
            }
            readEntries();
        }else{
            safeCallback();
        }
    } catch(e) {
        console.error('traverseFileTree error:', e);
        safeCallback();
    }
}

function handleFiles(fileList){
    var files = Array.from(fileList);
    handleUploadFiles(files);
}

var uploadQueue = [];
var uploadRunning = false;

function addFilesToQueue(files){
    for(var i=0; i<files.length; i++){
        var task = {
            id: Date.now() + '_' + i,
            file: files[i],
            name: files[i].name,
            size: files[i].size,
            relativePath: files[i].relativePath || files[i].webkitRelativePath || files[i].name,
            status: 'waiting',
            progress: 0,
            hash: null,
            chunks: 0,
            chunkSize: 0,
            currentChunk: 0
        };
        uploadQueue.push(task);
        addQueueItem(task);
    }
    if(!uploadRunning){
        uploadRunning = true;
        processUploadQueue();
    }
}

function handleUploadFiles(files){
    if(files.length == 0) return;
    if(uploadModalIndex === null){
        openUploadModal(files);
        return;
    }
    addFilesToQueue(files);
}

function addQueueItem(task){
    var html = '<div class="upload-queue-item" id="uq_'+task.id+'">';
    html += '<div class="upload-queue-name" title="'+escapeHtml(task.name)+'">'+escapeHtml(task.name)+'</div>';
    html += '<div class="progress"><div class="progress-bar" style="width:0%"></div></div>';
    html += '<div class="status" id="us_'+task.id+'">等待中</div>';
    html += '</div>';
    $('#uploadQueue').append(html);
}

function renderUploadQueue(){
    $('#uploadQueue').empty();
    for(var i=0; i<uploadQueue.length; i++){
        var task = uploadQueue[i];
        addQueueItem(task);
        var percent = 0;
        var statusText = '等待中';
        var isError = false;
        if(task.status == 'hashing'){
            statusText = '计算Hash...';
        }else if(task.status == 'preupload'){
            statusText = '准备上传...';
            percent = 5;
        }else if(task.status == 'uploading'){
            percent = Math.floor((task.currentChunk / task.chunks) * 100);
            statusText = '上传中 ' + percent + '%';
        }else if(task.status == 'completed'){
            percent = 100;
            statusText = '完成';
        }else if(task.status == 'error'){
            statusText = '上传失败';
            isError = true;
        }
        updateQueueItem(task, statusText, percent, isError);
    }
}

function updateQueueItem(task, status, percent, isError){
    $('#uq_'+task.id+' .progress-bar').css('width', percent+'%');
    var $status = $('#us_'+task.id);
    $status.text(status);
    if(isError){
        $status.css('color', '#d9534f');
        $('#uq_'+task.id).css('background', '#fff5f5');
    }else if(percent >= 100){
        $status.css('color', '#5cb85c');
    }else{
        $status.css('color', '');
    }
}

function processUploadQueue(){
    // 找第一个 waiting 状态的任务
    var task = null;
    for(var i=0; i<uploadQueue.length; i++){
        if(uploadQueue[i].status == 'waiting'){
            task = uploadQueue[i];
            break;
        }
    }
    // 如果没有 waiting 的任务，检查是否还有正在进行的
    if(!task){
        var hasRunning = false;
        for(var i=0; i<uploadQueue.length; i++){
            var s = uploadQueue[i].status;
            if(s == 'hashing' || s == 'preupload' || s == 'uploading'){
                hasRunning = true;
                break;
            }
        }
        if(!hasRunning){
            uploadRunning = false;
            uploadNeedRefresh = false;
        }
        return;
    }
    task.status = 'hashing';
    updateQueueItem(task, '计算Hash...', 0);
    computeFileHash(task.file, function(hash){
        try {
            if(!hash){
                task.status = 'error';
                updateQueueItem(task, '文件读取失败，无法计算Hash', 0, true);
                processUploadQueue();
                return;
            }
            task.hash = hash;
            task.status = 'preupload';
            updateQueueItem(task, '准备上传...', 5);
            doPreUpload(task, function(preRes){
                try {
                    if(preRes.code == 1){
                        task.status = 'completed';
                        updateQueueItem(task, '已存在', 100);
                        refreshCurrentUploadFolder();
                        processUploadQueue();
                        return;
                    }
                    if(preRes.code != 0){
                        task.status = 'error';
                        updateQueueItem(task, preRes.msg || '预上传失败', 0, true);
                        processUploadQueue();
                        return;
                    }
                    task.chunks = preRes.chunks || 1;
                    task.chunkSize = preRes.chunksize || task.size;
                    task.status = 'uploading';
                    var resumeData = getResumeData(task.hash);
                    if(resumeData && resumeData.chunks == task.chunks){
                        task.currentChunk = resumeData.currentChunk;
                    }else{
                        task.currentChunk = 0;
                        saveResumeData(task.hash, {chunks:task.chunks, currentChunk:0, name:task.name});
                    }
                    uploadChunks(task, preRes.chunksize);
                } catch(e) {
                    console.error('doPreUpload callback error:', e);
                    task.status = 'error';
                    updateQueueItem(task, '处理异常', 0, true);
                    processUploadQueue();
                }
            });
        } catch(e) {
            console.error('computeFileHash callback error:', e);
            task.status = 'error';
            updateQueueItem(task, '处理异常', 0, true);
            processUploadQueue();
        }
    });
}

function uploadChunks(task, chunksize){
    if(task.currentChunk >= task.chunks){
        task.status = 'completed';
        updateQueueItem(task, '完成', 100);
        clearResumeData(task.hash);
        refreshCurrentUploadFolder();
        processUploadQueue();
        return;
    }
    var start = task.currentChunk * chunksize;
    var end = Math.min(start + chunksize, task.size);
    var blob = task.file.slice(start, end);
    var percent = Math.floor((task.currentChunk / task.chunks) * 100);
    updateQueueItem(task, '上传中 '+percent+'%', percent);
    var formData = new FormData();
    formData.append('file', blob);
    formData.append('chunk', task.currentChunk + 1);
    formData.append('hash', task.hash);
    formData.append('csrf_token', '<?php echo $csrf_token; ?>');
    $.ajax({
        url: 'ajax.php?act=upload_part',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(res){
            try {
                if(res.code == 0){
                    task.currentChunk++;
                    saveResumeData(task.hash, {chunks:task.chunks, currentChunk:task.currentChunk, name:task.name});
                    uploadChunks(task, chunksize);
                }else if(res.code == 1){
                    task.status = 'completed';
                    updateQueueItem(task, '已存在', 100);
                    clearResumeData(task.hash);
                    refreshCurrentUploadFolder();
                    processUploadQueue();
                }else{
                    task.status = 'error';
                    updateQueueItem(task, res.msg || '上传失败', 0, true);
                    processUploadQueue();
                }
            } catch(e) {
                console.error('uploadChunks success callback error:', e);
                task.status = 'error';
                updateQueueItem(task, '处理异常', 0, true);
                processUploadQueue();
            }
        },
        error: function(){
            task.status = 'error';
            updateQueueItem(task, '网络错误，请检查网络后重试', 0, true);
            processUploadQueue();
        }
    });
}

function computeFileHash(file, callback){
    var chunkSize = 2 * 1024 * 1024;
    var chunks = Math.ceil(file.size / chunkSize);
    var spark = new SparkMD5.ArrayBuffer();
    var fileReader = new FileReader();
    var currentChunk = 0;
    fileReader.onload = function(e){
        spark.append(e.target.result);
        currentChunk++;
        if(currentChunk < chunks){
            loadNext();
        }else{
            callback(spark.end());
        }
    };
    fileReader.onerror = function(){
        callback('');
    };
    function loadNext(){
        var start = currentChunk * chunkSize;
        var end = Math.min(start + chunkSize, file.size);
        fileReader.readAsArrayBuffer(file.slice(start, end));
    }
    loadNext();
}

function doPreUpload(task, callback){
    $.post('ajax.php?act=pre_upload', {
        name: task.name,
        hash: task.hash,
        size: task.size,
        ispwd: 0,
        pwd: '',
        csrf_token: '<?php echo $csrf_token; ?>',
        folder_id: currentFolderId,
        relative_path: task.relativePath || ''
    }, callback, 'json');
}

function getResumeData(hash){
    try{
        var data = localStorage.getItem('upload_resume_'+hash);
        return data ? JSON.parse(data) : null;
    }catch(e){ return null; }
}

function saveResumeData(hash, data){
    try{
        localStorage.setItem('upload_resume_'+hash, JSON.stringify(data));
    }catch(e){}
}

function clearResumeData(hash){
    try{
        localStorage.removeItem('upload_resume_'+hash);
    }catch(e){}
}
</script>
</body>
</html>
