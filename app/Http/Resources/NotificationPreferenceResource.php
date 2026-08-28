<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\NotificationPreference
 */
class NotificationPreferenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'role' => $this->role,
            'arrival' => (bool) $this->arrival,
            'departure' => (bool) $this->departure,
            'late_alert' => (bool) $this->late_alert,
            'weekly_summary' => (bool) $this->weekly_summary,
        ];
    }
}
