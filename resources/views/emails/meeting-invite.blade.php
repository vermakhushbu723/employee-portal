@php $m = $meeting; $link = $m->joinLink(); @endphp
<!DOCTYPE html>
<html>
<body style="margin:0;background:#F7F2EA;font-family:Arial,Helvetica,sans-serif;color:#2B2B2B">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px">
    <tr><td align="center">
        <table width="540" cellpadding="0" cellspacing="0" style="max-width:540px;background:#ffffff;border-radius:14px;overflow:hidden">
            <tr><td style="background:#1F2D2A;color:#ffffff;padding:18px 24px">
                <div style="font-size:18px;font-weight:bold">Meeting Invitation</div>
                <div style="font-size:13px;color:#C9D6D1">{{ config('portal.company') }}</div>
            </td></tr>
            <tr><td style="padding:22px 24px">
                <div style="font-size:17px;font-weight:bold;margin-bottom:12px">{{ $m->title }}</div>
                <table width="100%" cellpadding="7" cellspacing="0" style="font-size:14px;border-collapse:collapse;margin-bottom:18px">
                    <tr style="border-bottom:1px solid #EBE3D7"><td style="color:#7A746C">Date &amp; time</td><td align="right"><b>{{ $m->scheduled_at->format('l, d M Y · h:i A') }}</b></td></tr>
                    <tr style="border-bottom:1px solid #EBE3D7"><td style="color:#7A746C">With</td><td align="right"><b>{{ $sender->name }}</b></td></tr>
                    <tr style="border-bottom:1px solid #EBE3D7"><td style="color:#7A746C">Platform</td><td align="right">{{ $m->platformLabel() }}</td></tr>
                    @if ($m->notes)<tr><td style="color:#7A746C">Notes</td><td align="right">{{ $m->notes }}</td></tr>@endif
                </table>
                @if ($link)
                    <a href="{{ $link }}" style="display:inline-block;background:#2E7D6B;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:10px;font-weight:bold">Join Meeting</a>
                    <div style="font-size:12px;color:#7A746C;margin-top:12px;word-break:break-all">{{ $link }}</div>
                @endif
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
