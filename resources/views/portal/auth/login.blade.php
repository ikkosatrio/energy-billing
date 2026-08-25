<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="light">
  <title>Portal Pelanggan — {{ setting('app_name', config('app.name')) }}</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset_v('assets/css/app.css') }}">
</head>
<body>

<div class="login-page">
  <div class="login-card">
    <div class="login-brand">
      <div class="sidebar-brand-mark">
        @if ($logo = setting('company_logo'))
          <img src="{{ \Illuminate\Support\Facades\Storage::url($logo) }}" alt="{{ setting('company_name') }}">
        @else
          <i data-lucide="zap" style="width:19px;height:19px"></i>
        @endif
      </div>
      <div>
        <div class="login-title">Portal Pelanggan</div>
        <div class="login-sub">{{ setting('company_name') }}</div>
      </div>
    </div>

    @if ($errors->any())
      <div class="alert alert-danger" style="margin-bottom:18px">
        {{ $errors->first() }}
      </div>
    @endif

    <form method="POST" action="{{ route('portal.login') }}">
      @csrf

      <div class="field">
        <label class="field-label" for="username">Username atau Email</label>
        <input id="username" name="username" type="text" autofocus required
               autocomplete="username"
               value="{{ old('username') }}"
               class="input{{ $errors->has('username') ? ' is-invalid' : '' }}">
      </div>

      <div class="field">
        <label class="field-label" for="password">Password</label>
        <input id="password" name="password" type="password" required
               autocomplete="current-password"
               class="input{{ $errors->has('password') ? ' is-invalid' : '' }}">
      </div>

      <label class="checkbox-row">
        <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
        <span>Ingat saya</span>
      </label>

      <button type="submit" class="btn btn-primary">
        <i data-lucide="log-in" style="width:15px;height:15px"></i>
        Masuk
      </button>
    </form>

    {{-- Tidak ada tautan "lupa password": pengaturan ulang dilakukan pengelola
         dari panel admin, jadi mengarahkan ke alur mandiri yang belum ada
         hanya membuat pelanggan menunggu email yang tidak akan datang. --}}
    <div class="login-foot">
      Lupa password? Hubungi pengelola.
      <div style="margin-top:6px">&copy; {{ date('Y') }} {{ setting('company_name') }}</div>
    </div>
  </div>
</div>

<script src="https://unpkg.com/lucide@0.462.0/dist/umd/lucide.js"></script>
<script>lucide.createIcons();</script>
</body>
</html>
