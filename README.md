# single_php_email_relay

> 单文件 PHP 邮件代发 API —— 一个文件，同时支持 **SMTP 认证发信** 与 **本机 MX 直投**，自带 Token 鉴权、限流与反爬跳转。无需 Composer、无需框架，丢进任意 PHP 空间即可对外提供邮件接口。

![PHP](https://img.shields.io/badge/PHP-7.4%20~%208.4-777BB4?logo=php&logoColor=white)
![License](https://img.shields.io/badge/License-GPL--3.0-blue)
![Single File](https://img.shields.io/badge/Single--File-Yes-blue)

---

## ✨ 特性

- 🗂 **单文件交付**：复制 `mail.php` 到服务器即完成部署
- 📮 **双发送模式**
  - `smtp`（默认）：走任意 SMTP 服务器认证发信，最通用，可绕过云厂商 25 端口封锁
  - `direct` / `host`：直接查收件人域名 MX，向 25 端口投递，**无需 SMTP 账号**
- 🔑 **多 Token 鉴权**：可配置多个密钥，参数或 `X-Api-Token` 头任选其一
- 🚦 **IP 限流**：单 IP 每分钟请求数可配（默认不限）
- 🕵️ **反爬跳转**：无参数访问自动跳转到指定站点，降低接口被扫描概率
- 🌏 **完整 CORS**：开箱支持前端跨域调用（含 OPTIONS 预检）
- 🧩 **SMTP 白名单**：可限制只允许转发到指定 SMTP 主机，防止被当开放中继
- 📝 **结构化返回**：`accepted` / `rejected` / 逐行 SMTP 日志一目了然
- 🈶 **UTF-8 中文无忧**：主题、发件人名称、正文全部按 MIME 正确编码

---

## 📦 环境要求

| 项目 | 要求 |
|---|---|
| PHP | **≥ 7.4**（推荐 7.4 / 8.0 / 8.1 / 8.2 / 8.3 / 8.4） |
| 扩展 | `openssl`（SSL/TLS 必需）、`json`（PHP 8 内置） |
| 依赖 | 无（不依赖 Composer / 框架 / 数据库） |
| 权限 | 需可写 `sys_get_temp_dir()`（限流用；`MAX_PER_MINUTE=0` 时不需要） |
| 网络 | SMTP 模式需能出站访问目标 SMTP 端口；直投模式需能出站访问 **25 端口** |

### PHP 版本兼容速查

| 版本 | 兼容性 | 原因 |
|---|---|---|
| 5.6 / 7.0 – 7.3 | ❌ | 使用了类型化属性（7.4+）和箭头函数 `fn()`（7.4+） |
| **7.4** | ✅ **最低支持** | 全部语法特性满足 |
| 8.0 – 8.4 | ✅ **完全兼容** | 未使用 8.x 移除的 API，`stream_socket_*` 签名一致 |
| 8.5+ | ⚠️ 理论兼容，未实测 | 代码无已知废弃用法 |

> **Windows 说明**：直投依赖 `getmxrr()`，该函数在 Windows 下不存在，代码会自动回退为「直接连接收件人域名本身」，成功率较低。**Windows 建议一律使用 SMTP 模式。**

---

## 🚀 快速开始

### 1. 部署

```bash
git clone https://github.com/<your-name>/single_php_email_relay.git
# 把 mail.php 上传到你的 PHP 空间，例如 /var/www/html/mail.php
```

### 2. 配置

打开 `mail.php`，修改顶部常量：

```php
/* 无参访问时的跳转地址（反爬用），置空 '' 关闭跳转 */
const REDIRECT_URL = 'http://www.baidu.com';

/* API 密钥列表，请求 token 命中任意一个即通过；留空数组 = 不校验 */
const API_TOKENS = [
    'password',
    'hello-world',
];

const CONNECT_TIMEOUT = 20;   // SMTP 连接超时（秒）
const MAX_PER_MINUTE  = 30;   // 单 IP 每分钟上限，0 = 不限
const ALLOWED_HOSTS   = [];   // SMTP 主机白名单，空 = 不限
const DIRECT_HELO     = '';   // 直投 HELO 域名，留空自动取发件人域名
```

> ⚠️ 上面 `password` / `hello-world` 仅为**示例**，开源前务必换成你自己生成的随机串：
>
> ```bash
> php -r "echo bin2hex(random_bytes(32));"
> ```

### 3. 验证

浏览器打开 `https://your-domain.com/mail.php`（不带参数）应跳转到 `http://www.baidu.com`，说明部署成功。

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

> 下面示例里的 `password` / `hello-world` 对应上面 `API_TOKENS` 的示例值，实际使用时换成你自己的。

### ① SMTP 认证发信（最通用）

```bash
curl -X POST 'https://your-domain.com/mail.php' \
  -H 'Content-Type: application/json' \
  -H 'X-Api-Token: password' \
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
curl -X POST 'https://your-domain.com/mail.php?token=hello-world' \
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

### ③ 直投模式（无需 SMTP 账号）

```bash
curl -X POST 'https://your-domain.com/mail.php' \
  -H 'Content-Type: application/json' \
  -H 'X-Api-Token: password' \
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
    'X-Api-Token': 'password',
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
    'token'    => 'hello-world',
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
    headers={"X-Api-Token": "password"},
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

## 🌐 生态集成：魔方财务插件（Njdf）

仓库同时开源了配套的**魔方财务邮件插件** `NjdfPlugin.php`，让魔方财务系统把发信任务交给本服务执行：

```
魔方财务 ──HTTP/JSON──> single_php_email_relay ──SMTP──> 邮件服务器
```

### 为什么需要它

魔方财务自带的 SMTP 发信在以下场景容易失败：

- 服务器所在云厂商**封禁 25 端口**出站
- 目标 SMTP 服务器**被墙 / 跨区域超时**
- 面板环境没有外网 DNS

把发信请求转发到一台能出网、能连 SMTP 的机器上处理，问题迎刃而解。

### 部署

1. 把 `NjdfPlugin.php` 放到：

```
app/mail/plugin/njdf/NjdfPlugin.php
```

2. 进后台 → **邮件设置** → 找到「南京代发」→ 安装
3. 配置字段：

| 字段 | 示例值 | 说明 |
|---|---|---|
| Relay API 地址 | `https://your-domain.com/mail.php` | 你的 `mail.php` 完整 URL |
| API 密钥 | `password` 或 `hello-world` | 与 `mail.php` 的 `API_TOKENS` 一致 |
| SMTP 主机 / 端口 / 加密 | `smtp.qq.com` / `465` / `ssl` | 与原来魔方财务的配置一致 |
| SMTP 账号 / 密码 | `you@qq.com` / `SMTP授权码` | 同上 |
| 发件人地址 / 名称 | `you@qq.com` / `你的站点名` | 邮件 `From:` 显示的内容 |

4. 保存后发一封测试邮件验证。

### 参数映射关系

| 插件字段 | 转发到 relay 的参数 |
|---|---|
| API 密钥 | `token`（同时以 `X-Api-Token` 头传） |
| SMTP 主机 | `host` |
| SMTP 端口 | `port` |
| 加密方式 | `secure` |
| SMTP 账号 | `username` |
| SMTP 密码 | `password` |
| 发件人地址 | `from` |
| 发件人名称 | `from_name` |
| 收件人 | `to` |
| 邮件标题 | `subject` |
| 邮件内容 | `body`（`is_html=1`） |

### ⚠️ 附件说明

插件会读取 `./upload/common/email/` 下的附件并作为 `attachments` 字段转发，但**当前版本的 `mail.php` 不解析 `attachments`**，因此：

- **正文、标题、收件人正常送达**
- **附件不会被发送**

如果业务必须带附件，需要自行扩展 `mail.php` 的 MIME 组装部分（改为 `multipart/mixed`），或改用支持附件的标准 SMTP 直接投递。

### 常见问题

**Q：填了 `direct` 模式一直失败？**
A：直投需 25 端口出站。几乎所有云厂商默认封禁 25，需工单解封，或改用 `smtp` 模式。

**Q：插件 `_config()` 后台空白 / 字段不显示？**
A：不同魔方财务版本字段格式略有差异，参照同目录下其他邮件插件调整 `_config()` 返回结构。

**Q：接口请求失败：SSL certificate problem？**
A：插件默认关闭了证书校验（`CURLOPT_SSL_VERIFYPEER=false`）。生产环境建议配置正式 HTTPS 证书并开启校验。

---

## 🔒 安全建议

1. **务必修改默认密钥**：仓库里的 `password` / `hello-world` 只是示例，上线前换成随机串；不需要公网使用时设成 `[]` 并配合 IP 白名单。
2. **强制 HTTPS**：SMTP 密码在请求体中明文传输，必须走 HTTPS。
3. **配置 `ALLOWED_HOSTS`**：只允许转发到信任的 SMTP 主机，避免被拿去发垃圾邮件导致 IP 被拉黑。
4. **设置 `MAX_PER_MINUTE`**：建议 10–60，防止接口被刷。
5. **设置 `REDIRECT_URL`**：示例里是 `http://www.baidu.com`，正式部署时建议换成你自己的站点或置空关闭。
6. **限流文件位置**：默认写入 `sys_get_temp_dir()/mailapi_rl/`，如介意请改路径或关闭限流。

---

## ❓ FAQ

**Q1：直投模式失败，提示「连接超时」或「Connection refused」？**
A：99% 是云厂商**封禁了 25 端口出站**（阿里云、腾讯云、AWS、GCP 默认全封）。请改用 SMTP 模式，或工单申请解封 25 端口。

**Q2：SMTP 模式报「证书验证失败」？**
A：代码默认 `verify_peer=true`。自建 SMTP（如 Postfix + 自签证书）需在 `Smtp::connect()` 中调整 `verify_peer` / `allow_self_signed`，或给服务器换 Let's Encrypt 正式证书。

**Q3：Gmail / QQ 邮箱提示「需要应用专用密码」？**
A：主流邮箱开启 SMTP 都要求使用**授权码 / 应用专用密码**，不是登录密码。请到邮箱设置中生成。

**Q4：直投进垃圾箱怎么办？**
A：直投对发信 IP 信誉要求极高，至少要具备：
- 固定公网 IP + 正确的 **PTR 反解**
- **SPF** 记录包含该 IP
- **DKIM** 签名（本代码未内置，需自行在 MTA 层做）
- **DMARC** 记录

多数场景建议直接用 SMTP 模式，交给专业邮件服务商。

**Q5：返回「接口密钥无效」？**
A：确认 `API_TOKENS` 中确实包含该 token，且请求头 `X-Api-Token` 拼写正确、无多余空格。

**Q6：收件人里有一个格式错误，会整体失败吗？**
A：不会。`to` / `cc` / `bcc` 中不合法的地址会被静默过滤；SMTP 阶段被拒的收件人会出现在 `rejected` 中，其余继续投递。

**Q7：中文主题乱码？**
A：代码已对非 ASCII 内容做 `=?UTF-8?B?...?=` 编码，正常不会乱码。若仍乱码，检查收件客户端是否按 UTF-8 解析。

---

## 📋 已知限制

- **不支持附件**（MIME 为单 part）
- 不支持 DKIM 签名（直投模式易进垃圾箱）
- 直投模式仅尝试 25 端口，不支持 587
- 限流为单机文件计数，多实例部署不共享

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

> **「自用」**：GPL-3.0 的开源义务只在**分发（distribution）**时触发。自己用、公司内部用、私有部署改代码，**没有**公开义务。
>
> **「HTTP 调用」**：本项目是独立部署的 Web API。第三方通过 HTTP 调用 `mail.php` 发信属于**独立程序间的网络调用**，**不构成衍生作品**，无需开源其自身代码。（若要覆盖 SaaS 场景，需改用 AGPL-3.0。）

完整条款见仓库根目录 [`LICENSE`](./LICENSE) 文件。

## 🙏 致谢

如果这个项目帮你省了事，点个 ⭐ Star 就是最大的支持。
