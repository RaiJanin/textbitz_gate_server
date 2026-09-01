<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GateResource;
use App\Models\Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GatesController extends Controller
{
    /**
     * Gates for every school the authenticated user has a relationship with
     * (as a guardian of one or more students, or via their own student
     * account). Mirrors the relation set MeController loads.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing(['guardian.students', 'studentAccount.student']);

        $schoolIds = collect()
            ->merge($user->guardian?->students->pluck('school_id') ?? [])
            ->push($user->studentAccount?->student?->school_id)
            ->filter()
            ->unique();

        $gates = Gate::whereIn('school_id', $schoolIds)->get();

        return response()->json([
            'gates' => GateResource::collection($gates),
        ]);
    }
}