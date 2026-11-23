<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kedai Kopi — API Reference & Playground</title>
    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
    <style>
        body { margin: 0; padding: 0; background-color: #14100d; color: #f2ead8; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .back-nav {
            background: #1c1511; padding: 12px 24px; border-bottom: 1px solid #2e231c; display: flex; align-items: center; justify-content: space-between;
        }
        .back-nav a {
            color: #c9a56f; text-decoration: none; font-weight: bold; font-size: 14px; display: inline-flex; align-items: center; gap: 8px;
        }
        .back-nav a:hover { color: #f2ead8; }
        .tag-badge {
            background: #2a1f16; color: #a58a63; border: 1px solid #3d2c20; font-size: 12px; padding: 4px 10px; border-radius: 4px;
        }
    </style>
</head>
<body>
    <header class="back-nav">
        <a href="/">← Kembali ke Aplikasi Kasir</a>
        <span class="tag-badge">OpenAPI 3.0 / Scalar Interactive Explorer</span>
    </header>

    <script id="api-reference" data-url="/openapi.json"></script>
    <script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"></script>
</body>
</html>

