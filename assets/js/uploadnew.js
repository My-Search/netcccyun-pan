new Vue({
    el: '#app',
    data: {
        uploadTitle: '选择文件/Ctrl+V粘贴/拖拽到此上传',
        background: '#fff',
        showtype: 0,
        isBlock: false,
        alert: {
            type: 'success',
            msg: ''
        },
        beginTime: 0,
        loaded_size: 0,
        progress: 0,
        progress_tip: '',
        filename: '',
        uploadspeed: '',
        speedHistory: [],
        uploadStatus: 'idle',
        currentFile: null,
        currentXhr: null,
        isThirdUpload: false,
        uploadMeta: {
            chunkSize: 0,
            chunks: 0,
            currentChunk: 1,
            lastResult: null,
            thirdUrl: '',
            thirdPost: null
        },
        input: {
            csrf_token:'',
            ispwd: false,
            pwd: '',
            hash: '',
            name: '',
            size: 0
        },
    },
    mounted() {
        $(".colorful_loading_frame").hide();
        this.input.csrf_token = $("#csrf_token").val();

        var that = this;
        var fileInput = $("#fileInput");
        var elemetnNode="";
        //拖拽外部文件，进入目标元素触发
        fileInput.on("dragenter",function(e){
            elemetnNode=e.originalEvent.target;
            that.uploadTitle = '释放鼠标立即上传';
            that.background = '#ccc';
        });
        //拖拽外部文件，离开目标元素触发
        fileInput.on("dragleave",function(e){
            if(elemetnNode===e.originalEvent.target){
                that.uploadTitle = '选择文件/Ctrl+V粘贴/拖拽到此上传';
                that.background = '#fff';
            }
        });
        //拖拽外部文件，在目标元素上释放鼠标触发
        fileInput.on('dragover', false).on("drop",function(e){
            that.uploadTitle = '选择文件/Ctrl+V粘贴/拖拽到此上传';
            that.background = '#fff';
            var fs = e.originalEvent.dataTransfer.files;
            if(fs.length>0){
                that.uploadFile(fs[0])
            }
            return false;
        });

        document.addEventListener('paste', function(e) {
            var items = ((e.clipboardData || window.clipboardData).items) || [];
            var file = null;

            if (items && items.length) {
                for (var i = 0; i < items.length; i++) {
                    if (items[i].type.indexOf('text/') === -1) {
                        file = items[i].getAsFile();
                        break;
                    }
                }
            }

            if (!file) {
                return;
            }
            that.uploadFile(file)
        });
    },
    methods: {
        show_msg(msg, type){
            type = type || 'success';
            this.alert.type = type;
            this.alert.msg = msg;
            this.showtype = 2;
            this.isBlock=false;
            this.uploadStatus = 'idle';
            this.currentXhr = null;
            $("#file").val('');
        },
        clickUpload(){
            if(this.isBlock) return;
            $("#file").trigger("click");
        },
        async selectFile(e){
            var total = e.target.files.length;
            if(total == 0) return;
            var fileObj = e.target.files[0];
            await this.uploadFile(fileObj)
        },
        async uploadFile(file){
            if(this.isBlock) return;
            if(!this.beforeUpload(file)) return;
            this.initUploadState(file);

            let loading = layer.msg('正在准备上传...', {icon: 16,shade: 0.1,time: 0});
            try{
                this.input.hash = await this.getFileHash(file);

                // 获取用户容量信息并校验
                var storageCheck = await this.checkStorage(file.size);
                if(!storageCheck.ok){
                    layer.close(loading);
                    layer.alert(storageCheck.msg, {icon:2});
                    this.resetUploadBlock();
                    return;
                }

                var result = await this.preUpload();
                layer.close(loading);
                this.filename = file.name + ' （'+this.size_format(file.size)+'）';
                this.isBlock = true;

                if(result.code == 1){
                    this.progress = 100;
                    this.uploadSuccess(result.hash);
                    return;
                }

                await this.startUpload(result);
            }catch(e){
                layer.close(loading);
                this.handleUploadError(e);
            }
        },
        beforeUpload(file){
            if(typeof forbid!=='undefined' && forbid){
                layer.alert('登录后才能上传文件！',{icon:0},function(){window.location.href='./login.php'});
                return false;
            }
            if(upload_max_filesize != '' && parseInt(upload_max_filesize) > 0){
                if(file.size > parseInt(upload_max_filesize) * 1024 * 1024){
                    layer.alert('上传文件大小限制'+upload_max_filesize+'MB！');return false;
                }
            }
            return true;
        },
        initUploadState(file){
            this.input.name = file.name;
            this.input.size = file.size;
            this.progress = 0;
            this.loaded_size = 0;
            this.progress_tip = '';
            this.uploadspeed = '';
            this.speedHistory = [];
            this.showtype = 1;
            this.beginTime = new Date().getTime();
            this.currentFile = file;
            this.currentXhr = null;
            this.isThirdUpload = false;
            this.uploadStatus = 'preparing';
            this.uploadMeta = {
                chunkSize: 0,
                chunks: 0,
                currentChunk: 1,
                lastResult: null,
                thirdUrl: '',
                thirdPost: null
            };
        },
        resetUploadBlock(){
            this.isBlock = false;
            this.uploadStatus = 'idle';
            this.currentXhr = null;
            $("#file").val('');
        },
        async startUpload(result){
            this.uploadStatus = 'uploading';
            if(result.third){
                this.isThirdUpload = true;
                this.uploadMeta.thirdUrl = result.url;
                this.uploadMeta.thirdPost = result.post;
                await this.startThirdUpload(result.url, result.post);
                return;
            }
            this.isThirdUpload = false;
            this.uploadMeta.chunkSize = result.chunksize;
            this.uploadMeta.chunks = result.chunks;
            this.uploadMeta.currentChunk = 1;
            await this.startPartUpload();
        },
        async startThirdUpload(url, postdata){
            await this.uploadThird(url, postdata, this.currentFile);
            if(this.isPaused()) return;
            var result = await this.completeUpload();
            this.uploadSuccess(result.hash);
        },
        async startPartUpload(){
            var result = this.uploadMeta.lastResult || {};
            var chunks = this.uploadMeta.chunks;
            if(chunks == 1){
                result = await this.uploadPart(this.currentFile, 1);
                this.uploadMeta.lastResult = result;
                this.uploadMeta.currentChunk = 2;
                if(this.isPaused()) return;
            }else{
                result = await this.uploadChunksFromCurrent();
                if(this.isPaused()) return;
            }
            this.uploadSuccess(result.hash);
        },
        async uploadChunksFromCurrent(){
            var blobSlice = File.prototype.mozSlice || File.prototype.webkitSlice || File.prototype.slice;
            var chunkSize = this.uploadMeta.chunkSize;
            var chunks = this.uploadMeta.chunks;
            var result = this.uploadMeta.lastResult || {};
            for (var chunk = this.uploadMeta.currentChunk; chunk <= chunks; chunk++) {
                if(this.isPaused()) break;
                var start = (chunk - 1) * chunkSize;
                var end = start + chunkSize > this.currentFile.size ? this.currentFile.size : start + chunkSize;
                var blob = blobSlice.call(this.currentFile, start, end);
                result = await this.uploadPart(blob, chunk);
                this.loaded_size = end;
                this.uploadMeta.lastResult = result;
                this.uploadMeta.currentChunk = chunk + 1;
                if(this.isPaused()) break;
            }
            return result;
        },
        isPaused(){
            return this.uploadStatus == 'paused';
        },
        canPause(){
            return this.showtype == 1 && this.uploadStatus == 'uploading' && !this.isThirdUpload;
        },
        canResume(){
            return this.showtype == 1 && this.uploadStatus == 'paused' && !this.isThirdUpload;
        },
        pauseUpload(){
            if(this.isThirdUpload){
                this.resetUploadBlock();
                layer.msg('当前存储模式不支持暂停，请重新选择文件上传');
                return;
            }
            if(!this.canPause()) return;
            this.uploadStatus = 'paused';
            this.progress_tip = '已暂停，进度已保留';
            this.uploadspeed = '';
            if(this.currentXhr){
                this.currentXhr.abort();
            }
        },
        async resumeUpload(){
            if(!this.canResume()) return;
            if(this.isThirdUpload){
                this.show_msg('当前存储模式不支持真正断点续传，请重新选择文件上传', 'danger');
                return;
            }
            this.uploadStatus = 'uploading';
            this.progress_tip = '';
            this.beginTime = new Date().getTime();
            this.speedHistory = [];
            try{
                if(this.uploadMeta.currentChunk > this.uploadMeta.chunks && this.uploadMeta.lastResult){
                    this.uploadSuccess(this.uploadMeta.lastResult.hash);
                    return;
                }
                await this.startPartUpload();
            }catch(e){
                this.handleUploadError(e);
            }
        },
        handleUploadError(error){
            if(error === '__UPLOAD_PAUSED__' || this.isPaused()) return;
            this.show_msg(error && error.message ? error.message : error, 'danger');
        },
        async checkStorage(fileSize){ //检查用户容量是否足够
            var that = this;
            // 未登录用户不检查
            if(typeof islogin2 === 'undefined' || !islogin2){
                return {ok: true};
            }
            return new Promise((resolve) => {
                $.ajax({
                    type: 'GET',
                    url: 'ajax.php?act=getUserStorage',
                    dataType: 'json',
                    success: function(data) {
                        if(data.code == 0 && data.data.limit > 0){
                            var limit = data.data.limit;
                            var used = data.data.used;
                            if(used + fileSize > limit){
                                var msg = '您的存储空间不足（已用 ' + data.data.usedFormatted + ' / 总共 ' + data.data.limitFormatted + '），无法上传该文件';
                                resolve({ok: false, msg: msg});
                            }else{
                                resolve({ok: true});
                            }
                        }else{
                            resolve({ok: true});
                        }
                    },
                    error: function(){
                        resolve({ok: true}); // 获取失败时不阻止上传
                    }
                });
            });
        },
        async preUpload(){ //文件预上传，极速秒传查询
            var postData = {
                csrf_token: this.input.csrf_token,
                name: this.input.name,
                hash: this.input.hash,
                size: this.input.size,
                ispwd: this.input.ispwd?'1':'0',
                pwd: this.input.pwd,
            };
            var that = this;
            return new Promise((resolve, reject) => {
                $.ajax({
                    type: 'POST',
                    url: 'ajax.php?act=pre_upload',
                    data: postData,
                    dataType: 'json',
                    success: function(data) {
                        if(data.code == 0 || data.code == 1){
                            resolve(data);
                        }else{
                            reject(data.msg);
                        }
                    },
                    error:function(xhr){
                        var msg = '预上传请求失败';
                        if(xhr.status === 0) msg = '网络连接失败，请检查网络';
                        else if(xhr.status >= 500) msg = '服务器错误('+xhr.status+')';
                        layer.msg(msg);
                        reject(msg);
                    }
                });
            })
        },
        async completeUpload(){ //第三方上传文件完成上传
            var that = this;
            return new Promise((resolve, reject) => {
                $.ajax({
                    type: 'POST',
                    url: 'ajax.php?act=complete_upload',
                    data: {hash: that.input.hash, csrf_token: that.input.csrf_token},
                    dataType: 'json',
                    success: function(data) {
                        if(data.code == 0 || data.code == 1){
                            resolve(data);
                        }else{
                            reject(data.msg);
                        }
                    },
                    error:function(xhr){
                        var msg = '完成上传请求失败';
                        if(xhr.status === 0) msg = '网络连接失败，请检查网络';
                        else if(xhr.status >= 500) msg = '服务器错误('+xhr.status+')';
                        layer.msg(msg);
                        reject(msg);
                    }
                });
            })
        },
        async uploadPart(file, chunk){ //上传文件分片
            var that = this;
            return new Promise((resolve, reject) => {
                var data = new FormData();
                data.append('file', file);
                data.append('hash', that.input.hash);
                data.append('chunk', chunk);
                data.append('csrf_token', that.input.csrf_token);
                $.ajax({
                    type : "POST",
                    url : "ajax.php?act=upload_part",
                    data : data,
                    processData: false,
                    contentType: false,
                    dataType : 'json',
                    success : function(data) {
                        that.currentXhr = null;
                        if(data.code == 0 || data.code == 1){
                            resolve(data);
                        }else{
                            reject(data.msg);
                        }
                    },
                    error : function(xhr){
                        that.currentXhr = null;
                        if(that.isPaused()){
                            reject('__UPLOAD_PAUSED__');
                            return;
                        }
                        var msg = '上传失败，请稍后再试或联系站长';
                        if(xhr.status === 0) msg = '网络连接失败，请检查网络';
                        else if(xhr.status === 413) msg = '文件大小超出服务器限制';
                        else if(xhr.status >= 500) msg = '服务器错误('+xhr.status+')';
                        reject(msg);
                    },
                    xhr: function() {
                        var xhr = new XMLHttpRequest();
                        that.currentXhr = xhr;
                        xhr.upload.addEventListener('progress', function (e) {
                            var totalLoaded = e.loaded + that.loaded_size;
                            var progressRate = Math.round(totalLoaded / that.input.size * 100);
                            if(progressRate>100)progressRate=100;
                            that.progress = progressRate;
                            if(progressRate == 100) that.progress_tip = '正在保存中，请稍候'

                            //上传速度计算（滑动窗口平均）
                            var nowTime = new Date().getTime();
                            that.updateSpeed(nowTime, totalLoaded);
                        })
                        return xhr;
                    }
                });
            })
        },
        async uploadThird(url, postdata, file){ //第三方上传文件
            var that = this;
            return new Promise((resolve, reject) => {
                var data = new FormData();
                for(var key in postdata){
                    data.append(key, postdata[key]);
                }
                data.append('file', file);
                $.ajax({
                    type : "POST",
                    url : url,
                    data : data,
                    processData: false,
                    contentType: false,
                    dataType : 'html',
                    success : function(data) {
                        that.currentXhr = null;
                        resolve();
                    },
                    error : function(xhr){
                        that.currentXhr = null;
                        if(that.isPaused()){
                            reject('__UPLOAD_PAUSED__');
                            return;
                        }
                        var msg = '第三方上传失败';
                        if(xhr.status === 0) msg = '网络连接失败，请检查网络';
                        else if(xhr.status >= 500) msg = '服务器错误('+xhr.status+')';
                        reject(msg);
                    },
                    xhr: function() {
                        var xhr = new XMLHttpRequest();
                        that.currentXhr = xhr;
                        xhr.upload.addEventListener('progress', function (e) {
                            var totalLoaded = e.loaded + that.loaded_size;
                            var progressRate = Math.round(totalLoaded / that.input.size * 100);
                            if(progressRate>100)progressRate=100;
                            that.progress = progressRate;
                            if(progressRate == 100) that.progress_tip = '正在保存中，请稍候'

                            //上传速度计算（滑动窗口平均）
                            var nowTime = new Date().getTime();
                            that.updateSpeed(nowTime, totalLoaded);
                        })
                        return xhr;
                    }
                });
            })
        },
        async getFileHash(file){ //获取文件MD5
            var that = this;
            this.filename = '正在读取文件(0%)'

            return new Promise((resolve) => {
                var fileReader = new FileReader(),
                    blobSlice = File.prototype.mozSlice || File.prototype.webkitSlice || File.prototype.slice,
                    chunkSize = 2097152,
                    chunks = Math.ceil(file.size / chunkSize),
                    currentChunk = 0,
                    spark = new SparkMD5();

                loadNext();

                fileReader.onload = function(e) {
                    spark.appendBinary(e.target.result);
                    currentChunk++;
                    var progressRate = Math.round(currentChunk / chunks * 100);
                    that.filename = '正在读取文件('+progressRate+'%)'
                    if (currentChunk < chunks) {
                        loadNext();
                    }
                    else {
                        resolve(spark.end());
                    }
                };

                function loadNext() {
                    var start = currentChunk * chunkSize,
                        end = start + chunkSize >= file.size ? file.size : start + chunkSize;
                    fileReader.readAsBinaryString(blobSlice.call(file, start, end));
                };
            })
        },
        uploadSuccess(hash){
            var lastTime = (new Date().getTime() - this.beginTime) / 1000;
            var jumpurl = "file.php?hash="+hash;
            if(this.input.ispwd && this.input.pwd!=''){
                jumpurl+='&pwd='+this.input.pwd;
            }
            this.show_msg('上传成功！总用时：'+lastTime.toFixed(2)+'秒。正在跳转到文件查看页面...');
            setTimeout(function(){ window.location.href=jumpurl; }, 800);
        },
        updateSpeed(currentTime, totalLoaded){
            this.speedHistory.push({time: currentTime, loaded: totalLoaded});
            // 移除超过3秒的旧样本
            while(this.speedHistory.length > 0 && currentTime - this.speedHistory[0].time > 3000) {
                this.speedHistory.shift();
            }
            // 计算平均速度（使用最早可用的样本，至少2个样本）
            if(this.speedHistory.length >= 2) {
                var first = this.speedHistory[0];
                var timeDiff = (currentTime - first.time) / 1000;
                var loadedDiff = totalLoaded - first.loaded;
                if(timeDiff > 0.1) {
                    var speed = loadedDiff / timeDiff;
                    this.uploadspeed = this.size_format(speed) + '/s';
                }
            }
        },
        size_format(size){
            var units = 'B';
            if(size/1024>1){
                size = size/1024;
                units = 'KB';
            }
            if(size/1024>1){
                size = size/1024;
                units = 'MB';
            }
            if(size/1024>1){
                size = size/1024;
                units = 'GB';
            }
            return size.toFixed(2)+units;
        }
    }
})