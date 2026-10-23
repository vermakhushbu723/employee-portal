@php
    use App\Support\Duration;
    use App\Services\WorkScoreService;
    $s = $session;
    $u = $s->user;
    [$level, $gradeLabel] = WorkScoreService::battery($work['grade']);
    $rows = [
        ['Punch in', $s->check_in_at->format('h:i A').($s->is_late ? ' (late)' : '')],
        ['Punch out', $s->check_out_at?->format('h:i A') ?? '—'],
        ['Active work time', Duration::hm($work['active']).' of '.Duration::hm($work['required'])],
        ['Not working / idle', Duration::hm($work['idle'])],
        ['Break taken', Duration::hm($work['break'])],
        ['Leads created', $work['created'].' / '.$work['targets']['leads']],
        ['Interested / Mature leads', $work['interested'].' / '.$work['targets']['interested']],
    ];
@endphp
<!DOCTYPE html>
<html>
<body style="margin:0;background:#F7F2EA;font-family:Arial,Helvetica,sans-serif;color:#2B2B2B">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px">
    <tr><td align="center">
        <table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:14px;overflow:hidden">
            <tr><td style="background:#1F2D2A;color:#ffffff;padding:18px 24px">
                <div style="font-size:18px;font-weight:bold">Daily Work Report</div>
                <div style="font-size:13px;color:#C9D6D1">{{ $s->work_date->format('l, d M Y') }}</div>
            </td></tr>
            <tr><td style="padding:20px 24px">
                <div style="font-size:16px;font-weight:bold">{{ $u->name }} <span style="color:#7A746C;font-weight:normal">({{ $u->employee_code }})</span></div>
                <div style="margin:14px 0;padding:14px;border-radius:10px;background:#F8E6DD">
                    <span style="font-size:28px;font-weight:bold;color:#B85C3A">{{ $work['score'] }}%</span>
                    <span style="font-size:15px;font-weight:bold;margin-left:8px">{{ $gradeLabel }}</span>
                    <div style="margin-top:8px;height:10px;background:#EBE3D7;border-radius:5px">
                        <div style="height:10px;width:{{ min(100, $work['score']) }}%;background:#2E7D6B;border-radius:5px"></div>
                    </div>
                </div>
                <table width="100%" cellpadding="8" cellspacing="0" style="font-size:14px;border-collapse:collapse">
                    @foreach ($rows as [$label, $value])
                        <tr style="border-bottom:1px solid #EBE3D7"><td style="color:#7A746C">{{ $label }}</td><td align="right"><b>{{ $value }}</b></td></tr>
                    @endforeach
                </table>
                @if ($s->correction_note)
                    <div style="margin-top:14px;padding:10px 12px;background:#FDEEDD;border-radius:8px;font-size:13px"><b>Corrected by admin:</b> {{ $s->correction_note }}</div>
                @endif
                <p style="font-size:12px;color:#7A746C;margin-top:18px">Score = average of active time, leads created and interested/mature leads against today's targets. Sent automatically by Employee Portal.</p>
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
