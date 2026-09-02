<?php

namespace App\Filament\Resources\LinkCodes\Pages;

use App\Actions\IssueLinkCode;
use App\Filament\Resources\LinkCodes\LinkCodeResource;
use App\Models\Student;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateLinkCode extends CreateRecord
{
    protected static string $resource = LinkCodeResource::class;

    /**
     * Route creation through IssueLinkCode so any earlier still-usable code for
     * the same student is revoked and the expiry is derived from "valid days".
     */
    protected function handleRecordCreation(array $data): Model
    {
        $student = Student::findOrFail($data['student_id']);

        return IssueLinkCode::run(
            $student,
            $data['default_relationship'] ?? 'Guardian',
            (int) ($data['valid_for_days'] ?? 30),
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
