{{-- PWA / native-app meta tags, shared by both layouts. --}}
<meta name="theme-color" content="#ffffff">
<meta name="application-name" content="Emp Portal">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Emp Portal">
<meta name="format-detection" content="telephone=no">
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('icons/favicon-32.png') }}">
<link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
<script>
    // Capture Chrome's install prompt as early as possible (used by the banner and /install page).
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        window.__installPrompt = e;
        document.dispatchEvent(new Event('installable'));
    });
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(function () {});
    }
</script>
