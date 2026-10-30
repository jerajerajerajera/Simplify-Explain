<?php

return [
    'unreachable' => 'パネルから :host に接続できませんでした。パネルのサーバーがインターネットに接続できるか確認してください。',
    'invalid_key' => ':company の API キーが無効です。管理者が Explain の設定で新しいキーを入力できます。',
    'no_credit' => 'このキーの :company アカウントにクレジットが残っていません。管理者が :company でクレジットを追加できます。',
    'model_not_allowed' => 'この :company キーでは選択したモデルを使えません。管理者が Explain の設定で別のモデルを選べます。',
    'model_missing' => '選択した :company のモデルはこのキーでは利用できません。管理者が Explain の設定で別のモデルを選べます。',
    'busy' => ':company はこのキーで混み合っています。1分後にもう一度お試しください。',
    'unavailable' => ':company は一時的に利用できません。1分後にもう一度お試しください。',
    'failed' => ':company はこの質問に回答できませんでした (エラー :status)。後でもう一度お試しください。',
    'server_unreachable' => 'パネルが :host の :company サーバーに接続できません。Explain の設定のアドレスと、:company が起動していることを確認してください。',
    'server_model_missing' => 'このモデルは :company サーバーにありません。"ollama pull" でダウンロードするか、Explain の設定で別のモデルを入力してください。',
];
