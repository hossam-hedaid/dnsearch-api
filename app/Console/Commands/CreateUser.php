<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:user
    {--name= : The name of the user}
    {--email= : The email of the user}
    {--password= : The password of the user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Craete a new user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->option('name') ?? $this->ask('What is the user\'s name?');
        $email = $this->option('email') ?? $this->ask('What is the user\'s email?');
        $password = $this->option('password') ?? $this->secret('What is the user\'s password?');
        $validator = Validator::make(
            [
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8'],
            ]
        );

        if($validator->fails()) {
            $this->error('Could not create user:');
            foreach ($validator->errors()->all() as $error) {
                $this->line(" - {$error}");
            }
            return self::FAILURE;
        }
        $data = [
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ];
        $user = User::create($data);

        $this->info("User created successfully!");
        $this->table(
            ['ID', 'Name', 'Email'],
            [[$user->id, $user->name, $user->email]]
        );

        return self::SUCCESS;
    }
}
