<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing(['guardian.students.school', 'studentAccount.student.school']);

        return response()->json([
            'account' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
            ],
            'roles' => $user->roles(),
            'guardian' => $user->guardian ? [
                'id' => $user->guardian->id,
                'name' => $user->guardian->name,
                'email' => $user->guardian->email,
                'phone' => $user->guardian->phone,
                'students' => StudentResource::collection($user->guardian->students),
            ] : null,
            'student' => $user->studentAccount?->student ? [
                'account_id' => $user->studentAccount->id,
                'student' => new StudentResource($user->studentAccount->student),
            ] : null,
        ]);
    }
}
