<!DOCTYPE html>
{{-- Shown inside a dialog after a successful save: the window behind the dialog moves on. --}}
<html lang="en">
<head><meta charset="utf-8"><title>Saved</title></head>
<body>
    <p><a href="{{ $url }}" target="_top">Continue</a></p>
    <script>
        (function () {
            var url = @json($url);
            if (window.parent !== window) {
                window.parent.postMessage({ type: 'sies-dialog-done', url: url }, window.location.origin);
            } else {
                window.location.replace(url);
            }
        })();
    </script>
</body>
</html>
