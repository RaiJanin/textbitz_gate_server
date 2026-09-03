<?php

namespace App\Filament\Resources\Gates\Pages;

use App\Filament\Resources\Gates\GateResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateGate extends CreateRecord
{
    protected static string $resource = GateResource::class;

    protected function afterCreate(): void
    {
        Notification::make()
            ->success()
            ->title("Gate created — ID {$this->record->id}")
            ->body("Set the turnstile / bridge to post with gate_id={$this->record->id} and the school's ingest token.")
            ->persistent()
            ->send();
    }
}
