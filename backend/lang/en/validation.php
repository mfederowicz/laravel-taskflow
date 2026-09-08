<?php

return [
    'required' => 'Field :attribute is required.',
    'string' => 'Field :attribute must be a string.',
    'max' => [
        'string' => 'Field :attribute must be no more then :max characters.',
    ],
    'in' => 'Value of field :attribute is not valid.',
    'date' => 'Field :attribute must be a date.',
    'email' => 'Field :attribute must be proper e-mail.',
    'unique' => 'Value of :attribute already exists.',

    'attributes' => [
        'title' => 'title',
        'description' => 'description',
        'status' => 'status',
        'priority' => 'priority',
        'due_date' => 'due date',
        'email' => 'email address',
        'password' => 'password',
    ],
];
