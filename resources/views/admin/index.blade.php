<!doctype html>
<html lang="en-CA">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="robots" content="noindex,nofollow"><title>APF Press Administration</title>
    @vite(['resources/css/app.css', 'resources/js/admin.ts'])
</head>
<body class="admin-site">
    @php($adminUserData = $adminUser->only(['id', 'name', 'email', 'role']))
    <div id="admin-app" data-user='@json($adminUserData)'></div>
</body>
</html>
