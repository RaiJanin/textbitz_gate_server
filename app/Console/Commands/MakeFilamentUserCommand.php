<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Rules\PhilippineMobileNumber;
use Filament\Commands\MakeUserCommand;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Overrides Filament's built-in `make:filament-user` for the `x08` admin table.
 * The panel logs in by **username** (`name`), so `name` is required + unique and
 * `email` is optional. Also captures an optional phone and a school to scope to.
 * (The panel's `admins` provider means the created model is App\Models\AdminUser.)
 */
#[AsCommand(name: 'make:filament-user', aliases: ['filament:make-user', 'filament:user'])]
class MakeFilamentUserCommand extends MakeUserCommand
{
    protected function getOptions(): array
    {
        return [
            ...parent::getOptions(),
            new InputOption('phone', null, InputOption::VALUE_REQUIRED, 'Optional PH mobile number (+639XXXXXXXXX)'),
            new InputOption('school', null, InputOption::VALUE_REQUIRED, 'School id to scope this admin to (omit for a super-admin)'),
        ];
    }

    public function handle(): int
    {
        foreach ([
            'name' => $this->nameError(...),
            'email' => $this->emailError(...),
            'phone' => $this->phoneError(...),
        ] as $option => $validator) {
            $value = $this->option($option);

            if ($value !== null && ($error = $validator($value))) {
                $this->components->error($error);

                return static::FAILURE;
            }
        }

        return parent::handle();
    }

    protected function nameError(string $value): ?string
    {
        return Validator::make(['name' => $value], ['name' => ['required', 'string', 'max:255', 'unique:x08,name']])
            ->errors()->first('name') ?: null;
    }

    protected function emailError(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return Validator::make(['email' => $value], ['email' => ['email', 'max:255', 'unique:x08,email']])
            ->errors()->first('email') ?: null;
    }

    protected function phoneError(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return Validator::make(['phone_number' => $value], ['phone_number' => [new PhilippineMobileNumber]])
            ->errors()->first('phone_number') ?: null;
    }

    protected function getUserData(): array
    {
        $name = $this->options['name'] ?? text(
            label: 'Username',
            required: true,
            validate: fn (string $value): ?string => $this->nameError($value),
        );

        $password = $this->options['password'] ?? password(label: 'Password', required: true);

        $data = ['name' => $name, 'password' => Hash::make($password)];

        $email = $this->options['email'];
        if ($email === null && $this->input->isInteractive()) {
            $email = text(
                label: 'Email address (optional)',
                validate: fn (?string $value): ?string => $this->emailError($value),
            );
        }
        if (filled($email)) {
            $data['email'] = $email;
        }

        $phone = $this->option('phone');
        if ($phone === null && $this->input->isInteractive()) {
            $phone = text(
                label: 'Phone number (optional)',
                placeholder: '+639171234567',
                validate: fn (?string $value): ?string => $this->phoneError($value),
            );
        }
        if (filled($phone)) {
            $data['phone_number'] = $phone;
        }

        $data['school_id'] = $this->resolveSchoolId();

        return $data;
    }

    protected function resolveSchoolId(): ?int
    {
        if ($this->option('school') !== null) {
            return (int) $this->option('school') ?: null;
        }

        $schools = School::query()->orderBy('name')->pluck('name', 'id')->all();

        if ($schools === []) {
            return null;
        }

        $choice = select(
            label: 'Scope this admin to a school?',
            options: ['' => 'All schools (super-admin)'] + $schools,
            default: '',
        );

        return $choice === '' ? null : (int) $choice;
    }
}
