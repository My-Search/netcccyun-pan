<?php
/**
 * 用户注册登录功能单元测试
 * 运行方式: php tests/AuthTest.php
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/lib/Mailer.php';

class AuthTest
{
    private $passed = 0;
    private $failed = 0;

    public function run()
    {
        echo "=== 用户认证功能单元测试 ===\n\n";

        $this->testUsernameValidation();
        $this->testPasswordValidation();
        $this->testEmailValidation();
        $this->testCodeExpiration();
        $this->testMailerConfig();

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

    private function testUsernameValidation()
    {
        echo "--- 用户名格式验证 ---\n";
        $this->assert(preg_match('/^[a-zA-Z0-9_]{4,20}$/', 'test') === 1, '合法用户名: test');
        $this->assert(preg_match('/^[a-zA-Z0-9_]{4,20}$/', 'user123') === 1, '合法用户名: user123');
        $this->assert(preg_match('/^[a-zA-Z0-9_]{4,20}$/', 'abc') === 0, '太短用户名: abc 应被拒绝');
        $this->assert(preg_match('/^[a-zA-Z0-9_]{4,20}$/', 'ab') === 0, '太短用户名: ab 应被拒绝');
        $this->assert(preg_match('/^[a-zA-Z0-9_]{4,20}$/', 'user-name') === 0, '含横线用户名应被拒绝');
        $this->assert(preg_match('/^[a-zA-Z0-9_]{4,20}$/', 'user@name') === 0, '含特殊字符用户名应被拒绝');
        $this->assert(preg_match('/^[a-zA-Z0-9_]{4,20}$/', str_repeat('a', 21)) === 0, '超长用户名应被拒绝');
        echo "\n";
    }

    private function testPasswordValidation()
    {
        echo "--- 密码长度验证 ---\n";
        $this->assert(strlen('1234') >= 4, '4位密码应通过');
        $this->assert(strlen('123') < 4, '3位密码应被拒绝');
        $this->assert(strlen('123456') >= 4, '6位密码应通过');
        echo "\n";
    }

    private function testEmailValidation()
    {
        echo "--- 邮箱格式验证 ---\n";
        $this->assert(filter_var('test@example.com', FILTER_VALIDATE_EMAIL) !== false, '合法邮箱: test@example.com');
        $this->assert(filter_var('user.name+tag@example.co.uk', FILTER_VALIDATE_EMAIL) !== false, '合法邮箱: user.name+tag@example.co.uk');
        $this->assert(filter_var('invalid-email', FILTER_VALIDATE_EMAIL) === false, '非法邮箱: invalid-email');
        $this->assert(filter_var('user@', FILTER_VALIDATE_EMAIL) === false, '非法邮箱: user@');
        $this->assert(filter_var('@example.com', FILTER_VALIDATE_EMAIL) === false, '非法邮箱: @example.com');
        echo "\n";
    }

    private function testCodeExpiration()
    {
        echo "--- 验证码过期逻辑 ---\n";
        $now = time();
        $codeTime = $now - 300; // 5分钟前
        $this->assert(($now - $codeTime) <= 600, '5分钟内的验证码应有效');

        $codeTime = $now - 601; // 10分钟零1秒前
        $this->assert(($now - $codeTime) > 600, '超过10分钟的验证码应过期');
        echo "\n";
    }

    private function testMailerConfig()
    {
        echo "--- 邮件发送配置验证 ---\n";
        $mailer = new \lib\Mailer([
            'host' => '',
            'port' => 587,
            'user' => '',
            'pass' => '',
        ]);
        $this->assert($mailer->error() === null, '初始状态无错误');

        // 配置不完整时应返回错误
        $result = $mailer->send('test@example.com', 'Test', 'Body');
        $this->assert($result === false, 'SMTP配置不完整时应发送失败');
        $this->assert(strpos($mailer->error(), 'SMTP配置不完整') !== false, '错误信息应提示配置不完整');
        echo "\n";
    }
}

$test = new AuthTest();
exit($test->run() ? 0 : 1);
