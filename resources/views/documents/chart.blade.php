<!DOCTYPE html>
<html lang="en" @class(['dark' => $dark])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Chart</title>
    <style>
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
