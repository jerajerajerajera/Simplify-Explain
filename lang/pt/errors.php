<?php

return [
    'unreachable' => 'Não foi possível contactar :host a partir do painel. Verifica se o servidor do painel pode ligar-se à internet.',
    'invalid_key' => 'A chave API da :company não é válida. Um administrador pode introduzir uma nova nas definições do Explain.',
    'no_credit' => 'A conta :company desta chave não tem crédito. Um administrador pode adicionar crédito na :company.',
    'model_not_allowed' => 'Esta chave da :company não pode usar o modelo escolhido. Um administrador pode escolher outro nas definições do Explain.',
    'model_missing' => 'O modelo da :company escolhido não está disponível para esta chave. Um administrador pode escolher outro nas definições do Explain.',
    'busy' => 'A :company está ocupada para esta chave. Tenta novamente daqui a um minuto.',
    'unavailable' => 'A :company está temporariamente indisponível. Tenta novamente daqui a um minuto.',
    'failed' => 'A :company não conseguiu responder (erro :status). Tenta mais tarde.',
    'server_unreachable' => 'O painel não consegue chegar ao servidor :company em :host. Verifique o endereço nas definições do Explain e se o :company está a correr.',
    'server_model_missing' => 'Este modelo não está no seu servidor :company. Transfira-o com "ollama pull" ou introduza outro modelo nas definições do Explain.',
];
