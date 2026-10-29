{{-- Print layout: plain white A4 pages saved with the browser's "Save as PDF". --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · QuoteIt</title>
    @stack('styles')
</head>

<body>
    @yield('toolbar')

    <main class="sheet">
        @yield('content')
    </main>

    @if (request('print'))
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
    @endif
</body>

</html>
