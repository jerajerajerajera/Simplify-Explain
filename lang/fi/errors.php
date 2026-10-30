<?php

return [
    'unreachable' => ':host ei vastaa paneelista. Tarkista, että paneelin palvelin saa yhdistää internetiin.',
    'invalid_key' => ':company-API-avain ei kelpaa. Ylläpitäjä voi antaa uuden Explainin asetuksissa.',
    'no_credit' => 'Avaimen :company-tilillä ei ole enää saldoa. Ylläpitäjä voi lisätä saldoa palvelussa :company.',
    'model_not_allowed' => 'Tällä :company-avaimella ei voi käyttää valittua mallia. Ylläpitäjä voi valita toisen Explainin asetuksissa.',
    'model_missing' => 'Valittu :company-malli ei ole tämän avaimen käytettävissä. Ylläpitäjä voi valita toisen Explainin asetuksissa.',
    'busy' => ':company on juuri nyt ruuhkautunut tälle avaimelle. Yritä minuutin päästä uudelleen.',
    'unavailable' => ':company ei ole tilapäisesti käytettävissä. Yritä minuutin päästä uudelleen.',
    'failed' => ':company ei pystynyt vastaamaan (virhe :status). Yritä myöhemmin uudelleen.',
    'server_unreachable' => 'Paneeli ei tavoita :company-palvelinta osoitteessa :host. Tarkista osoite Explainin asetuksista ja että :company on käynnissä.',
    'server_model_missing' => 'Tätä mallia ei ole :company-palvelimellasi. Lataa se komennolla "ollama pull" tai anna toinen malli Explainin asetuksissa.',
];
