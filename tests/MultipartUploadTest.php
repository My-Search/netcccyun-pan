<?php
/**
 * 分片上传合并安全测试
 * 运行方式: php tests/MultipartUploadTest.php
 */

require_once __DIR__ . '/../includes/functions.php';

class MultipartUploadTest
{
    private $passed = 0;
    private $failed = 0;
    private $tempFiles = [];

    public function run()
    {
        echo "=== 分片上传合并安全测试 ===\n\n";

        $this->testMergeSuccessAndContentIntegrity();
        $this->testMergeMissingChunkFailsSafely();
        $this->testMergeInvalidChunksFailsSafely();
        $this->testMergeLockRejectsConcurrentLastChunk();
        $this->testUploadPartRejectsChunkOutOfRange();

        $this->cleanup();

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

    private function tempPath($hash, $suffix)
    {
        return sys_get_temp_dir() . '/' . $hash . $suffix;
    }

    private function trackHash($hash)
    {
        $this->tempFiles[] = $this->tempPath($hash, '.parttmp');
        $this->tempFiles[] = $this->tempPath($hash, '.partlock');
        for ($i = 1; $i <= 5; $i++) {
            $this->tempFiles[] = $this->tempPath($hash, '.part'.$i);
        }
    }

    private function cleanup()
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }

    private function testMergeSuccessAndContentIntegrity()
    {
        echo "--- 正常合并与内容一致性 ---\n";
        $content = 'part-one-' . str_repeat('中', 100) . '-part-two-part-three';
        $hash = md5($content);
        $this->trackHash($hash);
        file_put_contents($this->tempPath($hash, '.part1'), substr($content, 0, 20));
        file_put_contents($this->tempPath($hash, '.part2'), substr($content, 20, 120));
        file_put_contents($this->tempPath($hash, '.part3'), substr($content, 140));

        $merged = file_part_merge($hash, 3);
        $this->assert(file_exists($merged), '合并成功后应生成 parttmp 文件');
        $this->assert(file_get_contents($merged) === $content, '合并输出内容应与原始内容一致');
        $this->assert(md5_file($merged) === $hash, '合并输出 MD5 应与上传 hash 一致');
        $this->assert(!file_exists($this->tempPath($hash, '.part1')), '合并成功后应清理已合并分片');
        @unlink($merged);
        echo "\n";
    }

    private function testMergeMissingChunkFailsSafely()
    {
        echo "--- 缺块安全失败 ---\n";
        $hash = md5('missing-chunk-test');
        $this->trackHash($hash);
        file_put_contents($this->tempPath($hash, '.part1'), 'first');
        file_put_contents($this->tempPath($hash, '.part3'), 'third');

        $result = $this->runMergeInChild($hash, 3);
        $this->assert(strpos($result, 'missing_chunk') !== false, '缺少中间分片时应返回 missing_chunk 错误');
        $this->assert(!file_exists($this->tempPath($hash, '.parttmp')), '缺块失败时不应保留不完整 parttmp');
        $this->assert(file_exists($this->tempPath($hash, '.part1')), '缺块失败时应保留已上传分片供重试');
        echo "\n";
    }

    private function testMergeInvalidChunksFailsSafely()
    {
        echo "--- chunks 参数校验 ---\n";
        $hash = md5('invalid-chunks-test');
        $this->trackHash($hash);
        $result = $this->runMergeInChild($hash, 0);
        $this->assert(strpos($result, '参数错误') !== false, 'chunks 小于等于 0 时应拒绝合并');
        $this->assert(!file_exists($this->tempPath($hash, '.parttmp')), '参数错误时不应生成 parttmp');
        echo "\n";
    }

    private function testMergeLockRejectsConcurrentLastChunk()
    {
        echo "--- 重复/并发最后分片锁定 ---\n";
        $hash = md5('merge-lock-test');
        $this->trackHash($hash);
        $lockFile = $this->tempPath($hash, '.partlock');
        $lock = fopen($lockFile, 'c');
        flock($lock, LOCK_EX);

        $result = $this->runMergeInChild($hash, 1);
        $this->assert(strpos($result, 'merge_locked') !== false, '已有合并锁时应安全失败');
        $this->assert(!file_exists($this->tempPath($hash, '.parttmp')), '并发失败时不应生成 parttmp');

        flock($lock, LOCK_UN);
        fclose($lock);
        echo "\n";
    }

    private function testUploadPartRejectsChunkOutOfRange()
    {
        echo "--- upload_part 分块序号边界 ---\n";
        $rootAjax = file_get_contents(__DIR__ . '/../ajax.php');
        $includeAjax = file_get_contents(__DIR__ . '/../includes/ajax.php');
        $this->assert(strpos($rootAjax, 'if($chunk < 1 || $chunk > $chunks)') !== false, '根目录 ajax.php 应拒绝越界 chunk');
        $this->assert(strpos($includeAjax, 'if($chunk < 1 || $chunk > $chunks)') !== false, 'includes/ajax.php 应拒绝越界 chunk');
        $this->assert(substr_count($rootAjax . $includeAjax, '"error":"chunk_range"') >= 2, '越界 chunk 应返回兼容 JSON 错误字段');
        echo "\n";
    }

    private function runMergeInChild($hash, $chunks)
    {
        $code = 'require '.var_export(__DIR__ . '/../includes/functions.php', true).'; file_part_merge('.var_export($hash, true).', '.intval($chunks).');';
        $cmd = PHP_BINARY . ' -r ' . escapeshellarg($code);
        return shell_exec($cmd);
    }
}

$test = new MultipartUploadTest();
exit($test->run() ? 0 : 1);
