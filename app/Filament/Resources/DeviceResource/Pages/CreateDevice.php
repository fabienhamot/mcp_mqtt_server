<?php

namespace App\Filament\Resources\DeviceResource\Pages;

use App\Filament\Resources\DeviceResource;
use App\Filament\Resources\DeviceResource\Concerns\HandlesDeviceJsonFields;
use Filament\Resources\Pages\CreateRecord;

class CreateDevice extends CreateRecord
{
    use HandlesDeviceJsonFields;

    protected static string $resource = DeviceResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->extractJsonFields($data);
    }
}
