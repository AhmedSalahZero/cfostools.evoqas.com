@php
    /** @var \Illuminate\Contracts\Auth\CanResetPassword $user */
    /** @var string $resetUrl */
    /** @var string $appName */
    $logoUrl = config('mail.brand.logo_url') ?? rtrim(config('app.url'), '/').'/images/logo13.png';
    $expireMinutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $appName }} – Reset Password</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        body, table, td, a {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        body {
            margin: 0;
            padding: 0;
            background-color: #0f172a;
        }
        a {
            text-decoration: none;
            color: #2563eb;
        }
        .wrapper {
            width: 100%;
            table-layout: fixed;
            background-color: #0f172a;
            padding: 24px 0;
        }
        .main {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            background-color: #020617;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #1e293b;
        }
        .header {
            padding: 24px 32px;
            background: linear-gradient(135deg, #1e3a8a 0%, #020617 100%);
            text-align: left;
        }
        .logo {
            display: inline-block;
        }
        .logo img {
            max-height: 40px;
            width: auto;
            display: block;
            border: 0;
            outline: none;
        }
        .body {
            padding: 28px 32px 32px;
            color: #e2e8f0;
            font-size: 15px;
            line-height: 1.6;
        }
        .headline {
            font-size: 20px;
            font-weight: 600;
            margin: 0 0 16px 0;
            color: #f8fafc;
        }
        .body p {
            margin: 0 0 12px 0;
        }
        .button-wrapper {
            margin: 24px 0;
            text-align: left;
        }
        .button {
            display: inline-block;
            padding: 14px 28px;
            background-color: #2563eb;
            color: #ffffff !important;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            border: none;
        }
        .muted {
            color: #94a3b8;
            font-size: 13px;
        }
        .fallback-url {
            word-break: break-all;
            font-size: 12px;
            color: #94a3b8;
            margin-top: 8px;
        }
        .footer {
            padding: 20px 32px 28px;
            text-align: center;
            font-size: 12px;
            color: #64748b;
            border-top: 1px solid #1e293b;
        }
        @media only screen and (max-width: 600px) {
            .main {
                border-radius: 0;
                border-left: none;
                border-right: none;
            }
            .header, .body, .footer {
                padding-left: 20px !important;
                padding-right: 20px !important;
            }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#0f172a;">
    <table class="wrapper" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#0f172a;">
        <tr>
            <td align="center" style="padding:24px 16px;">
                <table class="main" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;margin:0 auto;background-color:#020617;border-radius:12px;border:1px solid #1e293b;">
                    <!-- Header with logo -->
                    <tr>
                        <td class="header" style="padding:24px 32px;background:linear-gradient(135deg, #1e3a8a 0%, #020617 100%);">
                            <a href="{{ config('app.url') }}" class="logo" style="display:inline-block;">
                                <img src="{{ $logoUrl }}" alt="{{ $appName }} logo" width="160" style="max-height:40px;width:auto;display:block;border:0;">
                            </a>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td class="body" style="padding:28px 32px 32px;color:#e2e8f0;font-size:15px;line-height:1.6;">
                            <div class="headline" style="font-size:20px;font-weight:600;margin-bottom:16px;color:#f8fafc;">
                                Reset your password
                            </div>

                            <p style="margin:0 0 12px 0;">Hi {{ $user->name ?? 'there' }},</p>

                            <p style="margin:0 0 12px 0;">
                                We received a request to reset the password for your
                                <strong>{{ $appName }}</strong> account.
                                If this was you, click the button below to choose a new password.
                            </p>

                            <div class="button-wrapper" style="margin:24px 0;">
                                <a href="{{ $resetUrl }}" class="button" target="_blank" rel="noopener noreferrer" style="display:inline-block;padding:14px 28px;background-color:#2563eb;color:#ffffff !important;border-radius:8px;font-size:15px;font-weight:600;">
                                    Reset password
                                </a>
                            </div>

                            <p class="muted" style="margin:0 0 8px 0;color:#94a3b8;font-size:13px;">
                                This link will expire in {{ $expireMinutes }} minutes. If the button doesn't work, copy and paste this URL into your browser:
                            </p>

                            <p class="fallback-url" style="word-break:break-all;font-size:12px;color:#94a3b8;margin:0;">
                                {{ $resetUrl }}
                            </p>

                            <p class="muted" style="margin-top:20px;color:#94a3b8;font-size:13px;">
                                If you didn't request a password reset, you can safely ignore this email. Your password will remain unchanged.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td class="footer" style="padding:20px 32px 28px;text-align:center;font-size:12px;color:#64748b;border-top:1px solid #1e293b;">
                            © {{ now()->year }} {{ $appName }}. All rights reserved.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
