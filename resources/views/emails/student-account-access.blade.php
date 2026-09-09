<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your Smart NSTP student account</title>
    <style>
        @media only screen and (max-width: 620px) {
            .email-shell { width: 100% !important; }
            .email-pad { padding-left: 22px !important; padding-right: 22px !important; }
            .setup-button { display: block !important; text-align: center !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background:#eef3f8; color:#243449; font-family:Arial, Helvetica, sans-serif;">
<div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent;">
    Your Smart NSTP student account is ready. Set your password to access the platform.
</div>

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; background:#eef3f8;">
    <tr>
        <td align="center" style="padding:32px 12px;">
            <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" class="email-shell" style="width:600px; max-width:600px; background:#ffffff; border-radius:18px; overflow:hidden; box-shadow:0 10px 30px rgba(23,77,132,.10);">
                <tr>
                    <td class="email-pad" style="padding:24px 40px; background:#174d84;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td width="54" valign="middle">
                                    <table role="presentation" width="46" height="46" cellspacing="0" cellpadding="0" border="0" style="width:46px; height:46px; background:#ffffff; border-radius:14px;">
                                        <tr><td align="center" valign="middle" style="color:#174d84; font-size:20px; font-weight:800;">S</td></tr>
                                    </table>
                                </td>
                                <td valign="middle" style="padding-left:12px; color:#ffffff;">
                                    <div style="font-size:20px; font-weight:800; line-height:1.2;">SNAPIE</div>
                                    <div style="padding-top:3px; color:#dceaff; font-size:12px; line-height:1.3;">Smart NSTP Management Platform</div>
                                </td>
                                <td align="right" valign="middle"><span style="display:inline-block; padding:7px 11px; border:1px solid #7fa8d2; border-radius:999px; color:#ffffff; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.7px;">Student</span></td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td class="email-pad" style="padding:30px 40px 12px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td valign="middle" style="padding-right:16px;">
                                    <div style="color:#2468ca; font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:1.1px;">Account invitation</div>
                                    <h1 style="margin:10px 0 14px; color:#163657; font-size:29px; line-height:1.25;">Your student account is ready, {{ $recipientName }}!</h1>
                                    <p style="margin:0; color:#53657a; font-size:16px; line-height:1.7;">An administrator has invited you to access the Smart NSTP platform. Confirm your account and choose a secure password to get started.</p>
                                </td>
                                <td width="142" align="right" valign="bottom" style="width:142px;">
                                    <img src="{{ $message->embed(public_path('images/characters/snapie-email-wave.png')) }}" width="132" alt="Snapie waving hello" style="display:block; width:132px; max-width:132px; height:auto; border:0;">
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td class="email-pad" style="padding:20px 40px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; border:1px solid #dce6f0; border-radius:14px; background:#f8fbfe;">
                            <tr><td style="padding:14px 18px; color:#6a7b8e; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.6px;">Registered email address</td></tr>
                            <tr><td style="padding:0 18px 16px; color:#243449; font-size:16px; font-weight:800; word-break:break-all;">{{ $accountEmail }}</td></tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td class="email-pad" style="padding:8px 40px 28px;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                            <tr><td style="border-radius:10px; background:#2468ca;"><a href="{{ $setupUrl }}" class="setup-button" style="display:inline-block; padding:14px 24px; color:#ffffff; font-size:15px; font-weight:800; text-decoration:none;">Set password &amp; access account&nbsp; →</a></td></tr>
                        </table>
                        <p style="margin:18px 0 0; color:#7a8999; font-size:12px; line-height:1.6;">This secure link expires in {{ config('auth.passwords.users.expire', 60) }} minutes. If the button does not work, copy and paste this address into your browser:<br><a href="{{ $setupUrl }}" style="color:#2468ca; word-break:break-all;">{{ $setupUrl }}</a></p>
                    </td>
                </tr>

                <tr>
                    <td class="email-pad" style="padding:0 40px 32px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; background:#fff8e6; border-left:4px solid #f2b84b; border-radius:8px;">
                            <tr><td style="padding:14px 16px; color:#624b18; font-size:13px; line-height:1.6;"><strong>Didn’t expect this email?</strong> You can safely ignore it. Never forward this link or share your password with anyone.</td></tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td class="email-pad" style="padding:22px 40px; background:#f5f8fb; border-top:1px solid #e3eaf1; color:#7a8999; font-size:12px; line-height:1.6;">
                        This automated message was sent by Snapie for the Smart NSTP Management and AI-Integrated Platform.<br>
                        <span style="color:#9aa7b5;">© {{ date('Y') }} Smart NSTP. All rights reserved.</span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
