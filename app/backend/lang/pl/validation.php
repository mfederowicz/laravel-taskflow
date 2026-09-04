<?php

return [
    'required' => 'Pole :attribute jest wymagane.',
    'string' => 'Pole :attribute musi być tekstem.',
    'max' => [
        'string' => 'Pole :attribute nie może mieć więcej niż :max znaków.',
    ],
    'in' => 'Wybrana wartość pola :attribute jest nieprawidłowa.',
    'date' => 'Pole :attribute musi zawierać prawidłową datę.',
    'email' => 'Pole :attribute musi być prawidłowym adresem e-mail.',
    'unique' => 'Wartość pola :attribute jest już zajęta.',

    'attributes' => [
        'title' => 'tytuł',
        'description' => 'opis',
        'status' => 'status',
        'priority' => 'priorytet',
        'due_date' => 'termin',
        'email' => 'adres e-mail',
        'password' => 'hasło',
    ],
];
