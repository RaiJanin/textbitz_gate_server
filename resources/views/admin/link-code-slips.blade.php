@php
    use App\Http\Controllers\Admin\LinkCodeSlipController;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Link code{{ $codes->count() > 1 ? 's' : '' }} — TextBitz Gate</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #f1f5f9;
            color: #0f172a;
            padding: 24px;
        }
        .toolbar {
            max-width: 720px; margin: 0 auto 20px; display: flex; gap: 12px; justify-content: flex-end;
        }
        .toolbar button {
            font: inherit; font-weight: 600; padding: 8px 16px; border-radius: 8px;
            border: 1px solid #cbd5e1; background: #fff; cursor: pointer;
        }
        .toolbar button.primary { background: #d97706; border-color: #d97706; color: #fff; }
        .slip {
            max-width: 720px; margin: 0 auto 20px; background: #fff; border: 1px solid #e2e8f0;
            border-radius: 16px; padding: 32px; page-break-inside: avoid;
        }
        .slip__brand { font-size: 13px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #d97706; }
        .slip__title { font-size: 22px; font-weight: 700; margin: 4px 0 2px; }
        .slip__school { color: #475569; font-size: 14px; margin-bottom: 24px; }
        .slip__grid { display: flex; gap: 28px; align-items: center; flex-wrap: wrap; }
        .slip__qr { width: 168px; height: 168px; flex: none; border: 1px solid #e2e8f0; border-radius: 12px; padding: 8px; }
        .slip__qr svg { width: 100%; height: 100%; display: block; }
        .slip__for { font-size: 13px; text-transform: uppercase; letter-spacing: .06em; color: #64748b; }
        .slip__student { font-size: 19px; font-weight: 700; margin: 2px 0 14px; }
        .slip__code {
            font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, monospace;
            font-size: 34px; font-weight: 700; letter-spacing: .12em;
            background: #fffbeb; border: 2px dashed #f59e0b; border-radius: 12px;
            padding: 12px 20px; display: inline-block;
        }
        .slip__meta { margin-top: 12px; font-size: 13px; color: #64748b; }
        .steps { margin: 24px 0 0; padding: 20px 24px; background: #f8fafc; border-radius: 12px; }
        .steps h3 { margin: 0 0 8px; font-size: 14px; }
        .steps ol { margin: 0; padding-left: 20px; font-size: 14px; line-height: 1.7; color: #334155; }
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none; }
            .slip { border: none; border-radius: 0; margin: 0; padding: 40px; max-width: none; }
            .slip + .slip { border-top: 1px dashed #cbd5e1; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.close()">Close</button>
        <button class="primary" onclick="window.print()">Print</button>
    </div>

    @foreach ($codes as $code)
        <article class="slip">
            <div class="slip__brand">TextBitz Gate</div>
            <h1 class="slip__title">Get your child's attendance alerts</h1>
            <div class="slip__school">{{ $code->student?->school?->name ?? 'Your school' }}</div>

            <div class="slip__grid">
                <div class="slip__qr">{!! LinkCodeSlipController::qrDataUri($code->code) !!}</div>
                <div>
                    <div class="slip__for">Link code for</div>
                    <div class="slip__student">{{ $code->student?->full_name ?? 'your child' }}</div>
                    <span class="slip__code">{{ $code->code }}</span>
                    <div class="slip__meta">
                        @if ($code->expires_at)
                            Use it before {{ $code->expires_at->format('M j, Y') }}.
                        @else
                            No expiry.
                        @endif
                        Relationship: {{ $code->default_relationship ?? 'Guardian' }}.
                    </div>
                </div>
            </div>

            <div class="steps">
                <h3>How to use this code</h3>
                <ol>
                    <li>Install <strong>TextBitz Gate</strong> on your phone and create an account.</li>
                    <li>Open <strong>Settings → Link a student</strong>.</li>
                    <li>Enter the code above (or scan the QR) and confirm.</li>
                    <li>You'll get a notification whenever {{ $code->student?->full_name ?? 'your child' }} taps in or out.</li>
                </ol>
            </div>
        </article>
    @endforeach

    <script>
        window.addEventListener('load', () => setTimeout(() => window.print(), 400));
    </script>
</body>
</html>
