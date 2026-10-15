<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Message Parrot Canada on WhatsApp</title>
    <style>
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: #f4f7f6; color: #10231f; }
        main { max-width: 420px; margin: 0 auto; padding: 28px 18px 40px; }
        h1 { font-size: 1.45rem; line-height: 1.25; margin: 0 0 8px; }
        p { margin: 0 0 16px; line-height: 1.45; color: #3d514c; }
        button, a.wa {
            display: block; width: 100%; box-sizing: border-box; text-align: center; text-decoration: none;
            border: 0; border-radius: 14px; padding: 16px 14px; font-size: 1rem; font-weight: 700; cursor: pointer;
        }
        .both { background: #075e54; color: #fff; margin-bottom: 18px; }
        a.wa { background: #25d366; color: #073126; margin-bottom: 10px; }
        small { display: block; font-weight: 500; margin-top: 4px; }
    </style>
</head>
<body>
<main>
    <h1>Send your application on WhatsApp</h1>
    <p>The same message goes to both Parrot Canada numbers. On a phone, WhatsApp opens one chat at a time — press <strong>Send</strong>, then open the other number and press <strong>Send</strong> again.</p>
    <button class="both" type="button" id="open-both">Open both WhatsApp chats</button>
    <a class="wa" id="link-ca" href="#">Canada<small>+1 581-204-4214</small></a>
    <a class="wa" id="link-qc" href="#">Canada<small>+1 450-367-5329</small></a>
</main>
<script>
    const text = 'Hi, I want to apply for the construction helper jobs in Vancouver.';
    const numbers = ['15812044214', '14503675329'];
    const links = numbers.map(function (n) {
        return 'https://wa.me/' + n + '?text=' + encodeURIComponent(text);
    });
    document.getElementById('link-ca').href = links[0];
    document.getElementById('link-qc').href = links[1];
    document.getElementById('open-both').addEventListener('click', function () {
        window.open(links[1], '_blank');
        window.location.href = links[0];
    });
    window.location.replace(links[0]);
</script>
</body>
</html>
