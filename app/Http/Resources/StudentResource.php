<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Student
 */
class StudentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'grade' => $this->grade,
            'section' => $this->section,
            'avatar_path' => $this->avatar_path,
            'relationship' => $this->whenPivotLoaded('guardian_student', fn () => $this->pivot->relationship),
            'school' => $this->whenLoaded('school', fn () => [
                'id' => $this->school->id,
                'name' => $this->school->name,
                'timezone' => $this->school->timezone,
                'attendance_cutoff_time' => $this->school->attendance_cutoff_time,
                'contact_phone' => $this->school->contact_phone,
                'contact_email' => $this->school->contact_email,
            ]),
        ];
    }
}
