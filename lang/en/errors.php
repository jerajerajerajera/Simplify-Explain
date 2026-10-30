<?php

return [
    'unreachable' => 'Could not reach :host from the panel. Check that the panel server may connect to the internet.',
    'invalid_key' => 'The :company API key is not valid. An administrator can enter a new one in the Explain settings.',
    'no_credit' => 'The :company account behind this key has no credit left. An administrator can add credit at :company.',
    'model_not_allowed' => 'This :company API key may not use the chosen model. An administrator can pick another model in the Explain settings.',
    'model_missing' => 'The chosen :company model is not available for this key. An administrator can pick another model in the Explain settings.',
    'busy' => ':company is busy for this key right now. Try again in a minute.',
    'unavailable' => ':company is temporarily unavailable. Try again in a minute.',
    'failed' => ':company could not answer this question (error :status). Try again later.',
    'server_unreachable' => 'The panel cannot reach the :company server at :host. Check the address in the Explain settings and that :company is running.',
    'server_model_missing' => 'This model is not on your :company server. Download it with "ollama pull" or enter another model in the Explain settings.',
];
