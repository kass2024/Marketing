<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Account Setup</title>

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet"/>

@vite(['resources/css/app.css','resources/js/app.js'])

</head>

<body class="bg-gray-100 font-sans">

<div class="min-h-screen flex items-center justify-center">

<div class="max-w-2xl w-full">

<div class="bg-white shadow-xl rounded-2xl p-10">

{{ $slot }}

</div>

</div>

</div>

</body>
</html>