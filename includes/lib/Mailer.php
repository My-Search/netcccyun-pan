<?php
namespace lib;

class Mailer
{
    private $host;
    private $port;
    private $user;
    private $pass;
    private $secure;
    private $from;
    private $fromName;
    private $conn;
    private $lastError;

    public function __construct($config = [])
    {
        $this->host = isset($config['host']) ? $config['host'] : '';
        $this->port = isset($config['port']) ? $config['port'] : 587;
        $this->user = isset($config['user']) ? $config['user'] : '';
        $this->pass = isset($config['pass']) ? $config['pass'] : '';
        $this->secure = isset($config['secure']) ? strtolower($config['secure']) : 'tls';
        $this->from = isset($config['from']) ? $config['from'] : $this->user;
        $this->fromName = isset($config['fromname']) ? $config['fromname'] : '';
    }

    public function send($to, $subject, $body, $isHtml = true)
    {
        if (empty($this->host) || empty($this->user) || empty($this->pass)) {
            $this->lastError = 'SMTP配置不完整';
            return false;
        }

        $this->conn = $this->connect();
        if (!$this->conn) {
            return false;
        }

        $this->command('EHLO ' . gethostname());

        if ($this->secure === 'tls') {
            $this->command('STARTTLS');
            if (!stream_socket_enable_crypto($this->conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $this->lastError = 'TLS握手失败';
                $this->close();
                return false;
            }
            $this->command('EHLO ' . gethostname());
        }

        $this->command('AUTH LOGIN');
        $this->command(base64_encode($this->user));
        $this->command(base64_encode($this->pass));

        $from = $this->from ? $this->from : $this->user;
        $this->command('MAIL FROM:<' . $from . '>');
        $this->command('RCPT TO:<' . $to . '>');
        $this->command('DATA');

        $boundary = md5(uniqid());
        $subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $fromHeader = $this->fromName ? '=?UTF-8?B?' . base64_encode($this->fromName) . '?= <' . $from . '>' : $from;

        $headers = [];
        $headers[] = 'From: ' . $fromHeader;
        $headers[] = 'To: <' . $to . '>';
        $headers[] = 'Subject: ' . $subject;
        $headers[] = 'MIME-Version: 1.0';
        if ($isHtml) {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        }
        $headers[] = 'Content-Transfer-Encoding: base64';

        $message = implode("\r\n", $headers) . "\r\n\r\n";
        $message .= chunk_split(base64_encode($body));
        $message .= "\r\n.\r\n";

        $this->command($message, false);
        $this->command('QUIT');
        $this->close();
        return true;
    }

    private function connect()
    {
        $errno = 0;
        $errstr = '';
        $timeout = 10;

        if ($this->secure === 'ssl') {
            $address = 'ssl://' . $this->host . ':' . $this->port;
        } else {
            $address = 'tcp://' . $this->host . ':' . $this->port;
        }

        $conn = @stream_socket_client($address, $errno, $errstr, $timeout);
        if (!$conn) {
            $this->lastError = '连接SMTP服务器失败: ' . $errstr . ' (' . $errno . ')';
            return false;
        }

        stream_set_timeout($conn, $timeout);
        $response = $this->getResponse($conn);
        if (substr($response, 0, 3) !== '220') {
            $this->lastError = 'SMTP服务器响应异常: ' . $response;
            fclose($conn);
            return false;
        }
        return $conn;
    }

    private function command($cmd, $expectResponse = true)
    {
        fwrite($this->conn, $cmd . "\r\n");
        if ($expectResponse) {
            $response = $this->getResponse($this->conn);
            $code = substr($response, 0, 3);
            if ($code[0] !== '2' && $code[0] !== '3') {
                $this->lastError = 'SMTP命令失败: ' . $response;
                return false;
            }
        }
        return true;
    }

    private function getResponse($conn)
    {
        $response = '';
        while (($line = fgets($conn, 512)) !== false) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }
        return $response;
    }

    private function close()
    {
        if ($this->conn) {
            fclose($this->conn);
            $this->conn = null;
        }
    }

    public function error()
    {
        return $this->lastError;
    }
}
