<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GEORX Verification Code</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #F8FAFC;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #0F172A;
        }
        .container {
            max-width: 560px;
            margin: 40px auto;
            background: #FFFFFF;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.08);
            border: 1px solid #E2E8F0;
        }
        .header {
            background-color: #2F7E6A;
            padding: 28px 32px;
            text-align: center;
        }
        .logo-text {
            color: #FFFFFF;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: 2px;
            margin: 0;
        }
        .logo-sub {
            color: #BFE8D6;
            font-size: 13px;
            margin: 4px 0 0 0;
            letter-spacing: 0.5px;
        }
        .content {
            padding: 36px 32px;
        }
        .greeting {
            font-size: 18px;
            font-weight: 700;
            color: #0F172A;
            margin-bottom: 12px;
        }
        .description {
            font-size: 15px;
            color: #475569;
            line-height: 1.6;
            margin-bottom: 28px;
        }
        .code-box {
            background: #E9F7F2;
            border: 2px dashed #63C6A7;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin: 24px 0;
        }
        .code-label {
            font-size: 12px;
            font-weight: 700;
            color: #2F7E6A;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .code-value {
            font-size: 36px;
            font-weight: 800;
            letter-spacing: 8px;
            color: #2F7E6A;
            font-family: 'Courier New', Courier, monospace;
            margin: 0;
        }
        .security-notice {
            background-color: #F1F5F9;
            border-radius: 8px;
            padding: 14px 16px;
            font-size: 13px;
            color: #64748B;
            line-height: 1.5;
            margin-top: 24px;
        }
        .footer {
            background-color: #F8FAFC;
            padding: 20px 32px;
            border-top: 1px solid #E2E8F0;
            text-align: center;
            font-size: 12px;
            color: #94A3B8;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="logo-text">GEORX</h1>
            <p class="logo-sub">A Medicinal Portal Hub</p>
        </div>
        <div class="content">
            <div class="greeting">Hello, {{ $recipientName }}</div>
            <div class="description">
                @if($purpose === 'registration')
                    Thank you for registering with GEORX. To verify your email address and activate your patient account, please enter the six-digit code below:
                @else
                    We received a request to verify your GEORX account. Please use the six-digit security code below to proceed:
                @endif
            </div>

            <div class="code-box">
                <div class="code-label">Verification Code</div>
                <div class="code-value">{{ $otp }}</div>
            </div>

            <div class="security-notice">
                <strong>Security Notice:</strong> This code will expire in {{ $expiresInMinutes }} minutes. Never share this code with anyone. GEORX staff will never ask for your verification code.
            </div>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} GEORX: A Medicinal Portal Hub. All rights reserved.<br>
            If you did not request this verification code, please ignore this email.
        </div>
    </div>
</body>
</html>
