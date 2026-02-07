<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>You're Invited</title>
    <style>
        body { background-color: #f4f6f8; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; }
        .container { width: 100%; max-width: 680px; margin: 32px auto; padding: 16px; }
        .card { background: #ffffff; border-radius: 8px; box-shadow: 0 8px 24px rgba(23,32,46,0.08); overflow: hidden; }
        .header { padding: 28px 28px 0 28px; text-align: left; }
        .brand { font-weight: 700; color: #0f1724; font-size: 20px; letter-spacing: -0.2px; }
        .body { padding: 20px 28px 28px 28px; color: #374151; line-height: 1.45; }
        .lead { font-size: 16px; margin-bottom: 12px; color: #111827; }
        .btn { display: inline-block; padding: 12px 20px; background-color: #2563eb; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; }
        .password { display: inline-block; margin-top: 14px; padding: 12px 16px; background: #f8fafc; border: 1px dashed #d1d5db; border-radius: 6px; font-size: 18px; letter-spacing: 6px; color: #111827; font-weight: 700; }
        .note { margin-top: 12px; color: #6b7280; font-size: 13px; }
        .footer { padding: 16px 28px; font-size: 12px; color: #9ca3af; text-align: center; }
        @media (max-width:480px){ .container{padding:12px} .header{padding:20px} .body{padding:18px} }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <div class="brand">Apres Pics</div>
            </div>

            <div class="body">
                <p class="lead">You're invited to view photos from a private event.</p>

                <p>To get started, open the event page using the button below:</p>

                <p>
                    <a href="{{ $link }}" class="btn">Open Event</a>
                </p>

                @if(!empty($password))
                    <p style="margin-top:18px;">Use the following password to sign in:</p>
                    <div class="password">{{ $password }}</div>
                    <p class="note">Keep this password private. It grants access to event photos.</p>
                @endif

                <hr style="border:none;border-top:1px solid #eef2f7;margin:18px 0">

                <p style="color:#374151;">If the button doesn't work, copy and paste the following link into your browser:</p>
                <p style="word-break:break-all;color:#2563eb;font-size:13px">{{ $link }}</p>
            </div>

            <div class="footer">Apres Pics — Secure photo sharing for private events</div>
        </div>
    </div>
</body>
</html>