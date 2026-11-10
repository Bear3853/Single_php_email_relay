<?php
namespace mail\njdf;

use app\admin\lib\Plugin;

class NjdfPlugin extends Plugin
{
    # 基础信息
    public $info = array(
        'name'        => 'Njdf',
        'title'       => '南京代发',
        'description' => '通过自建南京 API 接口发送邮件',
        'status'      => 1,
        'author'      => '小熊被',
        'version'     => '1.0',
        'help_url'    => 'https://njdf.mxzc.top',
    );

    const ATTACHMENTS_ADDRESS = './upload/common/email/';

    public function install()
    {
        return true;
    }

    public function uninstall()
    {
        return true;
    }

    public function send($params)
    {
        $config = $params['config'];

        $data = [
            'token'     => isset($config['token']) ? $config['token'] : '',
            'host'      => $config['host'],
            'port'      => $config['port'],
            'secure'    => $config['smtpsecure'],
            'username'  => $config['username'],
            'password'  => $config['password'],
            'from'      => $config['systememail'],
            'from_name' => $config['fromname'],
            'to'        => $params['email'],
            'subject'   => $params['subject'],
            'body'      => $params['content'],
            'is_html'   => 1,
        ];

        if (!empty($params['attachments'])) {
            $attachments = explode(',', $params['attachments']);
            $files = [];
            foreach ($attachments as $attachment) {
                $tmp = explode('^', $attachment);
                if (count($tmp) < 2) continue;
                $originalName = $tmp[1];
                $path = self::ATTACHMENTS_ADDRESS . $attachment;
                if (is_file($path)) {
                    $files[] = [
                        'name'    => $originalName,
                        'content' => base64_encode(file_get_contents($path)),
                    ];
                }
            }
            if (!empty($files)) {
                $data['attachments'] = $files;
            }
        }

        $apiUrl = !empty($config['api_url']) ? $config['api_url'] : 'https://njdf.mxzc.top/';

        return $this->request($apiUrl, $data);
    }

    private function request($url, $data)
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Api-Token: ' . $data['token'],
            ],
        ]);

        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        $error    = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            return ['status' => 'error', 'msg' => '接口请求失败：' . $error];
        }

        if ($httpCode != 200) {
            return [
                'status' => 'error',
                'msg'    => '接口返回状态码：' . $httpCode . '，响应：' . $this->toUtf8($response),
            ];
        }

        return $this->parseResponse($response);
    }

    private function parseResponse($response)
    {
        $json = json_decode($response, true);

        if (!is_array($json)) {
            return ['status' => 'success'];
        }

        if (array_key_exists('code', $json)) {
            $code = $json['code'];
            if ($code === 0 || $code === '0' || $code === 200 || $code === '200' || $code === true) {
                return ['status' => 'success'];
            }
            return ['status' => 'error', 'msg' => $this->getMsg($json)];
        }

        if (array_key_exists('success', $json)) {
            return $json['success']
                ? ['status' => 'success']
                : ['status' => 'error', 'msg' => $this->getMsg($json)];
        }

        if (array_key_exists('status', $json)) {
            $s = strtolower((string)$json['status']);
            if (in_array($s, ['success', 'ok', 'true', '1'], true)) {
                return ['status' => 'success'];
            }
            return ['status' => 'error', 'msg' => $this->getMsg($json)];
        }

        return ['status' => 'success'];
    }

    private function getMsg($json)
    {
        foreach (['msg', 'message', 'error', 'info'] as $key) {
            if (!empty($json[$key]) && is_string($json[$key])) {
                return $this->toUtf8($json[$key]);
            }
        }
        return '接口返回失败';
    }

    private function toUtf8($str)
    {
        if (!is_string($str) || $str === '') return $str;
        $encoding = mb_detect_encoding($str, ["ASCII", 'UTF-8', "GB2312", "GBK", 'BIG5']);
        if ($encoding && $encoding != "UTF-8") {
            $str = mb_convert_encoding($str, "UTF-8", $encoding);
        }
        return $str;
    }
}
