<!DOCTYPE html>
<html lang="en" @class(['dark' => $dark])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Chart</title>
    <script>if (new URLSearchParams(location.search).has('dark')) document.documentElement.classList.add('dark');</script>
    <style>
        /* A frame whose color scheme differs from the page's gets an opaque canvas; match it so the frame stays transparent */
        html { color-scheme: light; }
        html.dark { color-scheme: dark; }
        html, body { margin: 0; background: transparent; font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif; }
        body { padding: 8px; }
        [data-phiz-view] { display: flex; justify-content: center; }
    </style>
    {!! $scripts !!}
</head>
<body>
    {!! $container !!}
    <script>
        // Tell the page how tall the chart is. The page checks that the message comes from this frame.
        new ResizeObserver(() => parent.postMessage({ chartHeight: document.documentElement.scrollHeight }, '*')).observe(document.body);
    </script>
</body>
</html>
