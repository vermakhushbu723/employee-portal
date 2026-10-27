<!DOCTYPE html>
<html>
<body style="margin:0;background:#F7F2EA;font-family:Arial,Helvetica,sans-serif;color:#2B2B2B">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px">
    <tr><td align="center">
        <table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:14px;overflow:hidden">
            <tr><td style="padding:22px 24px;font-size:14px;line-height:1.6">{!! nl2br(e($text)) !!}</td></tr>
            <tr><td style="padding:12px 24px;background:#F7F2EA;font-size:12px;color:#7A746C">{{ $sender->name }} · {{ config('portal.company') }}</td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
