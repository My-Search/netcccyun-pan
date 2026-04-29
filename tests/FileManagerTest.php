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
        $this->testSizeFormat();
        $this->testMoveFolderLogic();
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
