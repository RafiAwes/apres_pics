<!DOCTYPE html>
<html>

<head>
    <title>Event Invitation</title>
</head>

<body style="font-family: sans-serif;">
    <h1>You are invited!</h1>
    <p>You have been invited to view the photos for a private event.</p>

    <p><strong>Step 1:</strong> Click the link below to access the event page:</p>
    <a href="{{ $link }}"
        style="display:inline-block; padding: 10px 20px; background: #3490dc; color: white; text-decoration: none; border-radius: 5px;">
        Access Event
    </a>

    <p><strong>Step 2:</strong> Use this One-Time Password (OTP) to login:</p>
    <h2 style="color: #e3342f; letter-spacing: 5px;">{{ $otp }}</h2>

    <p><em>This code expires in 30 minutes.</em></p>
</body>

</html>