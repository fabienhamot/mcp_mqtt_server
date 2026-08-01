<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Authorize — {{ config('app.name', 'MCP Server') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
    {{-- Self-contained: no @vite (image Docker n'embarque pas public/build) --}}
    <style>
        :root {
            --bg: #f4f4f5;
            --card: #ffffff;
            --text: #18181b;
            --muted: #71717a;
            --border: #e4e4e7;
            --primary: #18181b;
            --primary-fg: #fafafa;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .card {
            width: 100%;
            max-width: 28rem;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgb(0 0 0 / 0.06);
            padding: 1.5rem;
        }
        .icon { display: block; margin: 0 auto 1rem; width: 3rem; height: 3rem; color: var(--primary); }
        h1 { margin: 0 0 0.5rem; font-size: 1.35rem; text-align: center; font-weight: 600; }
        .subtitle { margin: 0 0 1.25rem; font-size: 0.875rem; color: var(--muted); text-align: center; line-height: 1.4; }
        .user-box {
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            padding: 0.875rem 1rem;
            background: #fafafa;
            margin-bottom: 1rem;
        }
        .user-box .label { margin: 0 0 0.25rem; font-size: 0.8rem; color: var(--muted); }
        .user-box .email { margin: 0; font-weight: 500; font-size: 0.95rem; }
        .perms { margin-bottom: 1.25rem; }
        .perms > p { margin: 0 0 0.5rem; font-size: 0.875rem; font-weight: 500; }
        .perms ul { margin: 0; padding: 0; list-style: none; }
        .perms li {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: var(--muted);
            margin-bottom: 0.4rem;
        }
        .dot {
            flex-shrink: 0;
            width: 0.4rem;
            height: 0.4rem;
            margin-top: 0.4rem;
            border-radius: 999px;
            background: var(--primary);
        }
        .actions { display: flex; gap: 0.75rem; }
        .actions form { flex: 1; margin: 0; }
        button {
            width: 100%;
            height: 2.5rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            font-family: inherit;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
        }
        button:disabled { opacity: 0.6; cursor: not-allowed; }
        .btn-cancel {
            background: var(--card);
            border: 1px solid var(--border);
            color: var(--text);
        }
        .btn-cancel:hover:not(:disabled) { background: #f4f4f5; }
        .btn-approve {
            background: var(--primary);
            border: 1px solid var(--primary);
            color: var(--primary-fg);
        }
        .btn-approve:hover:not(:disabled) { opacity: 0.9; }
        .spinner { display: none; width: 1rem; height: 1rem; animation: spin 0.7s linear infinite; }
        .spinner.show { display: inline-block; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
<div class="card">
    <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M20.618 5.984A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.031 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
    </svg>

    <h1>Authorize {{ $client->name }}</h1>
    <p class="subtitle">This application will be able to use available MCP functionality.</p>

    <div class="user-box">
        <p class="label">Logged in as</p>
        <p class="email">{{ $user->email }}</p>
    </div>

    @if(count($scopes) > 0)
        <div class="perms">
            <p>Permissions</p>
            <ul>
                @foreach($scopes as $scope)
                    <li><span class="dot"></span><span>{{ $scope->description }}</span></li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="actions">
        <form method="POST" action="{{ route('passport.authorizations.deny') }}">
            @csrf
            @method('DELETE')
            <input type="hidden" name="state" value="">
            <input type="hidden" name="client_id" value="{{ $client->id }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit" class="btn-cancel">Cancel</button>
        </form>

        <form method="POST" action="{{ route('passport.authorizations.approve') }}" id="authorizeForm">
            @csrf
            <input type="hidden" name="state" value="">
            <input type="hidden" name="client_id" value="{{ $client->id }}">
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit" class="btn-approve" id="authorizeButton">
                <span id="authorizeText">Authorize</span>
                <svg id="loadingSpinner" class="spinner" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity="0.25"/>
                    <path fill="currentColor" opacity="0.75"
                          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
            </button>
        </form>
    </div>
</div>

<script>
    document.getElementById('authorizeForm').addEventListener('submit', function () {
        const button = document.getElementById('authorizeButton');
        button.disabled = true;
        document.getElementById('authorizeText').textContent = 'Authorizing…';
        document.getElementById('loadingSpinner').classList.add('show');
    });
</script>
</body>
</html>
