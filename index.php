<?php
if (version_compare(PHP_VERSION, '7.1.0', '<')) {
    die('require PHP >= 7.1 !');
}
include("./includes/common.php");

if(!$islogin2){
    $title = $conf['title'];
    include SYSTEM_ROOT.'header.php';
    ?>
    <div class="container">
        <div class="row">
            <div class="col-md-6 col-md-offset-3 col-sm-8 col-sm-offset-2">
                <div class="panel panel-default" style="margin-top: 15%; border-radius: 8px; box-shadow: 0 2px 15px rgba(0,0,0,0.08);">
                    <div class="panel-body text-center" style="padding: 48px 32px;">
                        <div style="margin-bottom: 24px;">
                            <i class="fa fa-lock" style="font-size: 64px; color: #e0e0e0;"></i>
                        </div>
                        <h2 style="margin-top: 0; margin-bottom: 12px; font-weight: 400; color: #333;">请先登录</h2>
                        <p class="text-muted" style="font-size: 15px; margin-bottom: 32px;">登录后即可管理您的文件，享受完整的网盘服务。</p>
                        <a href="./login.php" class="btn btn-primary btn-lg btn-raised" style="padding: 12px 40px; border-radius: 4px;">
                            <i class="fa fa-sign-in" style="margin-right: 6px;"></i>立即登录
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    include SYSTEM_ROOT.'footer.php';
    exit;
}

require __DIR__ . '/index_home.php';
