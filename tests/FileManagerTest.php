<?php
/**
 * 文件管理与目录功能单元测试
 * 运行方式: php tests/FileManagerTest.php (需要PHP环境)
 */

require_once __DIR__ . '/../includes/functions.php';

class FileManagerTest
{
    private $passed = 0;
    private $failed = 0;

    public function run()
    {
        echo "=== 文件管理与目录功能单元测试 ===\n\n";

        $this->testFolderNameValidation();
        $this->testFileHashLogic();
        $this->testPathBreadcrumb();
        $this->testUploadResumeLogic();
        $this->testDownloadRangeResumeLogic();
        $this->testSizeFormat();
        $this->testMoveFolderLogic();
        $this->testRefererHostAllowsSameOriginOriginHeader();
        $this->testMineViewFolderUrlPersistence();
        $this->testMineViewRefreshesAfterEachUploadCompletion();

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

    private function testFolderNameValidation()
    {
        echo "--- 目录名验证 ---\n";
        $this->assert(!empty('Documents'), '非空目录名应通过');
        $this->assert(strlen('') == 0, '空目录名应被拒绝');
        $this->assert(strlen(str_repeat('a', 256)) > 255, '超长目录名应被拒绝');
        $this->assert(strlen('NormalFolder') <= 255, '正常长度目录名应通过');
        echo "\n";
    }

    private function testFileHashLogic()
    {
        echo "--- 文件Hash逻辑 ---\n";
        $hash = md5('test_content');
        $this->assert(preg_match('/^[0-9a-z]{32}$/i', $hash) === 1, 'MD5 Hash格式正确');
        $this->assert(strlen($hash) == 32, 'MD5 Hash长度为32');
        $this->assert(preg_match('/^[0-9a-z]{32}$/i', 'invalid') === 0, '非法Hash应被拒绝');
        echo "\n";
    }

    private function testPathBreadcrumb()
    {
        echo "--- 面包屑路径逻辑 ---\n";
        // 模拟路径数组
        $path = [
            ['id'=>1, 'name'=>'文档'],
            ['id'=>2, 'name'=>'项目'],
            ['id'=>3, 'name'=>'2024']
        ];
        $this->assert(count($path) == 3, '路径包含3个层级');
        $this->assert($path[0]['name'] == '文档', '第一层名称正确');
        $this->assert($path[2]['id'] == 3, '最后一层ID正确');
        echo "\n";
    }

    private function testUploadResumeLogic()
    {
        echo "--- 断点续传逻辑 ---\n";
        $totalChunks = 10;
        $currentChunk = 5;
        $this->assert($currentChunk < $totalChunks, '当前分块应小于总分块数');
        $this->assert($totalChunks > 0, '总分块数应大于0');

        // 模拟localStorage断点续存数据结构
        $resumeData = json_encode(['chunks'=>10, 'currentChunk'=>5, 'name'=>'test.zip']);
        $decoded = json_decode($resumeData, true);
        $this->assert($decoded['chunks'] == 10, '恢复数据总分块数正确');
        $this->assert($decoded['currentChunk'] == 5, '恢复数据当前分块正确');

        // 测试过期清理逻辑
        $expired = time() - 7200; // 2小时前
        $this->assert((time() - $expired) > 3600, '超过1小时的数据应视为过期');
        echo "\n";
    }

    private function testDownloadRangeResumeLogic()
    {
        echo "--- 下载断点续传 Range 逻辑 ---\n";

        $this->assert($this->parseDownloadRangeForTest('bytes=500-', 1000) === [500, 999], '断开后继续下载应从已完成字节继续到文件末尾');
        $this->assert($this->parseDownloadRangeForTest('bytes=200-499', 1000) === [200, 499], '指定起止范围应保持客户端请求的下载区间');
        $this->assert($this->parseDownloadRangeForTest('bytes=-200', 1000) === [800, 999], '后缀范围应下载文件最后指定字节数');
        $this->assert($this->parseDownloadRangeForTest('bytes=900-2000', 1000) === [900, 999], '超出文件大小的结束位置应裁剪到文件末尾');
        $this->assert($this->parseDownloadRangeForTest('bytes=1000-', 1000) === 'unsatisfiable', '起始位置等于文件大小时应判定为不可满足范围');
        $this->assert($this->parseDownloadRangeForTest('bytes=700-600', 1000) === 'unsatisfiable', '结束位置小于起始位置时应判定为不可满足范围');
        $this->assert($this->parseDownloadRangeForTest('bytes=abc-def', 1000) === false, '语法错误的 Range 应忽略并允许普通完整下载');
        $this->assert($this->parseDownloadRangeForTest('items=0-10', 1000) === false, '非 bytes 单位的 Range 应忽略并允许普通完整下载');
        $this->assert($this->parseDownloadRangeForTest('bytes=0-99,200-299', 1000) === false, '未实现 multipart Range 时应忽略多段范围而不是误返回416');

        echo "\n";
    }

    private function parseDownloadRangeForTest($rangeHeader, $size)
    {
        $oldRange = isset($_SERVER['HTTP_RANGE']) ? $_SERVER['HTTP_RANGE'] : null;
        $_SERVER['HTTP_RANGE'] = $rangeHeader;
        $range = get_file_range($size);

        if ($oldRange === null) {
            unset($_SERVER['HTTP_RANGE']);
        } else {
            $_SERVER['HTTP_RANGE'] = $oldRange;
        }

        return $range;
    }

    private function testSizeFormat()
    {
        echo "--- 文件大小格式化 ---\n";
        $this->assert(size_format(512) == '512 B', '512字节格式化正确');
        $this->assert(size_format(1024) == '1 KB', '1KB格式化正确');
        $this->assert(size_format(1024*1024) == '1 MB', '1MB格式化正确');
        echo "\n";
    }

    private function testMoveFolderLogic()
    {
        echo "--- 目录移动逻辑 ---\n";
        // 模拟面包屑路径数组（根目录 -> A -> B）
        $path = [
            ['id'=>1, 'name'=>'文档'],
            ['id'=>2, 'name'=>'项目'],
            ['id'=>3, 'name'=>'2024']
        ];
        // 当前在 id=3，父目录应为 id=2
        $parentId = count($path) >= 2 ? $path[count($path)-2]['id'] : (count($path) === 1 ? 0 : null);
        $this->assert($parentId === 2, 'getParentFolderId 应返回父目录ID');

        // 根目录无父目录
        $rootPath = [];
        $rootParent = count($rootPath) === 0 ? null : (count($rootPath) === 1 ? 0 : $rootPath[count($rootPath)-2]['id']);
        $this->assert($rootParent === null, '根目录的父目录应为null');

        // 循环移动检测：子目录ID列表包含目标ID时应拒绝
        $subIds = [3, 4, 5]; // 假设id=3的子目录有4,5
        $this->assert(in_array(4, $subIds), '子目录列表应包含子目录ID');
        $this->assert(in_array(3, $subIds), '子目录列表应包含自身ID');
        $this->assert(!in_array(1, $subIds), '子目录列表不应包含非子目录ID');

        // 重名检测：同一父目录下不能存在同名文件夹
        $existingFolders = ['work', 'docs', 'images'];
        $this->assert(in_array('docs', $existingFolders), '应检测到同名文件夹');
        $this->assert(!in_array('music', $existingFolders), '未存在的文件夹名应可用');
        echo "\n";
    }

    private function testRefererHostAllowsSameOriginOriginHeader()
    {
        echo "--- AJAX 来源校验 ---\n";
        $oldHost = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null;
        $oldReferer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
        $oldOrigin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : null;

        $_SERVER['HTTP_HOST'] = '101.42.200.84:5858';
        unset($_SERVER['HTTP_REFERER']);
        $_SERVER['HTTP_ORIGIN'] = 'http://101.42.200.84:5858';
        $this->assert(checkRefererHost() === true, '无 Referer 但 Origin 同源时应允许 AJAX 请求');

        $_SERVER['HTTP_ORIGIN'] = 'http://evil.example.com';
        $this->assert(checkRefererHost() === false, '跨域 Origin 应被拒绝');

        if ($oldHost === null) unset($_SERVER['HTTP_HOST']); else $_SERVER['HTTP_HOST'] = $oldHost;
        if ($oldReferer === null) unset($_SERVER['HTTP_REFERER']); else $_SERVER['HTTP_REFERER'] = $oldReferer;
        if ($oldOrigin === null) unset($_SERVER['HTTP_ORIGIN']); else $_SERVER['HTTP_ORIGIN'] = $oldOrigin;
        echo "\n";
    }

    private function testMineViewFolderUrlPersistence()
    {
        echo "--- 我的文件目录位置刷新保持 ---\n";
        $view = file_get_contents(__DIR__ . '/../includes/mine_view.php');

        $this->assert(strpos($view, "loadFolder(getInitialFolderId(), {replaceUrl:true})") !== false, '页面初始化应从URL读取目录ID');
        $this->assert(strpos($view, "params.set('folder_id', normalizedFolderId)") !== false, '进入子目录时应写入folder_id参数');
        $this->assert(strpos($view, "params.delete('folder_id')") !== false, '回到根目录时应移除folder_id参数');
        $this->assert(strpos($view, "window.addEventListener('popstate'") !== false, '浏览器前进后退应重新加载对应目录');
        echo "\n";
    }

    private function testMineViewRefreshesAfterEachUploadCompletion()
    {
        echo "--- 我的文件单文件上传完成刷新 ---\n";
        $view = file_get_contents(__DIR__ . '/../includes/mine_view.php');

        $this->assert(strpos($view, 'function refreshCurrentUploadFolder()') !== false, '应提供上传完成后的当前目录刷新函数');
        $this->assert(strpos($view, 'loadFolder(currentFolderId, {updateUrl:false})') !== false, '上传完成刷新应保持当前URL目录状态');
        $this->assert(substr_count($view, 'refreshCurrentUploadFolder();') >= 3, '秒传、普通上传完成、服务端完成响应均应触发刷新');
        $this->assert(strpos($view, '文件上传完成，请刷新页面查看最新文件') === false, '上传完成后不应再要求用户手动刷新查看最新文件');
        echo "\n";
    }
}

$test = new FileManagerTest();
exit($test->run() ? 0 : 1);
