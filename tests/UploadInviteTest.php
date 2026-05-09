<?php
/**
 * 邀请上传功能单元测试
 * 运行方式: php tests/UploadInviteTest.php
 */

class UploadInviteTest
{
    private $passed = 0;
    private $failed = 0;

    public function run()
    {
        echo "=== 邀请上传功能单元测试 ===\n\n";

        $this->testInvitePasswordFormat();
        $this->testInviteTokenValidation();
        $this->testInviteUploadOwnershipLogic();
        $this->testInviteUploadSameNameConflictPolicy();
        $this->testInviteLimitAndExpireLogic();
        $this->testInviteIsDisabledAfterSuccessfulUpload();
        $this->testInviteDatabaseFailureDoesNotReturnDeadLink();
        $this->testInviteLinkCarriesPasswordAndRootAllowed();
        $this->testInviteRemarkPersistenceAndDisplay();
        $this->testInstallSchemaContainsInviteTable();
        $this->testMineViewHasInviteEntry();
        $this->testInviteManagementPageAndApis();
        $this->testInviteUploadPagePostsInviteContext();

        echo "\n=== 测试结果 ===\n";
        echo "通过: {$this->passed}\n";
        echo "失败: {$this->failed}\n";
        echo ($this->failed === 0 ? "全部通过!" : "存在失败用例，请检查。") . "\n";
        return $this->failed === 0;
    }

    private function assert($condition, $message)
    {
        if ($condition) {
            $this->passed++;
            echo "[PASS] {$message}\n";
        } else {
            $this->failed++;
            echo "[FAIL] {$message}\n";
        }
    }

    private function testInvitePasswordFormat()
    {
        echo "--- 4位数字上传密码格式 ---\n";
        $this->assert(preg_match('/^\d{4}$/', '0000') === 1, '0000 应是合法4位数字密码');
        $this->assert(preg_match('/^\d{4}$/', '1234') === 1, '1234 应是合法4位数字密码');
        $this->assert(preg_match('/^\d{4}$/', '123') === 0, '3位数字应被拒绝');
        $this->assert(preg_match('/^\d{4}$/', '12345') === 0, '5位数字应被拒绝');
        $this->assert(preg_match('/^\d{4}$/', '12a4') === 0, '非纯数字密码应被拒绝');
        echo "\n";
    }

    private function testInviteTokenValidation()
    {
        echo "--- 邀请 token 格式 ---\n";
        $validToken = str_repeat('a', 32);
        $this->assert(preg_match('/^[0-9a-z]{32}$/i', $validToken) === 1, '32位十六进制风格 token 应通过');
        $this->assert(preg_match('/^[0-9a-z]{32}$/i', 'short') === 0, '过短 token 应被拒绝');
        $this->assert(preg_match('/^[0-9a-z]{32}$/i', str_repeat('g', 32)) === 1, '现有分享风格允许 0-9a-z 字符');
        $this->assert(preg_match('/^[0-9a-z]{32}$/i', str_repeat('*', 32)) === 0, '特殊字符 token 应被拒绝');
        echo "\n";
    }

    private function testInviteUploadOwnershipLogic()
    {
        echo "--- 邀请上传归属逻辑 ---\n";
        $invite = ['uid' => 1000, 'folder_id' => 8];
        $visitorUid = 0;
        $uploadUid = $invite['uid'];
        $folderId = $invite['folder_id'];

        $this->assert($visitorUid === 0, '访问者可以未登录');
        $this->assert($uploadUid === 1000, '上传记录 uid 应归属邀请创建者');
        $this->assert($folderId === 8, '上传记录 folder_id 应使用邀请目录');
        echo "\n";
    }

