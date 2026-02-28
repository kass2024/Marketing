<?php
// data-deletion.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Data Deletion Instructions</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 900px;
            margin: 40px auto;
            background: #ffffff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        h1, h2 {
            color: #2c3e50;
        }
        p, li {
            line-height: 1.7;
            color: #555;
        }
        ul {
            padding-left: 20px;
        }
        .highlight {
            background: #eef5ff;
            padding: 15px;
            border-left: 4px solid #2d6cdf;
            margin: 20px 0;
        }
        .footer {
            margin-top: 40px;
            font-size: 14px;
            color: #888;
        }
        .email {
            font-weight: bold;
            color: #2d6cdf;
        }
    </style>
</head>
<body>

<div class="container">

    <h1>Data Deletion Instructions</h1>
    <p>Last Updated: <?php echo date("F d, Y"); ?></p>

    <p>
        This page explains how users can request deletion of their personal data
        associated with our platform and any connected Facebook or Meta services.
    </p>

    <h2>1. Requesting Data Deletion</h2>
    <p>
        If you would like us to delete your personal data, you may submit a request
        using one of the methods below:
    </p>

    <ul>
        <li>Email us at: <span class="email">infos@visaconsultantcanada.com</span></li>
        <li>Use the account deletion feature inside your dashboard (if available)</li>
    </ul>

    <div class="highlight">
        Please include your registered email address and business name
        so we can identify your account correctly.
    </div>

    <h2>2. What Data Will Be Deleted</h2>
    <p>
        Upon receiving a verified deletion request, we will permanently delete:
    </p>

    <ul>
        <li>Your account information</li>
        <li>Connected Facebook access tokens</li>
        <li>Advertising account integrations</li>
        <li>WhatsApp integration data</li>
        <li>Stored campaign-related data</li>
    </ul>

    <h2>3. Data Retention Exceptions</h2>
    <p>
        Certain information may be retained if required by law,
        for security purposes, or to prevent fraud or abuse.
    </p>

    <h2>4. Facebook & Meta Data</h2>
    <p>
        If you want to remove our application from your Facebook account directly,
        you can do so by:
    </p>

    <ul>
        <li>Going to Facebook Settings</li>
        <li>Selecting "Apps and Websites"</li>
        <li>Removing our application from your active apps</li>
    </ul>

    <p>
        Once removed, we will no longer have access to your Facebook data.
    </p>

    <h2>5. Processing Time</h2>
    <p>
        Data deletion requests are processed within 7 business days.
    </p>

    <h2>6. Contact Us</h2>
    <p>
        If you have questions regarding data deletion,
        please contact us at:
    </p>

    <p class="email">
        infos@visaconsultantcanada.com
    </p>

    <div class="footer">
        &copy; <?php echo date("Y"); ?> Your Company Name. All rights reserved.
    </div>

</div>

</body>
</html>