{{--
    Swagger UI dengan identitas Energy Billing.

    Berkas ini hasil publish dari paket l5-swagger, lalu disesuaikan:
      - topbar bawaan Swagger (logo + kolom URL) disembunyikan lewat CSS,
        diganti header aplikasi di bawah
      - memakai app.css untuk token warna & font, plus swagger-theme.css
        yang menimpa gaya bawaan Swagger UI
      - layout BaseLayout (bukan StandaloneLayout) karena topbar tidak dipakai

    Bila paket di-update dan berkas ini di-publish ulang, penyesuaian di atas
    perlu diterapkan kembali.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>Dokumentasi API — {{ setting('app_name', config('app.name')) }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    <link rel="stylesheet" type="text/css" href="{{ l5_swagger_asset($documentation, 'swagger-ui.css') }}">
    {{-- app.css dimuat lebih dulu karena hanya dipakai sebagai sumber token
         (variabel :root); swagger-theme.css yang menerapkannya ke Swagger UI. --}}
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ config('app.version') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/swagger-theme.css') }}?v={{ config('app.version') }}">

    <link rel="icon" type="image/png" href="{{ l5_swagger_asset($documentation, 'favicon-32x32.png') }}" sizes="32x32"/>
    <link rel="icon" type="image/png" href="{{ l5_swagger_asset($documentation, 'favicon-16x16.png') }}" sizes="16x16"/>
</head>

<body>

<header class="docs-header">
    <div class="mark">
        @if ($logo = setting('company_logo'))
            <img src="{{ \Illuminate\Support\Facades\Storage::url($logo) }}" alt="{{ setting('company_name') }}">
        @else
            <i data-lucide="zap"></i>
        @endif
    </div>
    <div>
        <div class="name">{{ setting('app_name', config('app.name')) }}</div>
        <div class="sub">Dokumentasi API · {{ setting('company_name') }}</div>
    </div>
    <a href="{{ route('dashboard') }}" class="back">
        <i data-lucide="arrow-left" style="width:15px;height:15px"></i>
        <span>Kembali ke Aplikasi</span>
    </a>
</header>

<div id="swagger-ui"></div>

<script src="https://unpkg.com/lucide@0.462.0/dist/umd/lucide.js"></script>
<script src="{{ l5_swagger_asset($documentation, 'swagger-ui-bundle.js') }}"></script>
<script src="{{ l5_swagger_asset($documentation, 'swagger-ui-standalone-preset.js') }}"></script>
<script>
    window.onload = function() {
        lucide.createIcons();

        const ui = SwaggerUIBundle({
            dom_id: '#swagger-ui',
            url: "{!! $urlToDocs !!}",
            operationsSorter: {!! isset($operationsSorter) ? '"' . $operationsSorter . '"' : 'null' !!},
            configUrl: {!! isset($configUrl) ? '"' . $configUrl . '"' : 'null' !!},
            validatorUrl: {!! isset($validatorUrl) ? '"' . $validatorUrl . '"' : 'null' !!},
            oauth2RedirectUrl: "{{ route('l5-swagger.'.$documentation.'.oauth2_callback', [], $useAbsolutePath) }}",

            requestInterceptor: function(request) {
                request.headers['X-CSRF-TOKEN'] = '{{ csrf_token() }}';
                return request;
            },

            presets: [
                SwaggerUIBundle.presets.apis,
                SwaggerUIStandalonePreset
            ],

            plugins: [
                SwaggerUIBundle.plugins.DownloadUrl
            ],

            // BaseLayout tidak merender topbar bawaan Swagger; identitas
            // aplikasi sudah ditampilkan lewat <header> di atas.
            layout: "BaseLayout",
            docExpansion : "{!! config('l5-swagger.defaults.ui.display.doc_expansion', 'none') !!}",
            deepLinking: true,
            filter: {!! config('l5-swagger.defaults.ui.display.filter') ? 'true' : 'false' !!},
            persistAuthorization: "{!! config('l5-swagger.defaults.ui.authorization.persist_authorization') ? 'true' : 'false' !!}",
        })

        window.ui = ui

        @if(in_array('oauth2', array_column(config('l5-swagger.defaults.securityDefinitions.securitySchemes'), 'type')))
        ui.initOAuth({
            usePkceWithAuthorizationCodeGrant: "{!! (bool)config('l5-swagger.defaults.ui.authorization.oauth2.use_pkce_with_authorization_code_grant') !!}"
        })
        @endif
    }
</script>
</body>
</html>
