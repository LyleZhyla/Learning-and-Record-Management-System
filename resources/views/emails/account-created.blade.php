<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your Smart NSTP account</title>
    <style>
        @media only screen and (max-width: 620px) {
            .email-shell { width: 100% !important; }
            .email-pad { padding-left: 22px !important; padding-right: 22px !important; }
            .credential-label, .credential-value { display: block !important; width: 100% !important; }
            .credential-label { padding-bottom: 4px !important; }
            .login-button { display: block !important; text-align: center !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background:#eef3f8; color:#243449; font-family:Arial, Helvetica, sans-serif;">
<div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent;">
    Your {{ $roleLabel }} account is ready. Use the temporary credentials in this email to sign in.
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
                                <td align="right" valign="middle">
                                    <span style="display:inline-block; padding:7px 11px; border:1px solid #7fa8d2; border-radius:999px; color:#ffffff; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.7px;">{{ $roleLabel }}</span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td class="email-pad" style="padding:38px 40px 12px;">
                        <div style="color:#2468ca; font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:1.1px;">Account ready</div>
                        <h1 style="margin:10px 0 14px; color:#163657; font-size:29px; line-height:1.25;">Welcome to Smart NSTP, {{ $recipientName }}!</h1>
                        <p style="margin:0; color:#53657a; font-size:16px; line-height:1.7;">Your {{ strtolower($roleLabel) }} account has been created. Use the temporary credentials below to access the platform.</p>
                    </td>
                </tr>

                <tr>
                    <td class="email-pad" style="padding:20px 40px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; border:1px solid #dce6f0; border-radius:14px; background:#f8fbfe;">
                            <tr>
                                <td colspan="2" style="padding:17px 20px 13px; border-bottom:1px solid #dce6f0; color:#163657; font-size:14px; font-weight:800;">Your login credentials</td>
                            </tr>
                            <tr>
                                <td class="credential-label" width="150" style="padding:17px 10px 8px 20px; color:#6a7b8e; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.6px;">Email address</td>
                                <td class="credential-value" style="padding:17px 20px 8px 10px; color:#243449; font-size:15px; font-weight:700; word-break:break-all;">{{ $accountEmail }}</td>
                            </tr>
                            <tr>
                                <td class="credential-label" width="150" style="padding:8px 10px 18px 20px; color:#6a7b8e; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.6px;">Temporary password</td>
                                <td class="credential-value" style="padding:8px 20px 18px 10px; color:#174d84; font-family:'Courier New', monospace; font-size:17px; font-weight:800; letter-spacing:.5px; word-break:break-all;">{{ $temporaryPassword }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td class="email-pad" style="padding:8px 40px 26px;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td style="border-radius:10px; background:#2468ca;">
                                    <a href="{{ $loginUrl }}" class="login-button" style="display:inline-block; padding:14px 24px; color:#ffffff; font-size:15px; font-weight:800; text-decoration:none;">Sign in to Smart NSTP&nbsp; →</a>
                                </td>
                            </tr>
                        </table>
                        <p style="margin:18px 0 0; color:#7a8999; font-size:12px; line-height:1.6;">If the button does not work, copy and paste this address into your browser:<br><a href="{{ $loginUrl }}" style="color:#2468ca; word-break:break-all;">{{ $loginUrl }}</a></p>
                    </td>
                </tr>

                <tr>
                    <td class="email-pad" style="padding:0 40px 32px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; background:#fff8e6; border-left:4px solid #f2b84b; border-radius:8px;">
                            <tr>
                                <td style="padding:14px 16px; color:#624b18; font-size:13px; line-height:1.6;"><strong>Keep your account secure.</strong> You will be asked to create a new password after your first sign-in. Never share your password with anyone.</td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td class="email-pad" style="padding:22px 40px; background:#f5f8fb; border-top:1px solid #e3eaf1; color:#7a8999; font-size:12px; line-height:1.6;">
                        This automated message was sent by Snapie for the Smart NSTP Management and AI-Integrated Platform. Please do not reply with your password.<br>
                        <span style="color:#9aa7b5;">© {{ date('Y') }} Smart NSTP. All rights reserved.</span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
