<?php

return [
    'unreachable' => '無法從面板連線到 :host。請檢查面板伺服器是否可以連上網際網路。',
    'invalid_key' => ':company 的 API 金鑰無效。管理員可以在 Explain 設定中輸入新金鑰。',
    'no_credit' => '此金鑰對應的 :company 帳戶已無餘額。管理員可以在 :company 儲值。',
    'model_not_allowed' => '此 :company 金鑰無權使用所選模型。管理員可以在 Explain 設定中選擇其他模型。',
    'model_missing' => '所選的 :company 模型無法用於此金鑰。管理員可以在 Explain 設定中選擇其他模型。',
    'busy' => ':company 目前對此金鑰忙碌中。請一分鐘後再試。',
    'unavailable' => ':company 暫時無法使用。請一分鐘後再試。',
    'failed' => ':company 無法回答這個問題（錯誤 :status）。請稍後再試。',
    'server_unreachable' => '面板無法連線到 :host 上的 :company 伺服器。請檢查 Explain 設定中的位址，並確認 :company 正在執行。',
    'server_model_missing' => '你的 :company 伺服器上沒有這個模型。請用 "ollama pull" 下載，或在 Explain 設定中輸入其他模型。',
];
