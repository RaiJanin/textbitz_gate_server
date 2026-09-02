<?php

namespace App\Filament\Resources\LinkCodes\Pages;

use App\Filament\Resources\LinkCodes\LinkCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLinkCodes extends ListRecords
{
    protected static string $resource = LinkCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
