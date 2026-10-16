<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class TornarAdmin extends Command
{
    protected $signature = 'usuario:admin {email : E-mail da conta} {--remover : Tira o acesso de administrador}';

    protected $description = 'Dá (ou tira) acesso de administrador a uma conta, para gerenciar convites';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('Nenhuma conta com esse e-mail.');

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => ! $this->option('remover')])->save();
        $this->info($user->is_admin ? "{$user->email} agora é administrador." : "{$user->email} não é mais administrador.");

        return self::SUCCESS;
    }
}
