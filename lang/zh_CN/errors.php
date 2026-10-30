<?php

return [
    'unreachable' => '无法从面板连接到 :host。请检查面板服务器是否可以访问互联网。',
    'invalid_key' => ':company 的 API 密钥无效。管理员可以在 Explain 设置中输入新密钥。',
    'no_credit' => '此密钥对应的 :company 账户余额不足。管理员可以在 :company 充值。',
    'model_not_allowed' => '此 :company 密钥无权使用所选模型。管理员可以在 Explain 设置中选择其他模型。',
    'model_missing' => '所选的 :company 模型对此密钥不可用。管理员可以在 Explain 设置中选择其他模型。',
    'busy' => ':company 目前对此密钥繁忙。请一分钟后重试。',
    'unavailable' => ':company 暂时不可用。请一分钟后重试。',
    'failed' => ':company 无法回答这个问题（错误 :status）。请稍后重试。',
    'server_unreachable' => '面板无法连接到 :host 上的 :company 服务器。请检查 Explain 设置中的地址，并确认 :company 正在运行。',
    'server_model_missing' => '你的 :company 服务器上没有这个模型。请用 "ollama pull" 下载，或在 Explain 设置中填写其他模型。',
];
