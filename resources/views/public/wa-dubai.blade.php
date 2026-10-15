<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Message Parrot Canada about Dubai jobs</title>
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
        a.primary { outline: 3px solid #075e54; }
        small { display: block; font-weight: 500; margin-top: 4px; }
    </style>
</head>
<body>
<main>
    <h1>Apply for the Dubai jobs on WhatsApp</h1>
    <p>Dubai is the main number. The same message also goes to the Canadian number. On a phone, WhatsApp opens one chat at a time — press <strong>Send</strong> on Dubai first, then open Canada and press <strong>Send</strong> again.</p>
    <button class="both" type="button" id="open-both">Open Dubai WhatsApp first</button>
    <a class="wa primary" id="link-dubai" href="#">WhatsApp Dubai — main line<small>+971 56 613 6690</small></a>
    <a class="wa" id="link-ca" href="#">WhatsApp Canada<small>+1 450-367-5329</small></a>
</main>
<script>
    const text = 'Hi, I want to apply for the Dubai jobs.';
    const dubai = 'https://wa.me/971566136690?text=' + encodeURIComponent(text);
    const canada = 'https://wa.me/14503675329?text=' + encodeURIComponent(text);
    document.getElementById('link-dubai').href = dubai;
    document.getElementById('link-ca').href = canada;
    document.getElementById('open-both').addEventListener('click', function () {
        window.open(canada, '_blank');
        window.location.href = dubai;
    });
    window.location.replace(dubai);
</script>
</body>
</html>
