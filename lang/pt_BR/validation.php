<?php

return [
    'array' => 'O campo :attribute precisa ser uma lista.',
    'between' => [
        'numeric' => 'O campo :attribute precisa estar entre :min e :max.',
        'string' => 'O campo :attribute precisa ter entre :min e :max caracteres.',
    ],
    'confirmed' => 'A confirmação de :attribute não confere.',
    'current_password' => 'A senha está incorreta.',
    'date' => 'O campo :attribute precisa ser uma data válida.',
    'email' => 'O campo :attribute precisa ser um e-mail válido.',
    'gt' => [
        'numeric' => 'O campo :attribute precisa ser maior que :value.',
    ],
    'in' => 'O valor escolhido em :attribute é inválido.',
    'integer' => 'O campo :attribute precisa ser um número inteiro.',
    'lowercase' => 'O campo :attribute precisa estar em letras minúsculas.',
    'max' => [
        'numeric' => 'O campo :attribute não pode ser maior que :max.',
        'string' => 'O campo :attribute não pode ter mais de :max caracteres.',
    ],
    'min' => [
        'numeric' => 'O campo :attribute precisa ser pelo menos :min.',
        'string' => 'O campo :attribute precisa ter pelo menos :min caracteres.',
    ],
    'numeric' => 'O campo :attribute precisa ser um número.',
    'required' => 'Preencha o campo :attribute.',
    'string' => 'O campo :attribute precisa ser um texto.',
    'unique' => 'Este :attribute já está em uso.',
    'password' => [
        'letters' => 'A senha precisa ter pelo menos uma letra.',
        'mixed' => 'A senha precisa ter letras maiúsculas e minúsculas.',
        'numbers' => 'A senha precisa ter pelo menos um número.',
        'symbols' => 'A senha precisa ter pelo menos um símbolo.',
        'uncompromised' => 'Esta senha apareceu em um vazamento de dados. Escolha outra.',
    ],

    'attributes' => [
        'name' => 'nome',
        'email' => 'e-mail',
        'password' => 'senha',
        'current_password' => 'senha atual',
        'data' => 'data',
        'valor' => 'valor',
        'categoria' => 'categoria',
        'descricao' => 'descrição',
        'quantidade' => 'quantidade',
        'nota' => 'nota',
        'classe' => 'classe',
        'reserva_atual' => 'valor guardado',
        'reserva_meses' => 'meses de cobertura',
    ],
];
