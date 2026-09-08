<?php

return [
    'required' => 'Pole :attribute jest wymagane.',
    'string' => 'Pole :attribute musi być tekstem.',
    'max' => [
        'string' => 'Pole :attribute może mieć maksymalnie :max znaków.',
    ],
    'min' => [
        'string' => 'Pole :attribute musi mieć co najmniej :min znaków.',
    ],
    'in' => 'Wartość pola :attribute jest nieprawidłowa.',
    'date' => 'Pole :attribute musi być datą.',
    'email' => 'Pole :attribute musi być poprawnym adresem e-mail.',
    'unique' => 'Wartość pola :attribute już istnieje.',
    'confirmed' => 'Potwierdzenie pola :attribute nie zgadza się.',
    'integer' => 'Pole :attribute musi być liczbą.',
    'exists' => 'Wybrana wartość pola :attribute jest nieprawidłowa.',

    'attributes' => [
        'name' => 'nazwa',
        'title' => 'tytuł',
        'description' => 'opis',
        'status' => 'status',
        'priority' => 'priorytet',
        'due_date' => 'termin',
        'email' => 'adres e-mail',
        'password' => 'hasło',
        'project_id' => 'projekt',
        'body' => 'treść',
    ],
];
