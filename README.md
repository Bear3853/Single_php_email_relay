# single_php_email_relay

> 丢进任意 PHP 空间就能用的邮件代发 API。
> 一个文件，同时支持 **SMTP 认证发信** 与 **本机 MX 直投**，自带 Token 鉴权、限流与反爬跳转。
> 不挑环境：VPS、独立服务器、虚拟主机、宝塔面板、NAS、树莓派——只要有 PHP，就能跑。
> 直投模式不需要任何 SMTP 账号、密码或授权码，直接以发件人身份把邮件投递到收件方的 MX 服务器。

![PHP](https://img.shields.io/badge/PHP-7.4%20~%208.4-777BB4?logo=php&logoColor=white)
![License](https://img.shields.io/badge/License-GPL--3.0-blue)
![Single File](https://img.shields.io/badge/Single--File-Yes-blue)
![No Composer](https://img.shields.io/badge/No-Composer-success)
![No Database](https://img.shields.io/badge/No-Database-success)

---

## 💡 为什么用它

- **🌍 任意 PHP 环境可跑**：不依赖 Composer、不依赖框架、不依赖数据库、不依赖 Redis、不依赖任何 PECL 扩展。**只要你的虚拟主机支持 PHP，就能直接上传运行**。
- **🔓 绕过 `mail()` 函数**：很多虚拟主机、空间商默认禁用 PHP 原生 `mail()`，或者 `sendmail_path` 指向空导致发信失败。本项目自带 SMTP 客户端，**完全不依赖 `mail()`**，走 socket 直连，行为可预期。
- **📮 双模式，按环境选**
  - **SMTP 模式**：需要 SMTP 账号和授权码，走 465/587 端口，几乎所有虚拟主机都放行，兼容性最好
  - **直投模式**：**不需要任何 SMTP 账号、密码或授权码**，直接以发件人身份把邮件投递到收件方的 MX 服务器，省掉申请邮箱、生成授权码这一整套流程；前提是运行环境能出站访问 25 端口
- **🪶 极轻量**：整个文件 ~500 行，无外部依赖，加载快、内存占用极低。虚拟主机的几百 MB 内存限制完全够用。
- **🔌 一次部署，多系统共用**：魔方财务、Typecho、WordPress、Discuz、自研系统……只要能把「发邮件」变成「发 HTTP 请求」，就能接进来。

---

## ✨ 特性

- 🗂 **单文件交付**：复制 `mail.php` 到服务器即完成部署
- 📮 **双发送模式**
  - `smtp`（默认）：走任意 SMTP 服务器认证发信，最通用，兼容性最好
  - `direct` / `host`：直接查收件人域名 MX 并投递，**不需要 SMTP 账号**
- 🔑 **多 Token 鉴权**：可配置多个密钥，参数或 `X-Api-Token` 头任选其一
- 🚦 **IP 限流**：单 IP 每分钟请求数可配（默认不限）
- 🕵️ **反爬跳转**：无参数访问自动跳转到指定站点，降低接口被扫描概率
- 🌏 **完整 CORS**：开箱支持前端跨域调用（含 OPTIONS 预检）
- 🧩 **SMTP 白名单**：可限制只允许转发到指定 SMTP 主机，防止被当开放中继
- 📝 **结构化返回**：`accepted` / `rejected` / 逐行 SMTP 日志一目了然
- 🈶 **UTF-8 中文无忧**：主题、发件人名称、正文全部按 MIME 正确编码
- 🔌 **生态集成**：附赠智简魔方（魔方财务）邮件插件，见下方专章

---

## 🖥 环境适配一览

这是本项目**最核心的优势**：几乎你能想到的 PHP 环境，都能跑。

| 环境 | 是否可跑 | 说明 |
|---|---|---|
| **传统虚拟主机**（阿里云虚拟主机、景安、Hostinger、Namecheap 等） | ✅ **推荐** | 上传 `mail.php` 即可。 |
| **宝塔面板**（Linux/Windows） | ✅ | 创建站点，把 `mail.php` 扔进去即可 |
| **cPanel / DirectAdmin 虚拟主机** | ✅ | 上传到 `public_html` 下，PHP 版本 ≥7.4 即可 |
| **VPS / 独立服务器**（阿里云 ECS、腾讯云 CVM、AWS EC2 等） | ✅ | 注意：**多数云厂商默认封 25 端口出站**，直投模式需要先工单申请解封；SMTP 模式走 465/587 不受影响 |
| **NAS**（群晖、威联通） | ✅ | 自带的 Web Station 跑 PHP 即可 |
| **树莓派 / OpenWRT** | ✅ | 装个 php-fpm + nginx 就行 |
| **共享空间 / 免费空间**（InfinityFree 等） | ⚠️ 部分可跑 | 有些免费空间禁用 `stream_socket_client`，需先测 |
| **Windows IIS + PHP** | ✅ SMTP 模式 | 直投依赖 `getmxrr()`，Windows 下没有，会自动回退为直连域名本身，成功率较低 |
| **Serverless**（Cloudflare Workers、Vercel、Netlify） | ❌ | 这些平台不是 PHP 运行时，无法直接跑。可考虑改用其它语言重写 |

> **PHP ≥ 7.4 + 能出站访问目标端口**，就能跑。SMTP 模式一般用 465/587，直投模式用 25。

---

## 📦 环境要求

| 项目 | 要求 |
|---|---|
| PHP | **≥ 7.4**（推荐 7.4 / 8.0 / 8.1 / 8.2 / 8.3 / 8.4） |
| 扩展 | `openssl`（SSL/TLS 必需）、`json`（PHP 8 内置） |
| 依赖 | 无（不依赖 Composer / 框架 / 数据库 / Redis） |
| 权限 | 需可写 `sys_get_temp_dir()`（限流用；`MAX_PER_MINUTE=0` 时不需要） |
| 出站 | SMTP 模式：放行目标 SMTP 端口（通常 465/587）<br>直投模式：放行 **25 端口** |

### PHP 版本兼容速查

| 版本 | 兼容性 | 原因 |
|---|---|---|
| 5.6 / 7.0 – 7.3 | ❌ | 使用了类型化属性（7.4+）和箭头函数 `fn()`（7.4+） |
| **7.4** | ✅ **最低支持** | 全部语法特性满足 |
| 8.0 – 8.4 | ✅ **完全兼容** | 未使用 8.x 移除的 API，`stream_socket_*` 签名一致 |
| 8.5+ | ⚠️ 理论兼容，未实测 | 代码无已知废弃用法 |

> **Windows 说明**：直投依赖 `getmxrr()`，该函数在 Windows 下不存在，代码会自动回退为「直接连接收件人域名本身」，成功率较低。**Windows 建议一律使用 SMTP 模式。**

---

🏠 MNBT虚拟主机完美可用哦！

---

### 虚拟主机最快上手（3 步）

1. **FTP 上传**
2. **改常量**：用文本编辑器打开 `mail.php`，修改 `REDIRECT_URL` 和 `API_TOKENS`

**发信调用**：用 `curl` 或任何 HTTP 客户端 POST 到 `https://你的域名/mail.php`，参数见下方「请求参数」章节。

---

## 🚀 快速开始

### 1. 部署

```bash
# VPS / 有 SSH 的环境
git clone https://github.com/Bear3853/single_php_email_relay.git
# 把 mail.php 上传到你的 PHP 空间

# 虚拟主机 / 只有 FTP 的环境
# 直接把 mail.php 拖到 public_html 下即可
```

### 2. 配置

打开 `mail.php`，修改顶部常量：

```php
/* 无参访问时的跳转地址（反爬用），置空 '' 关闭跳转 */
const REDIRECT_URL = 'https://your-domain.com';

/* API 密钥列表，请求 token 命中任意一个即通过；留空数组 = 不校验 */
const API_TOKENS = [
    '<YOUR_TOKEN>',
    '<YOUR_TOKEN_2>',
];

const CONNECT_TIMEOUT = 20;   // SMTP 连接超时（秒）
const MAX_PER_MINUTE  = 30;   // 单 IP 每分钟上限，0 = 不限
const ALLOWED_HOSTS   = [];   // SMTP 主机白名单，空 = 不限
const DIRECT_HELO     = '';   // 直投 HELO 域名，留空自动取发件人域名
```

### 3. 验证

浏览器打开 `https://your-domain.com/mail.php`（不带参数）应跳转到 `REDIRECT_URL`，说明部署成功。

---

## ⚙️ 请求参数

所有参数可放 **Query String**、**form-data** 或 **JSON Body**，同名时优先级：`JSON > POST > GET`。

### 通用参数

| 参数 | 类型 | 必填 | 默认 | 说明 |
|---|---|---|---|---|
| `token` | string | 见下 | — | API 密钥；也可用请求头 `X-Api-Token` |
| `mode` | string | 否 | `smtp` | `smtp` / `direct` / `host`（后两者等价） |
| `from` / `from_email` | string | 否 | `noreply@当前域名` | 发件人地址，必须是合法邮箱 |
| `from_name` | string | 否 | 空 | 发件人显示名（支持中文，自动 MIME 编码） |
| `to` | string / array | **是** | — | 收件人，多个用逗号/分号/空格分隔，或直接传 JSON 数组 |
| `cc` | string / array | 否 | — | 抄送，格式同上 |
| `bcc` | string / array | 否 | — | 密送，格式同上 |
| `subject` / `title` | string | 否 | `(无主题)` | 邮件主题 |
| `body` / `content` | string | 否 | 空 | 邮件正文 |
| `is_html` | bool / int | 否 | `false` | `1` = HTML 正文，`0` = 纯文本。**建议只传 1 或 0** |

> ⚠️ `is_html` 判断逻辑是 `!empty() && !== '0'`，字符串 `"false"` 会被当作 `true`。请统一传 `1` / `0`。

> ⚠️ **当前版本不支持附件**：请求里的 `attachments` 字段会被忽略，正文正常发送，但附件不会出现在收件箱。

### SMTP 模式专属参数（`mode=smtp`，默认）

| 参数 | 必填 | 默认 | 说明 |
|---|---|---|---|
| `host` / `smtp_host` | **是** | — | SMTP 服务器地址，如 `smtp.qq.com` |
| `port` | 否 | 按 `secure` 推断 | SMTP 端口 |
| `secure` / `encryption` | 否 | 自动推断 | `ssl` / `tls`(STARTTLS) / `none` |
| `username` / `user` | 否 | — | SMTP 登录账号，为空则匿名投递 |
| `password` / `pass` | 否 | — | SMTP 密码 / 授权码 |

**端口与加密自动推断：**

| 端口 | 推断加密 |
|---|---|
| 465 | `ssl`（隐式 TLS） |
| 587 | `tls`（STARTTLS） |
| 其它 / 未填 | `none` |

### 直投模式参数（`mode=direct` / `mode=host`）

除通用参数外无需额外参数。服务器会：

1. 对每个收件人域名调用 `getmxrr()` 并按优先级排序；
2. 依次尝试连接 MX 的 **25 端口**，投递成功即止。

> 💡 **直投模式的最大特点**：**不需要任何 SMTP 账号、密码或授权码**。它直接以 `from` 里填的发件人身份，把邮件投递到收件方域名的 MX 服务器上，整个链路里没有任何「登录邮箱」这个环节。代价是对发信 IP 的信誉要求更高（PTR 反解、SPF 记录、不能是动态 IP），且运行环境必须能出站访问 25 端口。

---

## 📤 返回格式

统一返回 JSON：

```jsonc
{
  "success": true,
  "message": "SMTP 发送成功",
  "mode": "smtp",
  "accepted": ["a@example.com", "b@example.com"],
  "rejected": {
    "bad@example.com": "SMTP 550：...收件人不存在"
  },
  "log": [
    "已连接 smtp.qq.com:465",
    "C: EHLO qq.com",
    "S: 250-...",
    "认证成功（LOGIN）",
    "C: [正文 1024 字节]"
  ]
}
```

失败时：

```json
{ "success": false, "message": "接口密钥无效", "mode": "smtp" }
```

---

## 🧪 调用示例

> 下面示例里的 `<YOUR_TOKEN>` 是占位符，替换成 `API_TOKENS` 里配置的真实值。

### ① SMTP 认证发信（最通用）

```bash
curl -X POST 'https://your-domain.com/mail.php' \
  -H 'Content-Type: application/json' \
  -H 'X-Api-Token: <YOUR_TOKEN>' \
  -d '{
    "host": "smtp.qq.com",
    "port": 465,
    "secure": "ssl",
    "username": "you@qq.com",
    "password": "SMTP授权码",
    "from": "you@qq.com",
    "from_name": "你的名字",
    "to": "a@example.com,b@example.com",
    "subject": "测试邮件",
    "body": "<h1>Hello</h1><p>这是一封测试邮件。</p>",
    "is_html": 1
  }'
```

### ② 使用 STARTTLS（587 端口）

```bash
curl -X POST 'https://your-domain.com/mail.php?token=<YOUR_TOKEN>' \
  -d 'host=smtp.gmail.com' \
  -d 'port=587' \
  -d 'secure=tls' \
  -d 'username=you@gmail.com' \
  -d 'password=应用专用密码' \
  -d 'from=you@gmail.com' \
  -d 'to=receiver@example.com' \
  -d 'subject=Hello' \
  -d 'body=纯文本正文'
```

### ③ 直投模式（不需要 SMTP 账号）

```bash
curl -X POST 'https://your-domain.com/mail.php' \
  -H 'Content-Type: application/json' \
  -H 'X-Api-Token: <YOUR_TOKEN>' \
  -d '{
    "mode": "direct",
    "from": "noreply@your-domain.com",
    "to": "receiver@example.com",
    "subject": "直投测试",
    "body": "hi"
  }'
```

### ④ 前端 JavaScript（fetch）

```javascript
const res = await fetch('https://your-domain.com/mail.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-Api-Token': '<YOUR_TOKEN>',
  },
  body: JSON.stringify({
    host: 'smtp.example.com',
    port: 465,
    secure: 'ssl',
    username: 'user@example.com',
    password: 'password',
    from: 'user@example.com',
    to: ['a@example.com', 'b@example.com'],
    subject: '前端调用的邮件',
    body: '<b>Hello</b>',
    is_html: 1,
  }),
});

console.log(await res.json());
```

### ⑤ PHP 调用

```php
$payload = [
    'token'    => '<YOUR_TOKEN>',
    'host'     => 'smtp.qq.com',
    'port'     => 465,
    'secure'   => 'ssl',
    'username' => 'you@qq.com',
    'password' => 'SMTP授权码',
    'from'     => 'you@qq.com',
    'to'       => 'receiver@example.com',
    'subject'  => 'PHP 调用测试',
    'body'     => 'Hello',
    'is_html'  => 1,
];

$ctx = stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\n",
        'content' => json_encode($payload),
        'timeout' => 30,
    ],
]);

$resp = @file_get_contents('https://your-domain.com/mail.php', false, $ctx);
echo $resp;
```

### ⑥ Python 调用

```python
import requests

resp = requests.post(
    "https://your-domain.com/mail.php",
    headers={"X-Api-Token": "<YOUR_TOKEN>"},
    json={
        "host": "smtp.qq.com",
        "port": 465,
        "secure": "ssl",
        "username": "you@qq.com",
        "password": "SMTP授权码",
        "from": "you@qq.com",
        "to": "receiver@example.com",
        "subject": "Python 调用测试",
        "body": "Hello",
        "is_html": 1,
    },
    timeout=30,
)
print(resp.json())
```

---

## 🔌 生态集成：智简魔方（魔方财务）邮件插件 Njdf

本仓库同时附带一个**智简魔方（魔方财务）邮件插件** `NjdfPlugin.php`。装上之后，魔方财务系统发出的所有邮件（注册、找回密码、工单、订单通知等）都会通过 HTTP 转交给本服务代发。

```
┌────────────┐  HTTP / JSON   ┌────────────────────────┐   SMTP    ┌────────────┐
│ 魔方财务    │ ─────────────▶ │ single_php_email_relay │ ────────▶ │ 邮件服务器  │
│ NjdfPlugin │  X-Api-Token   │        mail.php        │  SSL/TLS  │            │
└────────────┘                └────────────────────────┘           └────────────┘
```

### 为什么需要它

智简魔方自带的 SMTP 发信会露源

插件把「连 SMTP」这件事从魔方财务主机上移走，交给一个能出网、能连 SMTP 的独立 relay 服务完成。**魔方财务只要能发 HTTP 请求就行。**

### 目录结构

```
public/
└── mail/
    └── plugin/
        └── njdf/
            └── NjdfPlugin.php
            └── config.php
```

插件命名空间为 `mail\njdf`，类名为 `NjdfPlugin`，`$info['name']` 为 `Njdf`，后台显示名称为「邮件代发」。这些值不要随意改，改了需要同步改目录结构和文件名。

### 安装步骤

1. **先改代码里的接口地址**：打开 `NjdfPlugin.php`，找到 `send()` 方法末尾这一行：

   ```php
   $apiUrl = !empty($config['api_url']) ? $config['api_url'] : 'https://your-domain.com/mail.php';
   ```

   把冒号后面的默认值改成你实际的 relay 地址。**后台没有接口地址配置项，这个地址只写在代码里**，不改这行插件就发不出去：

2. 把 `NjdfPlugin.php` 和 `config.php` 按上面的目录结构放到服务器

3. 登录魔方财务**管理后台**

4. 进入 **系统设置 → 邮件设置**（不同版本菜单名略有差异，可能叫「邮件配置」「邮件通知」）

5. 在邮件插件列表里找到插件，点击 **安装**

6. 安装完成后点击 **配置**，按下表填写（**只有 token 和 SMTP 参数，没有接口地址**）

7. 保存后，在邮件设置页用「**发送测试邮件**」验证

### 配置项说明

插件通过 `$params['config']` 读取以下字段（对应你在后台配置页里看到的输入框）：

| 配置项（后台字段） | 插件内字段名 | 示例 | 说明 |
|---|---|---|---|
| 接口密钥 | `token` | `<YOUR_TOKEN>` | 必须与 `mail.php` 中 `API_TOKENS` 的某一项**完全一致** |
| SMTP 服务器 | `host` | `smtp.qq.com` | 真正发信的 SMTP 服务器 |
| SMTP 端口 | `port` | `465` | 465=SSL，587=STARTTLS，25=明文 |
| 加密方式 | `smtpsecure` | `ssl` | `ssl` / `tls` / `none` |
| SMTP 账号 | `username` | `you@qq.com` | 一般就是发件邮箱 |
| SMTP 密码 / 授权码 | `password` | `xxxxxxxx` | **不是登录密码**，是邮箱后台生成的 SMTP 授权码 |
| 发件人邮箱 | `systememail` | `you@qq.com` | 与 SMTP 账号一致，否则部分服务商会拒发 |
| 发件人名称 | `fromname` | `你的站点名` | 收件人看到的「发件人」名字，支持中文 |

> ⚠️ **接口地址不在后台配置**。插件后台没有「接口地址」这一项，`$config['api_url']` 永远读不到，永远走 `NjdfPlugin.php` 里写死的默认值。改接口地址只能改代码，见「安装步骤」第 1 步。

### 参数映射（插件 → relay）

插件里 `send()` 方法组装 JSON，字段对应关系如下，方便你排查：

| 插件字段 | 转发到 relay 的 JSON 字段 |
|---|---|
| `config['token']` | `token`（同时放 `X-Api-Token` 请求头） |
| `config['host']` | `host` |
| `config['port']` | `port` |
| `config['smtpsecure']` | `secure` |
| `config['username']` | `username` |
| `config['password']` | `password` |
| `config['systememail']` | `from` |
| `config['fromname']` | `from_name` |
| `$params['email']` | `to` |
| `$params['subject']` | `subject` |
| `$params['content']` | `body` |
| —（固定） | `is_html` = `1` |

插件 `parseResponse()` 会识别 relay 返回的 `success` 字段：`true` 视为发送成功，`false` 时把 `message` 字段回传给魔方财务后台显示。

### 附件说明

插件会把 `$params['attachments']` 里的附件读取出来、base64 编码后放进 `attachments` 数组转发。但**当前版本的 `mail.php` 不解析 `attachments` 字段**，所以：

- ✅ **邮件正文、标题、收件人正常送达**
- ❌ **附件不会随邮件发出**（收件人只收到正文）

如果业务必须带附件（如账单 PDF、工单截图），需要：
1. 自行扩展 `mail.php` 的 MIME 组装部分，改为 `multipart/mixed`；或者
2. 不使用本插件，直接用魔方财务自带的原生 SMTP 发信（前提是网络能通）

### 调试与排查

**第 1 步：确认 relay 本身能发信**

绕过魔方财务，先用 `curl` 按「调用示例 ①」直接 POST 一次 `mail.php`。能收到测试邮件，说明 relay 正常，问题在插件或网络。

**第 2 步：确认魔方财务能访问 relay**

在魔方财务服务器上执行：

```bash
curl -X POST 'https://your-domain.com/mail.php' \
  -H 'Content-Type: application/json' \
  -H 'X-Api-Token: <YOUR_TOKEN>' \
  -d '{"to":"test@example.com","subject":"ping","body":"ping"}'
```

- 返回 `{"success":true,...}`：网络通、token 对，插件配置八成没问题
- 返回 `{"success":false,"message":"接口密钥无效"}`：token 填错
- 连接超时 / 502：魔方财务服务器到 relay 之间的网络问题，或被防火墙拦

**第 3 步：看 relay 返回的 `log` 字段**

relay 的 JSON 里 `log` 是逐行 SMTP 对话记录。魔方财务的插件通常把错误信息显示在后台，如果显示不完整，可以在 `NjdfPlugin.php` 的 `request()` 方法里临时 `error_log($response)`，然后看魔方财务的 PHP 错误日志。

**第 4 步：常见错误对照表**

| 现象 | 原因 | 处理 |
|---|---|---|
| 后台显示「接口密钥无效」 | `token` 填错 / 前后有空格 | 重新复制 `API_TOKENS` 里的值 |
| 后台显示「SMTP 模式需要 host」 | `host` 没填 | 补齐 SMTP 服务器地址 |
| 后台显示「SMTP 535 Authentication failed」 | SMTP 密码不是授权码 / 账号密码不对 | 去邮箱后台重新生成授权码 |
| 后台显示「SMTP 550 ...」 | 收件人地址不存在 / 被收件方拒收 | 换收件地址测试 |
| 后台显示「接口返回状态码：404」 | 插件里写死的接口地址与实际部署不符 | 改 `NjdfPlugin.php` 里 `$apiUrl` 默认值，首页用 `/`，子文件用 `/mail.php` |
| 后台显示「接口返回状态码：500」 | relay 主机上 `mail.php` 报 PHP 错误 | 看 relay 主机 PHP 错误日志 |
| 后台显示「接口请求失败：Could not resolve host」 | 魔方财务服务器 DNS 有问题 | 改用 relay 的 IP 地址，或在 `/etc/hosts` 写死 |
| 后台显示「接口请求失败：SSL certificate problem」 | relay 用了自签 HTTPS 证书 | 换正式证书（Let's Encrypt 免费），或临时把 `NjdfPlugin.php` 里 `CURLOPT_SSL_VERIFYPEER` 设为 `false` |
| 发送成功但收不到 | 进了垃圾箱 / 附件丢失 / SMTP 服务商拦截 | 让收件人查垃圾箱；确认没依赖附件；换发信服务器测试 |

### 与魔方财务原有 SMTP 配置的关系

插件工作方式与魔方财务原生 SMTP 发信**互斥**：启用 Njdf 插件后，魔方财务不再自己连 SMTP，只向 relay 发 HTTP 请求。因此：

- 插件配置里的 SMTP 字段仍然要填**真实的 SMTP 账号密码**，因为真正的 SMTP 认证是在 relay 上发生的
- 魔方财务原生的 SMTP 配置可以保留也可以清空，插件不读它
- 想切回原生 SMTP，把邮件插件切回「SMTP」即可，互不影响

### 多站点共用

一个 relay 可以同时服务多个魔方财务站点（或者任何其它通过 HTTP 调用的系统）：

- 给每个站点在 `API_TOKENS` 里配一个**独立的 token**，方便单独吊销
- 不同站点可以用不同的 SMTP 账号（请求里各自传自己的 `host` / `username` / `password`）
- 如果想固定 SMTP 账号（对租户隐藏密码），可在 `mail.php` 主流程里把 `host` / `username` / `password` 改成读环境变量，插件里就不传这些字段了——但这样需要你自行做好多租户隔离，README 不展开

---

## 🔒 安全建议

1. **替换掉所有占位符**：`<YOUR_TOKEN>`、`https://your-domain.com` 在部署前必须换成真实值；不需要公网使用时把 `API_TOKENS` 设成 `[]` 并配合 IP 白名单
2. **强制 HTTPS**：SMTP 密码在请求体中明文传输，必须走 HTTPS。虚拟主机如果带免费 SSL（Let's Encrypt / cPanel AutoSSL），务必开启
3. **配置 `ALLOWED_HOSTS`**：只允许转发到信任的 SMTP 主机，避免被拿去发垃圾邮件导致 IP 被拉黑
4. **设置 `MAX_PER_MINUTE`**：建议 10–60，防止接口被刷。虚拟主机如果无法写临时文件，可保持 `0`
5. **设置 `REDIRECT_URL`**：让无参数访问跳转到正常页面，降低被扫描器发现的概率
6. **限流文件位置**：默认写入 `sys_get_temp_dir()/mailapi_rl/`，如介意请改路径或关闭限流
7. **插件侧证书校验**：`NjdfPlugin.php` 里 `CURLOPT_SSL_VERIFYPEER` 默认是 `false`，正式部署建议改成 `true` 并要求 relay 使用正规 HTTPS 证书

---

## ❓ FAQ

**Q1：直投模式失败，提示「连接超时」或「Connection refused」？**
A：通常是运行环境**封禁了 25 端口出站**（很多云厂商和部分虚拟主机默认封）。请改用 SMTP 模式走 465/587，或工单申请解封 25。虚拟主机用户请先跑「虚拟主机场景专题」里的端口测试代码。

**Q2：SMTP 模式报「证书验证失败」？**
A：代码默认 `verify_peer=true`。自建 SMTP（如 Postfix + 自签证书）需在 `Smtp::connect()` 中调整 `verify_peer` / `allow_self_signed`，或给服务器换 Let's Encrypt 正式证书。

**Q3：Gmail / QQ 邮箱提示「需要应用专用密码」？**
A：主流邮箱开启 SMTP 都要求使用**授权码 / 应用专用密码**，不是登录密码。请到邮箱设置中生成。

**Q4：直投模式进垃圾箱怎么办？**
A：直投对发信 IP 信誉要求极高，至少要具备：
- 固定公网 IP + 正确的 **PTR 反解**
- **SPF** 记录包含该 IP
- **DKIM** 签名（本代码未内置，需自行在 MTA 层做）
- **DMARC** 记录

多数场景建议直接用 SMTP 模式。

**Q5：返回「接口密钥无效」？**
A：确认 `API_TOKENS` 中确实包含该 token，且请求头 `X-Api-Token` 拼写正确、无多余空格。

**Q6：一个收件人格式错误，会整体失败吗？**
A：不会。`to` / `cc` / `bcc` 中不合法的地址会被静默过滤；SMTP 阶段被拒的收件人会出现在 `rejected` 中，其余继续投递。

**Q7：中文主题乱码？**
A：代码已对非 ASCII 内容做 `=?UTF-8?B?...?=` 编码。若仍乱码，检查收件客户端是否按 UTF-8 解析。

**Q8：魔方财务插件装上后后台发信报错，从哪查？**
A：见「调试与排查」章节，按 1→2→3 顺序，先绕开插件测 relay，再测网络，最后看 relay 返回的 `log`。

**Q9：虚拟主机 / 空间商禁用了 `stream_socket_client` 怎么办？**
A：本项目无法工作。请换空间商，或升级到 VPS。跑「虚拟主机场景专题」里的测试代码确认。

**Q10：虚拟主机只能 FTP 上传，没有 SSH，能用吗？**
A：完全可以。整个项目只有一个 `mail.php`，FTP 拖上去，改两行常量，浏览器打开就能验证。

**Q11：SMTP 模式和直投模式怎么选？**
A：
- **有现成的 SMTP 账号（QQ / 163 / Gmail / 企业邮箱）** → 用 SMTP 模式，最稳，进垃圾箱概率最低
- **不想申请邮箱 / 不想填授权码 / 想省掉认证环节** → 用直投模式，前提是运行环境能出站访问 25 端口，且发信 IP 有 PTR 反解和 SPF 记录
- **虚拟主机空间商封了 25 出站** → 直投用不了，只能 SMTP 模式

---

## 📋 已知限制

- **不支持附件**（MIME 为单 part），魔方财务插件转发过来的附件会被丢弃
- 不支持 DKIM 签名（直投模式易进垃圾箱）
- 直投模式仅尝试 25 端口，不支持 587
- 限流为单机文件计数，多实例部署不共享
- 插件目前只针对智简魔方（魔方财务）体系，其它系统的接入请直接参考 HTTP 调用示例
- 无法运行在非 PHP 环境（Serverless 平台如 Cloudflare Workers 不支持）

---

## 🗓 附录：兼容 PHP 7.0 – 7.3（可选）

若你的空间仍是 7.0–7.3，需改动三处：

1. **删除类属性类型声明**
   ```php
   // 原
   private string $host, $secure;
   private int $port, $timeout;
   // 改为
   private $host, $secure, $port, $timeout;
   ```

2. **箭头函数改闭包**
   ```php
   // 原：array_filter($hits, fn($t) => (int)$t > $now - 60)
   // 改：array_filter($hits, function ($t) use ($now) { return (int)$t > $now - 60; })
   ```
   `usort` 内的 `fn` 同理改为闭包。

3. **去掉函数调用的尾随逗号**（PHP 7.3 前不允许）

改完后最低可跑在 **PHP 7.0**。不建议再往下降（5.6 不支持 `??`、`Throwable`、`random_bytes`、`hash_equals` 的部分用法）。

---

## 📄 License

本项目采用 **GNU General Public License v3.0（GPL-3.0）** 发布。

- ✅ 可自由使用、修改、商用、再分发
- ✅ 无需支付费用，无需事先授权
- ⚠️ 若**修改后分发**（对外发布、随产品交付等），衍生作品必须以 GPL-3.0 兼容协议开源并提供完整源码
- ⚠️ 不提供任何担保

完整条款见仓库根目录 [`LICENSE`](./LICENSE) 文件。

## 🙏 致谢

如果这个项目帮你省了事，点个 ⭐ Star 就是最大的支持。
