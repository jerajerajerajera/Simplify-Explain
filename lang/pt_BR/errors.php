<?php

return [
    'unreachable' => 'Não foi possível acessar :host a partir do painel. Verifique se o servidor do painel pode se conectar à internet.',
    'invalid_key' => 'A chave de API da :company não é válida. Um administrador pode inserir uma nova nas configurações do Explain.',
    'no_credit' => 'A conta :company desta chave está sem crédito. Um administrador pode adicionar crédito na :company.',
    'model_not_allowed' => 'Esta chave da :company não pode usar o modelo escolhido. Um administrador pode escolher outro nas configurações do Explain.',
    'model_missing' => 'O modelo da :company escolhido não está disponível para esta chave. Um administrador pode escolher outro nas configurações do Explain.',
    'busy' => 'A :company está ocupada para esta chave agora. Tente de novo em um minuto.',
    'unavailable' => 'A :company está temporariamente indisponível. Tente de novo em um minuto.',
    'failed' => 'A :company não conseguiu responder (erro :status). Tente mais tarde.',
    'server_unreachable' => 'O painel não consegue acessar o servidor :company em :host. Confira o endereço nas configurações do Explain e se o :company está rodando.',
    'server_model_missing' => 'Este modelo não está no seu servidor :company. Baixe-o com "ollama pull" ou digite outro modelo nas configurações do Explain.',
];
