<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document d'Entrée en Relation</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
        <h2 style="color: #2c3e50; margin-top: 0;">Bonjour {{ $client->civilite }} {{ $client->nom }},</h2>
    </div>

    <div style="background-color: #ffffff; padding: 20px; border-left: 4px solid #3490dc; margin-bottom: 20px;">
        <p>Nous vous remercions de l'intérêt que vous portez à nos services.</p>

        <p>Suite à notre entretien, vous trouverez en pièce jointe votre <strong>Document d'Entrée en Relation (DER)</strong>.</p>

        <p>Ce document contient les informations relatives à votre rendez-vous :</p>
        <ul style="background-color: #f8f9fa; padding: 15px 15px 15px 35px; border-radius: 4px;">
            <li><strong>Lieu :</strong> {{ $client->der_lieu_rdv }}</li>
            <li><strong>Date :</strong> {{ \Carbon\Carbon::parse($client->der_date_rdv)->locale('fr')->isoFormat('dddd D MMMM YYYY') }}</li>
            <li><strong>Heure :</strong> {{ \Carbon\Carbon::parse($client->der_heure_rdv)->format('H\hi') }}</li>
        </ul>
    </div>

    <div style="background-color: #ffffff; padding: 20px; margin-bottom: 20px;">
        <p>Votre chargé de clientèle :</p>
        <div style="background-color: #e8f4f8; padding: 15px; border-radius: 4px; border-left: 3px solid #3490dc;">
            <strong>{{ $chargeClientele->name }}</strong><br>
            <a href="mailto:{{ $chargeClientele->email }}" style="color: #3490dc; text-decoration: none;">
                {{ $chargeClientele->email }}
            </a>
        </div>
    </div>

    <div style="background-color: #fffbea; padding: 15px; border-left: 4px solid #f39c12; margin-bottom: 20px;">
        <p style="margin: 0;"><strong>Important :</strong> Merci de lire attentivement ce document et de le conserver précieusement.</p>
    </div>

    <p style="color: #7f8c8d; font-size: 14px; margin-top: 30px;">
        Cordialement,<br>
        <strong>L'équipe</strong>
    </p>

    <hr style="border: none; border-top: 1px solid #e0e0e0; margin: 30px 0;">

    <p style="color: #95a5a6; font-size: 12px; text-align: center;">
        Cet email est envoyé automatiquement. Merci de ne pas y répondre directement.<br>
        Pour toute question, veuillez contacter {{ $chargeClientele->name }} à l'adresse {{ $chargeClientele->email }}.
    </p>
</body>
</html>
