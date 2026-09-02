<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Rules\PhilippineMobileNumber;
use Filament\Commands\MakeUserCommand;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Overrides Filament's built-in `make:filament-user` so it also captures the
 * `phone_number` this app requires, and can flag the account as an admin and
 * scope it to a school in one go.
 */
#[AsCommand(name: 'make:filament-user', aliases: ['filament:make-user', 'filament:user'])]
class MakeFilamentUserCommand extends MakeUserCommand
{
    protected bool $shouldBeAdmin = true;

    protected ?int $schoolId = null;

    protected function getOptions(): array
    {
        return [
            ...parent::getOptions(),
            new InputOption('phone', null, InputOption::VALUE_REQUIRED, 'A valid, unique PH mobile number (+639XXXXXXXXX)'),
            new InputOption('school', null, InputOption::VALUE_REQUIRED, 'School id to scope this admin to (omit for a super-admin)'),
            new InputOption('no-admin', null, InputOption::VALUE_NONE, 'Create the account without admin-panel access'),
        ];
    }

    public function handle(): int
    {
        // Validate a phone passed as an option up front so the failure is a clean
        // message, not a DB integrity error mid-create.
        if (($phone = $this->option('phone')) !== null && ($error = $this->phoneError($phone))) {
            $this->components->error($error);

            return static::FAILURE;
        }

        return parent::handle();
    }

    protected function phoneError(string $value): ?string
    {
        return Validator::make(
            ['phone_number' => $value],
            ['phone_number' => ['required', new PhilippineMobileNumber, 'unique:users,phone_number']],
        )->errors()->first('phone_number') ?: null;
    }

    protected function getUserData(): array
    {
        $data = parent::getUserData();

        $data['phone_number'] = $this->option('phone') ?? text(
            label: 'Phone number',
            placeholder: '+639171234567',
            required: true,
            validate: fn (string $value): ?string => $this->phoneError($value),
        );

        $this->shouldBeAdmin = ! $this->option('no-admin')
            && ($this->option('name') !== null || confirm('Grant admin-panel access?', default: true));

        if ($this->shouldBeAdmin) {
            $this->schoolId = $this->resolveSchoolId();
            $data['school_id'] = $this->schoolId;
        }

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

    protected function createUser(): Model&Authenticatable
    {
        $user = parent::createUser();

        if ($this->shouldBeAdmin) {
            $user->forceFill(['is_admin' => true])->save();
        }

        return $user;
    }
}
