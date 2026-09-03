<?php

namespace App\Filament\Resources\Guardians\Pages;

use App\Filament\Resources\Guardians\GuardianResource;
use App\Support\GuardianAccount;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateGuardian extends CreateRecord
{
    protected static string $resource = GuardianResource::class;

    /**
     * The GuardianObserver has, by now, provisioned the paired mobile-app
     * login. Surface it (and any auto-generated password) to the staff member.
     */
    protected function afterCreate(): void
    {
        $user = $this->record->refresh()->user;

        if (! $user) {
            return;
        }

        $generated = GuardianAccount::$lastGeneratedPassword;

        Notification::make()
            ->success()
            ->title('Mobile-app login ready')
            ->body(
                "Login: {$user->phone_number}"
                .($generated ? "\nTemporary password: {$generated}" : '')
            )
            ->persistent()
            ->send();
    }
}
