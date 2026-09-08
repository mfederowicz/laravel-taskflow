<?php

return [
    'required' => 'Field :attribute is required.',
    'string' => 'Field :attribute must be a string.',
    'max' => [
        'string' => 'Field :attribute must be no more than :max characters.',
    ],
    'min' => [
        'string' => 'Field :attribute must be at least :min characters.',
    ],
    'in' => 'Value of field :attribute is not valid.',
    'date' => 'Field :attribute must be a date.',
    'email' => 'Field :attribute must be proper e-mail.',
    'unique' => 'Value of :attribute already exists.',
    'confirmed' => 'The :attribute confirmation does not match.',
    'integer' => 'Field :attribute must be a number.',
    'exists' => 'Selected :attribute is invalid.',

    'attributes' => [
        'name' => 'name',
        'title' => 'title',
        'description' => 'description',
        'status' => 'status',
        'priority' => 'priority',
        'due_date' => 'due date',
        'email' => 'email address',
        'password' => 'password',
        'project_id' => 'project',
        'body' => 'comment',
    ],
];