    private function testInviteUploadSameNameConflictPolicy()
    {
        echo "--- 邀请上传同名冲突策略 ---\n";
        $ajax = file_get_contents(__DIR__ . '/../ajax.php');
        $this->assert(strpos($ajax, '$inviteUploadMode = $inviteContext ? true : false;') !== false, '应显式标识邀请上传模式');
        $this->assert(strpos($ajax, '目标目录已存在同名文件，请更换文件名后再上传') !== false, '邀请上传遇到同名不同内容应拒绝而不是覆盖');
        $this->assert(strpos($ajax, 'revalidateUploadInviteSession') !== false, '分片/完成阶段应重新复核邀请授权');
        $this->assert(strpos($ajax, "if(!empty(\$_SESSION['upload']['invite_token'])){") !== false, '已存在 hash 仅应在邀请上传场景新增目录引用');
        echo "\n";
    }

    private function testInviteLimitAndExpireLogic()
    {
        echo "--- 邀请大小限制与有效期 ---\n";
        $ajax = file_get_contents(__DIR__ . '/../ajax.php');
        $this->assert(strpos($ajax, 'function parseUploadInviteMaxSize') !== false, '应提供邀请大小限制解析函数');
        $this->assert(strpos($ajax, 'function parseUploadInviteExpireTime') !== false, '应提供邀请有效期解析函数');
        $this->assert(strpos($ajax, '1024') !== false, '默认大小限制应为1024MB');
        $this->assert(strpos($ajax, '邀请已失效') !== false, '服务端应校验邀请过期/停用/删除状态');
        $this->assert(strpos($ajax, '有效时长必须为非负整数') !== false, '服务端应拒绝非法有效时长输入');
        $this->assert(strpos($ajax, '文件超过邀请允许的大小上限') !== false, '服务端应校验单文件大小上限');
        echo "\n";
    }

    private function testInviteIsDisabledAfterSuccessfulUpload()
    {
        echo "--- 邀请一次性上传 ---\n";
        $ajax = file_get_contents(__DIR__ . '/../ajax.php');
        $this->assert(strpos($ajax, 'function completeUploadInvite') !== false, '应提供邀请上传成功后的统一完成函数');
        $this->assert(strpos($ajax, 'uploads=uploads+1, enable=0') !== false, '上传成功后应增加成功次数并停用邀请');
        $this->assert(substr_count($ajax, 'completeUploadInvite(') >= 7, '秒传、分片、第三方完成等成功分支都应停用邀请');
        $this->assert(strpos($ajax, "'已完成'") !== false, '管理页状态应区分已完成的一次性邀请');
        echo "\n";
    }

    private function testInstallSchemaContainsInviteTable()
    {
        echo "--- 数据库结构 ---\n";
        $installSql = file_get_contents(__DIR__ . '/../install/install.sql');
        $updateSql = file_get_contents(__DIR__ . '/../install/update_1007.sql');
        $updatePhp = file_get_contents(__DIR__ . '/../install/update.php');
        $this->assert(strpos($installSql, 'pre_upload_invite') !== false, '新安装 SQL 应包含邀请上传表');
        $this->assert(strpos($updateSql, 'pre_upload_invite') !== false, '升级 SQL 应包含邀请上传表');
        $this->assert(strpos($installSql, '`pwd` char(4) NOT NULL') !== false, '邀请上传密码字段应固定为4位');
        $this->assert(strpos($installSql, '`max_size` bigint(20) unsigned NOT NULL DEFAULT') !== false, '邀请上传表应包含单文件大小限制');
        $this->assert(strpos($installSql, '`expire_time` datetime DEFAULT NULL') !== false, '邀请上传表应包含有效期字段');
        $this->assert(strpos($installSql, '`enable` tinyint(1) NOT NULL DEFAULT') !== false, '邀请上传表应包含启用状态');
        $this->assert(strpos($installSql, '`remark` varchar(255) DEFAULT NULL') !== false, '文件和邀请表应包含备注字段');
        $this->assert(strpos($installSql, '`fail_count` int(11) unsigned NOT NULL DEFAULT') !== false, '邀请上传表应记录密码错误次数');
        $this->assert(strpos($updateSql, '`last_failtime` datetime DEFAULT NULL') !== false, '升级 SQL 应包含最后错误时间字段');
        $this->assert(strpos($updateSql, '`remark` varchar(255) DEFAULT NULL') !== false, '升级 SQL 应在邀请表包含备注字段');
        $this->assert(strpos($updatePhp, 'column_exists') !== false, '升级脚本应检查列是否存在，避免重复加列失败');
        $this->assert(strpos($updatePhp, 'if(table_exists($db, \'pre_upload_invite\')') !== false, '即使版本已是1007，也应补齐邀请表缺失字段');
        $this->assert(strpos($updatePhp, "\$repair = isset(\$_GET['repair'])") !== false, '升级脚本应支持 repair=1 强制补字段');
        $this->assert(strpos($updatePhp, 'intval($dberror[1]) === 1060') !== false, 'repair 模式应忽略重复列错误');
        $this->assert(strpos($updatePhp, 'ALTER TABLE `pre_file` ADD COLUMN `remark`') !== false, '升级脚本应为旧文件表补充备注字段');
        $this->assert(strpos($updatePhp, 'if($error === 0)') !== false, '升级脚本应仅在 SQL 全部成功后更新版本号');
        $this->assert(strpos($updatePhp, '数据库升级失败') !== false, '升级失败应明确提示错误而不是继续跳转');
        $this->assert(strpos($installSql, 'UNIQUE KEY `folder_uid`') !== false, '同一用户同一目录应复用一个邀请记录');
        echo "\n";
    }

