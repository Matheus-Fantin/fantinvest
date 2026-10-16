<!DOCTYPE html>
<html lang="pt-BR" translate="no">
<head>
    @include('partials.head')
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <a href="/" class="brand">
            <span class="brand-mark"><x-icone nome="logo" width="16" height="16" stroke-width="2.4" /></span>
            <span class="brand-name">FantInvest</span>
        </a>
        <div class="card">
            {{ $slot }}
        </div>
    </div>
</div>
</body>
</html>
