<?php
/*
 * 邮件代发 API 发信插件配置
 */
return [
    'token' => [
        'title' => 'API 密钥',
        'type'  => 'text',
        'value' => '',
        'tip'   => '',
    ],
    'host' => [
        'title' => 'SMTP 主机名',
        'type'  => 'text',
        'value' => '',
        'tip'   => '',
    ],
    'port' => [
        'title' => 'SMTP 端口',
        'type'  => 'text',
        'value' => '',
        'tip'   => '',
    ],
    'username' => [
        'title' => 'SMTP 用户名',
        'type'  => 'text',
        'value' => '',
        'tip'   => '',
    ],
    'password' => [
        'title' => 'SMTP 密码 / 授权码',
        'type'  => 'text',
        'value' => '',
        'tip'   => '',
    ],
    'smtpsecure' => [
        'title'   => '发信方式',
        'type'    => 'select',
        'options' => [
            ''    => '无',
            'ssl' => 'ssl',
            'tls' => 'tls',
        ],
        'value'   => '',
        'tip'     => '',
    ],
    'systememail' => [
        'title' => '发件人邮箱',
        'type'  => 'text',
        'value' => '',
        'tip'   => '',
    ],
    'fromname' => [
        'title' => '系统邮件名',
        'type'  => 'text',
        'value' => '',
        'tip'   => '',
    ],
];
