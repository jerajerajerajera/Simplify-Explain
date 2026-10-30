<?php

return [
    'unreachable' => 'Nepavyko pasiekti :host iš panelės. Patikrinkite, ar panelės serveris gali jungtis prie interneto.',
    'invalid_key' => ':company API raktas negalioja. Administratorius gali įvesti naują Explain nustatymuose.',
    'no_credit' => 'Šio rakto :company paskyroje nebeliko kredito. Administratorius gali jo papildyti :company.',
    'model_not_allowed' => 'Šiam :company raktui neleidžiama naudoti pasirinkto modelio. Administratorius gali pasirinkti kitą Explain nustatymuose.',
    'model_missing' => 'Pasirinktas :company modelis šiam raktui nepasiekiamas. Administratorius gali pasirinkti kitą Explain nustatymuose.',
    'busy' => ':company šiuo metu užimtas šiam raktui. Bandykite po minutės.',
    'unavailable' => ':company laikinai nepasiekiamas. Bandykite po minutės.',
    'failed' => ':company negalėjo atsakyti į šį klausimą (klaida :status). Bandykite vėliau.',
    'server_unreachable' => 'Skydelis negali pasiekti :company serverio adresu :host. Patikrinkite adresą Explain nustatymuose ir ar :company veikia.',
    'server_model_missing' => 'Šio modelio nėra jūsų :company serveryje. Atsisiųskite jį komanda "ollama pull" arba Explain nustatymuose įveskite kitą modelį.',
];
