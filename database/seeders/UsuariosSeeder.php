<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class UsuariosSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Eloy Espinosa Franco',
                'email' => 'eloy.espinosa@tecdimex.com',
                'password' => 'asdf1234',
                'avatar' => 'https://mpios.s3.us-east-2.amazonaws.com/21004/avatar/perfil.png',
            ],
            [
                'name' => 'Usuaro de Prueba',
                'email' => 'prueba@municipio.com',
                'password' => 'asdf1234',
                'avatar' => 'https://mpios.s3.us-east-2.amazonaws.com/21004/avatar/perfil.png',
            ],
        ];

        foreach ($users as $data) {
            $user = User::firstOrNew(['email' => $data['email']]);

            $payload = [
                'name' => $data['name'],
                'password' => Hash::make($data['password']),
            ];

            if (Schema::hasColumn('users', 'avatar')) {
                $payload['avatar'] = $data['avatar'];
            }

            $user->forceFill($payload);
            $user->save();
        }
    }
}
