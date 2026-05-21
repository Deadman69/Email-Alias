<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — Health</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, -apple-system, sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem; }
        .card { background: #1e293b; border-radius: 1rem; padding: 2rem; max-width: 480px; width: 100%; box-shadow: 0 4px 24px rgba(0,0,0,.3); }
        .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; }
        .app-name { font-size: .875rem; color: #94a3b8; }
        .version { font-size: .75rem; color: #64748b; font-family: monospace; }
        .status-badge { display: inline-flex; align-items: center; gap: .5rem; padding: .375rem .75rem; border-radius: .5rem; font-size: .875rem; font-weight: 600; }
        .status-healthy { background: #064e3b; color: #6ee7b7; }
        .status-degraded { background: #7f1d1d; color: #fca5a5; }
        .checks { display: flex; flex-direction: column; gap: .5rem; }
        .check { display: flex; align-items: center; justify-content: space-between; padding: .75rem 1rem; background: #0f172a; border-radius: .5rem; }
        .check-name { font-size: .875rem; font-weight: 500; text-transform: capitalize; }
        .check-right { display: flex; align-items: center; gap: .75rem; font-size: .75rem; }
        .check-latency { color: #64748b; font-family: monospace; }
        .dot { width: .625rem; height: .625rem; border-radius: 50%; flex-shrink: 0; }
        .dot-ok { background: #34d399; box-shadow: 0 0 6px #34d399; }
        .dot-error { background: #f87171; box-shadow: 0 0 6px #f87171; }
        .check-error { font-size: .6875rem; color: #f87171; margin-top: .25rem; word-break: break-all; }
        .footer { margin-top: 1.25rem; text-align: center; font-size: .6875rem; color: #475569; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <div>
                <div class="app-name">{{ config('app.name') }}</div>
                <div class="version">v{{ $version }}</div>
            </div>
            <span class="status-badge {{ $healthy ? 'status-healthy' : 'status-degraded' }}">
                {{ $healthy ? __('Healthy') : __('Degraded') }}
            </span>
        </div>

        <div class="checks">
            @foreach ($checks as $name => $check)
                <div>
                    <div class="check">
                        <span class="check-name">{{ __($name) }}</span>
                        <div class="check-right">
                            @if (isset($check['latency_ms']))
                                <span class="check-latency">{{ $check['latency_ms'] }} ms</span>
                            @endif
                            <span class="dot {{ $check['status'] === 'ok' ? 'dot-ok' : 'dot-error' }}"></span>
                        </div>
                    </div>
                    @if (isset($check['error']))
                        <div class="check-error" style="padding: 0 1rem .25rem;">{{ $check['error'] }}</div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="footer">{{ $timestamp }}</div>
    </div>
</body>
</html>