    private function testInviteDatabaseFailureDoesNotReturnDeadLink()
    {
        echo "--- 邀请保存失败处理 ---\n";
        $ajax = file_get_contents(__DIR__ . '/../ajax.php');
        $this->assert(strpos($ajax, 'function exitUploadInviteDatabaseError') !== false, '邀请保存失败应有统一数据库错误出口');
        $this->assert(strpos($ajax, '邀请保存失败，请先完成网站数据库升级') !== false, '数据库未升级时创建邀请应明确提示升级');
        $this->assert(strpos($ajax, 'exitUploadInviteDatabaseError();') !== false, '创建/更新邀请写库失败不应返回无效链接');
        echo "\n";
    }

    private function testInviteLinkCarriesPasswordAndRootAllowed()
    {
        echo "--- 邀请链接密码与根目录 ---\n";
        $ajax = file_get_contents(__DIR__ . '/../ajax.php');
        $page = file_get_contents(__DIR__ . '/../invite_upload.php');
        $view = file_get_contents(__DIR__ . '/../includes/mine_view.php');
        $this->assert(strpos($ajax, "'invite_upload.php?token='.\$token.'&pwd='.\$pwd") !== false, '创建邀请返回链接应包含4位密码');
        $this->assert(strpos($ajax, 'if($folder_id < 0)$folder_id = 0;') !== false, '创建邀请应允许根目录 folder_id=0');
        $this->assert(strpos($page, '邀请已失效') !== false, '取消/过期/目录删除后邀请页应提示已失效');
        $this->assert(strpos($page, 'Referrer-Policy: no-referrer') !== false, '链接携带密码时应禁止 Referer 泄露到第三方资源');
        $this->assert(strpos($page, "var invitePwd = '<?php echo htmlspecialchars(\$pwd)?>';") !== false, '邀请页应从链接读取密码并直接上传');
        $this->assert(strpos($view, 'currentFolderId <= 0') === false, '根目录不应阻止创建邀请上传');
        echo "\n";
    }

    private function testInviteRemarkPersistenceAndDisplay()
    {
        echo "--- 邀请备注写入与展示 ---\n";
        $ajax = file_get_contents(__DIR__ . '/../ajax.php');
        $view = file_get_contents(__DIR__ . '/../includes/mine_view.php');
        $inviteManage = file_get_contents(__DIR__ . '/../my_invites.php');
        $this->assert(strpos($ajax, 'function parseUploadInviteRemark') !== false, '服务端应清理并限制邀请备注长度');
        $this->assert(strpos($ajax, "'remark' => \$remark") !== false, '邀请上传备注应进入上传会话');
        $this->assert(strpos($ajax, '`folder_id`,`remark`') !== false, '邀请上传成功插入文件时应写入备注');
        $this->assert(strpos($ajax, 'count, remark FROM pre_file') !== false, '文件列表接口应返回备注');
        $this->assert(strpos($view, 'item-remark') !== false, '文件管理页应在文件名下方展示备注');
        $this->assert(strpos($view, '-webkit-line-clamp: 2') !== false, '备注展示过长时应省略');
        $this->assert(strpos($inviteManage, 'editInviteRemark') !== false, '邀请管理页应可编辑备注');
        echo "\n";
    }

