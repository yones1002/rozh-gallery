<?php

namespace App\Console\Commands;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Prompts\Prompt;
use SensitiveParameter;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:admin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an administrator who can access the panel';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Ubuntu's uutils stty rejects the saved terminal state that Laravel
        // Prompts restores, so the interactive renderer aborts. The registered
        // Symfony fallback still hides the password and validates each answer.
        Prompt::fallbackWhen(true);

        $name = $this->askForName();
        $email = $this->askForEmail();
        $password = $this->askForPassword();

        $administrator = $this->createAdministrator($name, $email, $password);

        $loginUrl = Filament::getPanel('admin')->getLoginUrl();

        $this->components->info("Administrator {$administrator->email} can now sign in at {$loginUrl}.");

        return self::SUCCESS;
    }

    private function askForName(): string
    {
        $name = text(
            label: 'Name',
            required: true,
            validate: function (string $value): ?string {
                $validator = Validator::make(
                    ['name' => trim($value)],
                    ['name' => ['required', 'string', 'max:255']],
                );

                return $validator->errors()->first('name') ?: null;
            },
        );

        return trim($name);
    }

    private function askForEmail(): string
    {
        $email = text(
            label: 'Email address',
            required: true,
            validate: function (string $value): ?string {
                $validator = Validator::make(
                    ['email' => strtolower(trim($value))],
                    ['email' => ['required', 'string', 'email', 'max:255', 'unique:users,email']],
                );

                return $validator->errors()->first('email') ?: null;
            },
        );

        return strtolower(trim($email));
    }

    private function askForPassword(): string
    {
        $password = password(
            label: 'Password',
            required: true,
            validate: function (string $value): ?string {
                $validator = Validator::make(
                    ['password' => $value],
                    ['password' => ['required', 'string', Password::defaults()]],
                );

                return $validator->errors()->first('password') ?: null;
            },
            hint: 'At least 8 characters. The value stays hidden and is not stored in the project.',
        );

        password(
            label: 'Confirm password',
            required: true,
            validate: fn (string $value): ?string => hash_equals($password, $value)
                ? null
                : 'The password confirmation does not match.',
        );

        return $password;
    }

    private function createAdministrator(string $name, string $email, #[SensitiveParameter] string $password): User
    {
        $administrator = new User;
        $administrator->name = $name;
        $administrator->email = $email;
        $administrator->password = $password;
        $administrator->is_admin = true;
        $administrator->save();

        return $administrator;
    }
}
