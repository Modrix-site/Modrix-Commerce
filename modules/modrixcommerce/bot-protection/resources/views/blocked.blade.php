<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Access Denied</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #090d16;
            --card-bg: rgba(17, 24, 39, 0.85);
            --border: rgba(239, 68, 68, 0.25);
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
            --danger: #ef4444;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', system-ui, sans-serif; }
        body {
            background-color: var(--bg);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background-image: radial-gradient(circle at 50% 20%, rgba(239, 68, 68, 0.15), transparent 50%);
        }
        .blocked-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 1.25rem;
            max-width: 520px;
            width: 100%;
            padding: 2.5rem 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 35px rgba(239, 68, 68, 0.2);
            text-align: center;
        }
        .icon-wrap {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            color: var(--danger);
        }
        h1 { font-size: 1.6rem; font-weight: 700; margin-bottom: 0.5rem; }
        p { color: var(--text-muted); font-size: 0.95rem; line-height: 1.6; margin-bottom: 1.5rem; }
        .details-box {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 0.75rem;
            padding: 1rem;
            text-align: left;
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
        }
        .row { display: flex; justify-content: space-between; margin-bottom: 0.5rem; }
        .row:last-child { margin-bottom: 0; }
        .row span:first-child { color: var(--text-muted); }
        .row span:last-child { font-weight: 600; font-family: monospace; }
        .footer-note { font-size: 0.8rem; color: #6b7280; }
    </style>
</head>
<body>
    <div class="blocked-card">
        <div class="icon-wrap">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
            </svg>
        </div>

        <h1>403 - Request Blocked</h1>
        <p>Access to this website has been temporarily restricted. Your connection triggered our automated Bot Protection defense system.</p>

        <div class="details-box">
            <div class="row">
                <span>Client IP:</span>
                <span>{{ $ip }}</span>
            </div>
            <div class="row">
                <span>Threat Score:</span>
                <span style="color: #ef4444;">{{ $score }} / 100</span>
            </div>
            <div class="row">
                <span>Incident Ray ID:</span>
                <span>{{ $ray_id ?? 'ray_' . substr(md5($ip . time()), 0, 12) }}</span>
            </div>
        </div>

        <div class="footer-note">
            If you believe this is a false positive, please contact the site administrator with your Incident Ray ID.
        </div>
    </div>
</body>
</html>
