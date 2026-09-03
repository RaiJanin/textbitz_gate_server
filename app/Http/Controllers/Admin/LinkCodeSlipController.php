<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LinkCode;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Printable hand-out slips for guardian link codes. Rendered from the Filament
 * panel's "Print slip" actions; opens in a new tab and auto-triggers print.
 */
class LinkCodeSlipController extends Controller
{
    public function show(Request $request, LinkCode $linkCode): Response
    {
        $this->authorizeAccess($request, collect([$linkCode]));

        return response()->view('admin.link-code-slips', [
            'codes' => collect([$linkCode->load('student.school')]),
        ]);
    }

    public function batch(Request $request): Response
    {
        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->all();

        $codes = LinkCode::with('student.school')->whereIn('id', $ids)->get();

        abort_if($codes->isEmpty(), 404);

        $this->authorizeAccess($request, $codes);

        return response()->view('admin.link-code-slips', ['codes' => $codes]);
    }

    /**
     * @param  Collection<int, LinkCode>  $codes
     */
    protected function authorizeAccess(Request $request, Collection $codes): void
    {
        $admin = $request->user('admin');

        abort_unless($admin !== null, 403);

        if ($admin->isSuperAdmin()) {
            return;
        }

        abort_unless(
            $codes->every(fn (LinkCode $c) => $c->school_id === $admin->school_id),
            403,
        );
    }

    public static function qrDataUri(string $payload): string
    {
        return (new QRCode(new QROptions([
            'outputType' => QRCode::OUTPUT_MARKUP_SVG,
            'eccLevel' => QRCode::ECC_M,
            'svgViewBoxSize' => 200,
            'addQuietzone' => true,
        ])))->render($payload);
    }
}
