<?php

return [
    'unreachable' => 'Impossibile raggiungere :host dal pannello. Controlla che il server del pannello possa connettersi a internet.',
    'invalid_key' => 'La chiave API di :company non è valida. Un amministratore può inserirne una nuova nelle impostazioni di Explain.',
    'no_credit' => 'L\'account :company di questa chiave non ha più credito. Un amministratore può aggiungerlo su :company.',
    'model_not_allowed' => 'Questa chiave :company non può usare il modello scelto. Un amministratore può sceglierne un altro nelle impostazioni di Explain.',
    'model_missing' => 'Il modello :company scelto non è disponibile per questa chiave. Un amministratore può sceglierne un altro nelle impostazioni di Explain.',
    'busy' => ':company è occupato per questa chiave. Riprova tra un minuto.',
    'unavailable' => ':company non è disponibile al momento. Riprova tra un minuto.',
    'failed' => ':company non è riuscito a rispondere (errore :status). Riprova più tardi.',
    'server_unreachable' => 'Il pannello non raggiunge il server :company su :host. Controlla l\'indirizzo nelle impostazioni di Explain e che :company sia in esecuzione.',
    'server_model_missing' => 'Questo modello non è sul tuo server :company. Scaricalo con "ollama pull" o inserisci un altro modello nelle impostazioni di Explain.',
];
