<?php

namespace App\Http\Middleware;

use App\Models\School;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates turnstile/bridge ingest calls by matching the bearer token
 * against schools.ingest_token, then exposes the resolved School to the
 * controller via the "ingest_school" request attribute.
 */
class AuthenticateSchoolIngest
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        $school = $token
            ? School::where('ingest_token', $token)->first()
            : null;

        if (! $school) {
            return response()->json(['message' => 'Invalid ingest token.'], 401);
        }

        $request->attributes->set('ingest_school', $school);

        return $next($request);
    }
}
