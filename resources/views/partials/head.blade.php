<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ isset($titulo) ? $titulo.' · ' : '' }}{{ config('app.name', 'FantInvest') }}</title>
<script>
    // Aplica o tema salvo antes de pintar a página (evita piscar no tema errado).
    try { const t = localStorage.getItem('fantinvest.tema'); if (t === 'escuro') document.documentElement.dataset.theme = 'dark'; if (t === 'claro') document.documentElement.dataset.theme = 'light'; } catch (e) {}
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,650&family=Figtree:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap">
@vite(['resources/css/app.css', 'resources/js/app.js'])
