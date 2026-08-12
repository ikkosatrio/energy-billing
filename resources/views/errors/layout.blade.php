<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="light">
  <title>@yield('title') — {{ setting('app_name', config('app.name')) }}</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ config('app.version') }}">
</head>
<body>

<div class="login-page">
  <div class="login-card" style="text-align:center">
    <div style="font-family:var(--font-mono);font-size:56px;font-weight:800;color:var(--primary);line-height:1">
      @yield('code')
    </div>
    <div style="font-size:18px;font-weight:700;margin-top:10px">@yield('title')</div>
    <p style="font-size:13px;color:var(--text-muted);margin:10px 0 24px">@yield('message')</p>

    <a href="{{ url('/') }}" class="btn btn-primary" style="justify-content:center;width:100%">
      <i data-lucide="arrow-left" style="width:15px;height:15px"></i>
      Kembali ke Dashboard
    </a>
  </div>
</div>

<script src="https://unpkg.com/lucide@0.462.0/dist/umd/lucide.js"></script>
<script>lucide.createIcons();</script>
</body>
</html>
