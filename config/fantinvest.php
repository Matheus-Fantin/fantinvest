<?php

return [
    /*
    | Com false (padrão), novas contas só são criadas por convite de um administrador.
    | A primeira conta do sistema sempre pode ser criada e vira administradora.
    */
    'cadastro_aberto' => (bool) env('CADASTRO_ABERTO', false),
];
