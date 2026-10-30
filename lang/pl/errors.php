<?php

return [
    'unreachable' => 'Nie udało się połączyć z :host z panelu. Sprawdź, czy serwer panelu może łączyć się z internetem.',
    'invalid_key' => 'Klucz API :company jest nieprawidłowy. Administrator może wpisać nowy w ustawieniach Explain.',
    'no_credit' => 'Konto :company tego klucza nie ma już środków. Administrator może je doładować w :company.',
    'model_not_allowed' => 'Ten klucz :company nie może używać wybranego modelu. Administrator może wybrać inny w ustawieniach Explain.',
    'model_missing' => 'Wybrany model :company nie jest dostępny dla tego klucza. Administrator może wybrać inny w ustawieniach Explain.',
    'busy' => ':company jest teraz zajęty dla tego klucza. Spróbuj ponownie za minutę.',
    'unavailable' => ':company jest chwilowo niedostępny. Spróbuj ponownie za minutę.',
    'failed' => ':company nie mógł odpowiedzieć na to pytanie (błąd :status). Spróbuj później.',
    'server_unreachable' => 'Panel nie może połączyć się z serwerem :company pod :host. Sprawdź adres w ustawieniach Explain i czy :company działa.',
    'server_model_missing' => 'Tego modelu nie ma na Twoim serwerze :company. Pobierz go poleceniem "ollama pull" lub podaj inny model w ustawieniach Explain.',
];
