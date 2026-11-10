<?php
declare(strict_types=1);

/* ==================== 反爬 ==================== */
/**
 * 直接访问时自动跳转地址
 */
const REDIRECT_URL = 'https://your-domain.com';

/* ------------------------------------------------------------
 * 判断是否有输入参数
 * ------------------------------------------------------------ */
$rawBody = file_get_contents('php://input');
$hasInput = !empty($_GET) || !empty($_POST);
if (!$hasInput && $rawBody !== '' && $rawBody !== false) {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded) && !empty($decoded)) {
        $hasInput = true;
    }
}

/* ------------------------------------------------------------
 * 无参数输出跳转页
 * ------------------------------------------------------------ */
if (!$hasInput && REDIRECT_URL !== '') {
    header('Content-Type: text/html; charset=utf-8');
    $u = htmlspecialchars(REDIRECT_URL, ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta http-equiv="refresh" content="0; url=' . $u . '">
</head>
<body>
<a href="' . $u . '">Click here</a>
</body>
</html>';
    exit;
}

/* ============================================================
 *  邮件发送接口逻辑
 * ============================================================ */

/* ==================== 配置区 ==================== */

/* ---------- 多密钥 ----------
 * 空数组 = 不校验
 * 生成新密钥：php -r "echo bin2hex(random_bytes(32));"
 */
const API_TOKENS = [
    '<YOUR_TOKEN>',
    '<YOUR_TOKEN_2>',
    // '再填一个密钥',
];

const CONNECT_TIMEOUT = 20;    // SMTP 连接超时（秒）
const MAX_PER_MINUTE  = 0;     // 单 IP 每分钟上限，0=不限
const ALLOWED_HOSTS   = [];    // SMTP 白名单，空=不限。例 ['smtp.qq.com']
const DIRECT_HELO     = '';    // 直投时 HELO 域名，留空默认发件人邮箱

/* ============================================== */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-Api-Token, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

/* ---------------- 工具 ---------------- */
function out(bool $ok, string $msg, array $extra = []): void {
    echo json_encode(array_merge(['success'=>$ok,'message'=>$msg],$extra),
                     JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function cleanHeader(string $s): string { return trim(preg_replace('/[\r\n]+/',' ',$s)); }
function mimeEnc(string $s): string {
    $s = cleanHeader($s);
    return preg_match('/[^\x20-\x7E]/',$s) ? '=?UTF-8?B?'.base64_encode($s).'?=' : $s;
}
function isEmail(string $e): bool { return (bool)filter_var($e, FILTER_VALIDATE_EMAIL); }
function parseList($v): array {
    if ($v === null || $v === '') return [];
    $arr = is_array($v) ? $v : preg_split('/[,;\s]+/', (string)$v);
    $r = [];
    foreach ($arr as $x) { $x = trim((string)$x); if ($x !== '' && isEmail($x)) $r[] = $x; }
    return array_values(array_unique($r));
}
function clientIp(): string {
    foreach (['HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $k)
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',',(string)$_SERVER[$k])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    return '0.0.0.0';
}
function rateLimit(string $ip): void {
    if (MAX_PER_MINUTE <= 0) return;
    $dir = sys_get_temp_dir().'/mailapi_rl';
    if (!is_dir($dir)) @mkdir($dir,0700,true);
    $file = $dir.'/'.md5($ip).'.json';
    $now = time();
    $hits = [];
    if (is_file($file)) {
        $j = json_decode((string)@file_get_contents($file), true);
        if (is_array($j['hits'] ?? null)) $hits = $j['hits'];
    }
    $hits = array_values(array_filter($hits, fn($t) => (int)$t > $now - 60));
    if (count($hits) >= MAX_PER_MINUTE) out(false, '请求过于频繁，请稍后再试');
    $hits[] = $now;
    @file_put_contents($file, json_encode(['hits'=>$hits]), LOCK_EX);
}
/** 查 MX，按优先级排序；无 MX 回退域名本身 */
function resolveMx(string $domain): array {
    $hosts = [];
    if (function_exists('getmxrr')) {
        $mx = []; $w = [];
        if (@getmxrr($domain, $mx, $w) && $mx) {
            $list = [];
            foreach ($mx as $i => $h) $list[] = ['h'=>rtrim((string)$h,'.'), 'w'=>(int)($w[$i] ?? 0)];
            usort($list, fn($a,$b) => $a['w'] <=> $b['w']);
            foreach ($list as $x) $hosts[] = $x['h'];
        }
    }
    return $hosts ?: [$domain];
}

/* ---------------- 极简 SMTP 客户端 ---------------- */
final class Smtp {
    private $fp = null;
    private string $host, $secure;
    private int $port, $timeout;
    private array $caps = [];
    public array $log = [];

    public function __construct(string $host, int $port, string $secure, int $timeout = 20) {
        $this->host=$host; $this->port=$port; $this->secure=$secure; $this->timeout=$timeout;
    }
    public function __destruct() { $this->quit(); }

    public function connect(): void {
        $prefix = $this->secure === 'ssl' ? 'ssl://' : '';
        $ctx = stream_context_create(['ssl'=>[
            'verify_peer'=>true,'verify_peer_name'=>true,
            'allow_self_signed'=>false,'SNI_enabled'=>true,'peer_name'=>$this->host,
        ]]);
        $errno = 0; $errstr = '';
        $fp = @stream_socket_client($prefix.$this->host.':'.$this->port, $errno, $errstr,
                                    $this->timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) throw new Exception("无法连接 {$this->host}:{$this->port} — {$errstr} ({$errno})");
        $this->fp = $fp;
        stream_set_timeout($fp, $this->timeout);
        $this->log[] = "已连接 {$this->host}:{$this->port}";
        $banner = $this->read();
        if ((int)substr(trim($banner),0,3) !== 220)
            throw new Exception('SMTP 问候异常：'.trim($banner));
    }
    private function read(): string {
        $d = '';
        while (true) {
            $line = @fgets($this->fp, 8192);
            if ($line === false) {
                $m = @stream_get_meta_data($this->fp);
                throw new Exception(!empty($m['timed_out']) ? 'SMTP 读取超时' : 'SMTP 连接被关闭');
            }
            $d .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        return $d;
    }
    private function cmd(string $line, array $ok): string {
        if ($line !== '') {
            $this->log[] = 'C: '.$line;
            if (@fwrite($this->fp, $line."\r\n") === false) throw new Exception('写入 SMTP 失败');
        }
        $r = $this->read();
        $this->log[] = 'S: '.trim($r);
        $code = (int)substr(trim($r),0,3);
        if (!in_array($code, $ok, true)) throw new Exception("SMTP {$code}：".trim($r));
        return $r;
    }
    public function ehlo(string $d): void {
        $r = $this->cmd('EHLO '.$d, [250]);
        $this->caps = [];
        $lines = preg_split('/\r?\n/', trim($r));
        array_shift($lines);
        foreach ($lines as $l) {
            $l = trim($l); if ($l === '') continue;
            $p = explode(' ', $l, 2);
            $this->caps[strtoupper($p[0])] = $p[1] ?? '';
        }
    }
    public function hasCap(string $n): bool { return isset($this->caps[strtoupper($n)]); }
    public function startTls(): void {
        $this->cmd('STARTTLS', [220]);
        if (@stream_socket_enable_crypto($this->fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true)
            throw new Exception('STARTTLS 握手失败');
        $this->log[] = 'TLS 已建立';
    }
    public function auth(string $u, string $p): void {
        $caps = strtoupper((string)($this->caps['AUTH'] ?? ''));
        if ($caps === '' || strpos($caps,'LOGIN') !== false) {
            try {
                $this->cmd('AUTH LOGIN', [334]);
                $this->cmd(base64_encode($u), [334]);
                $this->cmd(base64_encode($p), [235]);
                $this->log[] = '认证成功（LOGIN）';
                return;
            } catch (Throwable $e) {
                if ($caps === '' || strpos($caps,'PLAIN') === false) throw $e;
            }
        }
        if (strpos($caps,'PLAIN') !== false) {
            $this->cmd('AUTH PLAIN '.base64_encode("\0{$u}\0{$p}"), [235]);
            $this->log[] = '认证成功（PLAIN）';
            return;
        }
        throw new Exception('服务器无可用认证方式');
    }
    public function send(string $from, array $rcpts, string $raw): array {
        $this->cmd('MAIL FROM:<'.$from.'>', [250]);
        $acc = []; $rej = [];
        foreach ($rcpts as $r) {
            try { $this->cmd('RCPT TO:<'.$r.'>', [250,251,252]); $acc[] = $r; }
            catch (Throwable $e) { $rej[$r] = $e->getMessage(); $this->log[] = "收件人 {$r} 被拒"; }
        }
        if (!$acc) throw new Exception('所有收件人被拒：'.json_encode($rej, JSON_UNESCAPED_UNICODE));

        $this->cmd('DATA', [354]);
        $raw = str_replace(["\r\n","\r"], "\n", $raw);
        $raw = preg_replace('/^\./m', '..', $raw);
        $data = str_replace("\n", "\r\n", rtrim($raw, "\n"))."\r\n.\r\n";
        $this->log[] = 'C: [正文 '.strlen($raw).' 字节]';
        if (@fwrite($this->fp, $data) === false) throw new Exception('发送正文失败');
        $r = $this->read();
        $this->log[] = 'S: '.trim($r);
        $code = (int)substr(trim($r),0,3);
        if ($code !== 250) throw new Exception("正文被拒 [{$code}]：".trim($r));
        return ['accepted'=>$acc, 'rejected'=>$rej];
    }
    public function quit(): void {
        if ($this->fp) { @fwrite($this->fp,"QUIT\r\n"); @fclose($this->fp); $this->fp = null; }
    }
}

/* ---------------- 组装 MIME ---------------- */
function buildMessage(array $o): string {
    $eol  = "\r\n";
    $from = $o['from'];
    $p    = strrpos($from, '@');
    $domain = $p !== false ? substr($from, $p+1) : 'localhost';

    $h   = [];
    $h[] = 'Date: '.date('r');
    $h[] = 'From: '.($o['from_name'] !== '' ? mimeEnc($o['from_name'])." <{$from}>" : $from);
    $h[] = 'To: '.implode(', ', $o['to']);
    if ($o['cc']) $h[] = 'Cc: '.implode(', ', $o['cc']);
    $h[] = 'Subject: '.mimeEnc($o['subject']);
    $h[] = 'Message-ID: <'.bin2hex(random_bytes(10)).'.'.time().'@'.$domain.'>';
    $h[] = 'MIME-Version: 1.0';
    $h[] = 'X-Mailer: PHP-MailAPI/3.0';
    $type = $o['is_html'] ? 'text/html' : 'text/plain';
    $h[] = "Content-Type: {$type}; charset=UTF-8";
    $h[] = 'Content-Transfer-Encoding: base64';
    $body = chunk_split(base64_encode($o['body']), 76, $eol);
    return implode($eol, $h).$eol.$eol.$body;
}

/* ==================== 主流程 ==================== */
$json = ($rawBody !== '' && $rawBody !== false) ? (json_decode($rawBody, true) ?: []) : [];
$in = array_merge($_GET, $_POST, $json);

/* ---- 多密钥校验 ---- */
if (API_TOKENS) {
    $tk = (string)($in['token'] ?? $_SERVER['HTTP_X_API_TOKEN'] ?? '');
    $passed = false;
    foreach (API_TOKENS as $valid) {
        if ($valid !== '' && hash_equals($valid, $tk)) { $passed = true; break; }
    }
    if (!$passed) out(false, '接口密钥无效');
}

rateLimit(clientIp());

$mode     = strtolower(trim((string)($in['mode'] ?? 'smtp')));
$host     = trim((string)($in['host'] ?? $in['smtp_host'] ?? ''));
$port     = (int)($in['port'] ?? 0);
$secure   = strtolower(trim((string)($in['secure'] ?? $in['encryption'] ?? '')));
$user     = trim((string)($in['username'] ?? $in['user'] ?? ''));
$pass     = (string)($in['password'] ?? $in['pass'] ?? '');
$from     = trim((string)($in['from'] ?? $in['from_email'] ?? ''));
$fromName = trim((string)($in['from_name'] ?? ''));
$to       = parseList($in['to'] ?? '');
$cc       = parseList($in['cc'] ?? '');
$bcc      = parseList($in['bcc'] ?? '');
$subject  = (string)($in['subject'] ?? $in['title'] ?? '(无主题)');
$body     = (string)($in['body'] ?? $in['content'] ?? '');
$isHtml   = !empty($in['is_html']) && $in['is_html'] !== '0';

if (!$to) out(false, '缺少收件人 to');
if ($from === '' || !isEmail($from)) {
    $d = parse_url('http://'.($_SERVER['HTTP_HOST'] ?? 'localhost'), PHP_URL_HOST) ?: 'localhost';
    $from = 'noreply@'.$d;
}

/* ---- 只有 mode=direct / mode=host 才走直投，其它一律 smtp ---- */
$isDirect = in_array($mode, ['direct', 'host'], true);
$mode     = $isDirect ? 'direct' : 'smtp';

if ($secure === '') {
    if ($port === 465)     $secure = 'ssl';
    elseif ($port === 587) $secure = 'tls';
    else                   $secure = 'none';
}
if ($secure === 'starttls') $secure = 'tls';
if (!in_array($secure, ['ssl','tls','none'], true)) $secure = 'none';
if ($port <= 0) $port = $secure === 'ssl' ? 465 : ($secure === 'tls' ? 587 : 25);

$p    = strrpos($from, '@');
$helo = DIRECT_HELO !== '' ? DIRECT_HELO : ($p !== false ? substr($from, $p+1) : 'localhost');

$msg = buildMessage([
    'from'=>$from, 'from_name'=>$fromName,
    'to'=>$to, 'cc'=>$cc, 'bcc'=>$bcc,
    'subject'=>$subject, 'body'=>$body, 'is_html'=>$isHtml,
]);

try {
    /* ============ ① SMTP 认证发信（默认） ============ */
    if ($mode === 'smtp') {
        if ($host === '') throw new Exception('SMTP 模式需要 host');
        if (ALLOWED_HOSTS) {
            $a = array_map('strtolower', ALLOWED_HOSTS);
            if (!in_array(strtolower($host), $a, true)) throw new Exception('SMTP 主机不在白名单');
        }
        $c = new Smtp($host, $port, $secure, CONNECT_TIMEOUT);
        $c->connect();
        $c->ehlo($helo);
        if ($secure === 'tls') {
            if (!$c->hasCap('STARTTLS')) throw new Exception('服务器不支持 STARTTLS，请改用 ssl');
            $c->startTls();
            $c->ehlo($helo);
        }
        if ($user !== '') $c->auth($user, $pass);
        $res = $c->send($from, array_merge($to,$cc,$bcc), $msg);
        $c->quit();
        out(true, 'SMTP 发送成功', [
            'mode'=>'smtp',
            'accepted'=>$res['accepted'],
            'rejected'=>$res['rejected'],
            'log'=>$c->log,
        ]);
    }

    /* ============ ② 本机直投（mode=direct 或 mode=host） ============ */
    $groups = [];
    foreach (array_merge($to,$cc,$bcc) as $r) {
        $q = strrpos($r, '@');
        if ($q !== false) $groups[substr($r, $q+1)][] = $r;
    }
    if (!$groups) throw new Exception('无有效收件人');

    $log = []; $acc = []; $rej = []; $lastErr = null;
    foreach ($groups as $domain => $rcpts) {
        $mxs = resolveMx((string)$domain);
        $log[] = "解析 {$domain} MX → ".implode(', ', $mxs);
        $ok = false;
        foreach ($mxs as $mx) {
            try {
                $c = new Smtp($mx, 25, 'none', CONNECT_TIMEOUT);
                $c->connect();
                $c->ehlo($helo);
                $res = $c->send($from, $rcpts, $msg);
                $c->quit();
                $acc = array_merge($acc, $res['accepted']);
                foreach ($res['rejected'] as $k=>$v) $rej[$k] = $v;
                $log[] = "✓ {$domain} 经由 {$mx} 投递成功";
                $ok = true; break;
            } catch (Throwable $e) {
                $lastErr = $e->getMessage();
                $log[] = "✗ {$domain} 经由 {$mx} 失败：{$lastErr}";
            }
        }
        if (!$ok) foreach ($rcpts as $r) $rej[$r] = $lastErr ?: '直投失败';
    }
    if (!$acc) {
        throw new Exception('直投全部失败。常见原因：25 端口出站被封 / 无 PTR 反解 / SPF 不匹配 / 收件方拒收。最后错误：'.($lastErr ?: '无'));
    }
    out(true, '直投成功', [
        'mode'=>'direct',
        'accepted'=>$acc,
        'rejected'=>$rej,
        'log'=>$log,
    ]);

} catch (Throwable $e) {
    out(false, $e->getMessage(), ['mode'=>$mode]);
}
