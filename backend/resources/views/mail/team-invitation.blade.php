<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitation à rejoindre {{ $invitation->team->name }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8f8f8; margin: 0; padding: 40px 20px; }
        .container { max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #7367F0, #9055FD); padding: 40px 40px 32px; text-align: center; }
        .header .emoji { font-size: 48px; display: block; margin-bottom: 16px; }
        .header h1 { color: #fff; font-size: 24px; font-weight: 700; margin: 0; }
        .body { padding: 40px; }
        .body p { color: #5E5873; font-size: 15px; line-height: 1.6; margin: 0 0 16px; }
        .badge { display: inline-block; background: #F3F2F7; color: #7367F0; font-size: 13px; font-weight: 600; padding: 4px 12px; border-radius: 20px; margin: 0 4px; }
        .btn { display: block; width: fit-content; margin: 32px auto; padding: 14px 36px; background: linear-gradient(135deg, #7367F0, #9055FD); color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none; border-radius: 8px; box-shadow: 0 4px 16px rgba(115,103,240,0.4); }
        .footer { padding: 24px 40px; border-top: 1px solid #EBE9F1; text-align: center; }
        .footer p { color: #B9B9C3; font-size: 12px; margin: 0; }
        .footer a { color: #7367F0; word-break: break-all; }
        .expires { color: #B9B9C3; font-size: 13px; text-align: center; margin-top: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <span class="emoji">🎧</span>
            <h1>Whisper CRM</h1>
        </div>
        <div class="body">
            <p>Bonjour,</p>
            <p>
                <strong>{{ $invitation->inviter->name }}</strong> vous invite à rejoindre le cabinet
                <span class="badge">{{ $invitation->team->name }}</span>
                en tant que <span class="badge">{{ ucfirst($invitation->role) }}</span>.
            </p>
            <p>Cliquez sur le bouton ci-dessous pour accepter l'invitation et accéder au CRM :</p>

            <a href="{{ $acceptUrl }}" class="btn">Accepter l'invitation</a>

            <p class="expires">Cette invitation expire le {{ $invitation->expires_at->format('d/m/Y à H:i') }}.</p>
        </div>
        <div class="footer">
            <p>Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :</p>
            <p><a href="{{ $acceptUrl }}">{{ $acceptUrl }}</a></p>
            <p style="margin-top: 16px;">Si vous n'attendiez pas cette invitation, ignorez cet email.</p>
        </div>
    </div>
</body>
</html>
