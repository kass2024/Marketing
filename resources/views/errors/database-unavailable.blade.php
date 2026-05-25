<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Service temporarily unavailable</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f8fafc; color: #0f172a; margin: 0; min-height: 100vh; display: grid; place-items: center; }
        .card { max-width: 32rem; background: #fff; border: 1px solid #e2e8f0; border-radius: 1rem; padding: 2rem; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08); }
        h1 { margin: 0 0 0.75rem; font-size: 1.5rem; }
        p { margin: 0 0 1rem; line-height: 1.6; color: #475569; }
        a { color: #1d4ed8; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Database temporarily unavailable</h1>
        <p>The application cannot reach MySQL right now. Login and admin features will resume once the database service is running again.</p>
        <p><a href="{{ route('login') }}">Return to login</a></p>
    </div>
</body>
</html>
