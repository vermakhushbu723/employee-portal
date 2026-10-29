<!DOCTYPE html>
<html>
<body style="margin:0;background:#F7F2EA;font-family:Arial,Helvetica,sans-serif;color:#2B2B2B">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px">
    <tr><td align="center">
        <table width="480" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border-radius:14px;overflow:hidden">
            <tr><td style="padding:24px;font-size:14px;line-height:1.6">
                Hi {{ $name }},<br><br>
                Use this code to connect this e-mail ID to your {{ config('portal.company') }} portal account:
                <div style="margin:18px 0;font-size:30px;font-weight:bold;letter-spacing:8px;color:#B85C38;text-align:center">{{ $code }}</div>
                The code works for 10 minutes. If you did not ask for it, ignore this e-mail.
            </td></tr>
            <tr><td style="padding:12px 24px;background:#F7F2EA;font-size:12px;color:#7A746C">{{ config('portal.company') }} · Employee Portal</td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