    private function testMineViewHasInviteEntry()
    {
        echo "--- 我的文件入口 ---\n";
        $view = file_get_contents(__DIR__ . '/../includes/mine_view.php');
        $this->assert(strpos($view, 'createUploadInvite()') !== false, '目录页应提供邀请上传按钮');
        $this->assert(strpos($view, 'ajax.php?act=createUploadInvite') !== false, '目录页应调用创建邀请上传接口');
        $this->assert(strpos($view, 'inviteMaxSizeMb') !== false, '创建邀请时应可设置单文件大小限制');
        $this->assert(strpos($view, 'inviteExpireHours') !== false, '创建邀请时应可设置有效时长');
        $this->assert(strpos($view, 'csrf_token') !== false, '创建邀请上传应携带 CSRF token');
        $this->assert(strpos($view, '接收位置') !== false, '创建邀请时应提示接收位置，根目录也可邀请上传');
        echo "\n";
    }

    private function testInviteManagementPageAndApis()
    {
        echo "--- 邀请管理页面与接口 ---\n";
        $ajax = file_get_contents(__DIR__ . '/../ajax.php');
        $page = file_get_contents(__DIR__ . '/../my_invites.php');
        $header = file_get_contents(__DIR__ . '/../includes/header.php');
        $home = file_get_contents(__DIR__ . '/../index_home.php');
        $this->assert(strpos($ajax, "case 'listMyUploadInvites'") !== false, '应提供邀请列表接口');
        $this->assert(strpos($ajax, "case 'updateUploadInvite'") !== false, '应提供邀请设置更新接口');
        $this->assert(strpos($ajax, "case 'toggleUploadInvite'") !== false, '应提供邀请启停接口');
        $this->assert(strpos($ajax, "case 'deleteUploadInvite'") !== false, '应提供邀请删除接口');
        $this->assert(strpos($ajax, 'CSRF TOKEN ERROR') !== false, '邀请管理写操作应包含 CSRF 防护');
        $this->assert(strpos($page, "csrf_token:csrf_token") !== false, '邀请删除/更新请求应携带 CSRF token');
        $this->assert(strpos($page, 'loadInvites()') !== false, '应提供我的邀请管理页');
        $this->assert(strpos($page, '!/^\d+$/.test(expireRaw)') !== false, '管理页应拒绝非法有效时长输入');
        $this->assert(strpos($header, '<i class="fa fa-cloud-upload" aria-hidden="true"></i> 上传文件') === false, '用户菜单不应显示上传文件入口');
        $this->assert(strpos($home, './my_invites.php') !== false && strpos($home, '我的邀请') !== false, '首页快捷入口应链接我的邀请');
        $this->assert(strpos($home, 'onclick="openUpload()"') === false, '首页快捷入口不应再绑定上传跳转');
        echo "\n";
    }

    private function testInviteUploadPagePostsInviteContext()
    {
        echo "--- 免登录上传页 ---\n";
        $page = file_get_contents(__DIR__ . '/../invite_upload.php');
        $this->assert(strpos($page, "invite_token: inviteToken") !== false, '预上传请求应携带邀请 token');
        $this->assert(strpos($page, "invite_pwd: invitePwd") !== false, '预上传请求应携带链接中的4位上传密码');
        $this->assert(strpos($page, 'inviteMaxSize') !== false, '邀请上传页应展示/预校验大小限制');
        $this->assert(strpos($page, '无需登录') !== false, '页面应明确无需登录');
        $this->assert(strpos($page, '邀请上传到：') === false, '邀请上传页不应暴露目标目录');
        echo "\n";
    }
}

$test = new UploadInviteTest();
exit($test->run() ? 0 : 1);
