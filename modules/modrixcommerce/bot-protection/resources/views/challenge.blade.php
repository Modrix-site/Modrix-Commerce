<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Verification - {{ config('app.name', 'Store') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #090d16;
            --card-bg: rgba(17, 24, 39, 0.85);
            --border: rgba(255, 255, 255, 0.1);
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
            --accent: #3b82f6;
            --accent-glow: rgba(59, 130, 246, 0.35);
            --success: #10b981;
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
            background-image: 
                radial-gradient(circle at 50% 20%, rgba(59, 130, 246, 0.15), transparent 45%),
                radial-gradient(circle at 80% 80%, rgba(139, 92, 246, 0.1), transparent 40%);
        }
        .challenge-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 1.25rem;
            max-width: 480px;
            width: 100%;
            padding: 2.25rem 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 40px var(--accent-glow);
            backdrop-filter: blur(16px);
            text-align: center;
        }
        .icon-wrap {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            background: rgba(59, 130, 246, 0.15);
            border: 1px solid rgba(59, 130, 246, 0.3);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            color: var(--accent);
            animation: pulse 2s infinite ease-in-out;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.4); }
            50% { transform: scale(1.05); box-shadow: 0 0 0 14px rgba(59, 130, 246, 0); }
        }
        h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; }
        p { color: var(--text-muted); font-size: 0.925rem; line-height: 1.5; margin-bottom: 1.75rem; }
        .interactive-box {
            background: rgba(0, 0, 0, 0.35);
            border: 1px solid var(--border);
            border-radius: 0.875rem;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .interactive-box:hover {
            border-color: var(--accent);
            background: rgba(59, 130, 246, 0.05);
        }
        .checkbox-custom {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            border: 2px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            background: rgba(255, 255, 255, 0.05);
        }
        .interactive-box.active .checkbox-custom {
            background: var(--accent);
            border-color: var(--accent);
        }
        .verify-text { font-size: 0.95rem; font-weight: 600; text-align: left; flex: 1; }
        .meta-info {
            display: flex;
            justify-content: space-between;
            color: #6b7280;
            font-size: 0.75rem;
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }
        .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="challenge-card">
        <div class="icon-wrap">
            <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/>
                <path d="m9 12 2 2 4-4"/>
            </svg>
        </div>

        <h1>Checking your browser</h1>
        <p>Please complete this quick automated security verification before continuing to {{ config('app.name', 'the store') }}.</p>

        <form id="challengeForm" method="POST" action="{{ url('/m/modrixcommerce/bot-protection/verify') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $challenge->challenge_token }}">
            <input type="hidden" name="timestamp" id="timestamp" value="{{ $clientToken['timestamp'] }}">
            <input type="hidden" name="solution" id="solution" value="{{ $clientToken['expected_solution'] }}">
            <input type="hidden" name="return_to" value="{{ $challenge->target_url }}">

            <div class="interactive-box" id="verifyBox" onclick="triggerVerification()">
                <div class="checkbox-custom" id="chk">
                    <div class="spinner" id="spin"></div>
                </div>
                <div class="verify-text">I am a human visitor</div>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                </svg>
            </div>
        </form>

        <div class="meta-info">
            <span>Ray ID: {{ substr(md5($challenge->challenge_token), 0, 16) }}</span>
            <span>Protected by Bot Protection</span>
        </div>
    </div>

    <script>
        let verified = false;
        function triggerVerification() {
            if (verified) return;
            verified = true;
            document.getElementById('chk').style.display = 'none';
            document.getElementById('spin').style.display = 'block';
            document.getElementById('verifyBox').classList.add('active');

            setTimeout(() => {
                document.getElementById('challengeForm').submit();
            }, 800);
        }

        // Automatic passive verification if supported
        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                if (!verified && navigator.webdriver === undefined) {
                    triggerVerification();
                }
            }, 1200);
        });
    </script>
</body>
</html>
