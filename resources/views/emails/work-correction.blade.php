@php
    use App\Models\WorkCorrection;
    $c = $correction;
    $u = $c->user;
    $reviewUrl = route(($c->reviewer?->role->portal() === 'manager' ? 'manager' : 'admin').'.corrections.show', $c);
@endphp
<!DOCTYPE html>
<html>
<body style="margin:0;background:#F7F2EA;font-family:Arial,Helvetica,sans-serif;color:#2B2B2B">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px">
    <tr><td align="center">
        <table width="580" cellpadding="0" cellspacing="0" style="max-width:580px;background:#ffffff;border-radius:14px;overflow:hidden">
            <tr><td style="background:#1F2D2A;color:#ffffff;padding:18px 24px">
                <div style="font-size:18px;font-weight:bold">Work Data Correction — Review Needed</div>
                <div style="font-size:13px;color:#C9D6D1">Work date: {{ $c->session->work_date->format('l, d M Y') }} · Request #{{ $c->id }}</div>
            </td></tr>
            <tr><td style="padding:20px 24px">
                <p style="margin:0 0 14px">Hello {{ $c->reviewer?->name }},<br>A correction of an employee's work data was submitted and is waiting for your approval.</p>

                <table width="100%" cellpadding="7" cellspacing="0" style="font-size:14px;border-collapse:collapse;margin-bottom:14px">
                    <tr style="border-bottom:1px solid #EBE3D7"><td style="color:#7A746C">Employee Name</td><td align="right"><b>{{ $u->name }}</b></td></tr>
                    <tr style="border-bottom:1px solid #EBE3D7"><td style="color:#7A746C">Employee ID</td><td align="right"><b>{{ $u->employee_code }}</b></td></tr>
                    <tr style="border-bottom:1px solid #EBE3D7"><td style="color:#7A746C">Department</td><td align="right"><b>{{ $u->employee?->department?->name ?? '—' }}</b></td></tr>
                    <tr style="border-bottom:1px solid #EBE3D7"><td style="color:#7A746C">Submitted By</td><td align="right"><b>{{ $c->submitter?->name }}</b> ({{ $c->submitter?->roleLabel() }}) · {{ $c->created_at->format('d M Y, h:i A') }}</td></tr>
                </table>

                <table width="100%" cellpadding="8" cellspacing="0" style="font-size:14px;border-collapse:collapse;margin-bottom:14px">
                    <tr style="background:#F8E6DD"><th align="left">Field</th><th align="right">Original Data</th><th align="right">Corrected Data</th></tr>
                    @foreach (WorkCorrection::FIELDS as $field => [$label])
                        @php $changed = (int) $c->original[$field] !== (int) $c->corrected[$field]; @endphp
                        <tr style="border-bottom:1px solid #EBE3D7">
                            <td>{{ $label }}</td>
                            <td align="right" style="color:#7A746C">{{ WorkCorrection::format($field, $c->original[$field]) }}</td>
                            <td align="right" style="{{ $changed ? 'color:#B85C3A;font-weight:bold' : '' }}">{{ WorkCorrection::format($field, $c->corrected[$field]) }}{{ $changed ? ' (changed)' : '' }}</td>
                        </tr>
                    @endforeach
                </table>

                <div style="padding:12px 14px;border-radius:10px;background:#FFF8EC;border:1px dashed #F1C98A;margin-bottom:10px">
                    <div style="font-size:12px;color:#7A4B12;font-weight:bold">Correction Reason</div>
                    <div style="font-size:14px">{{ $c->reason }}</div>
                </div>
                <div style="padding:12px 14px;border-radius:10px;background:#F4F1EC;margin-bottom:18px">
                    <div style="font-size:12px;color:#7A746C;font-weight:bold">Correct information / details</div>
                    <div style="font-size:14px">{!! nl2br(e($c->details)) !!}</div>
                </div>

                <p style="font-size:13px;color:#7A746C;margin:0 0 16px">After approval the corrected numbers replace the original ones, and the day's work score and attendance are recalculated.</p>
                <a href="{{ $reviewUrl }}" style="display:inline-block;background:#B85C3A;color:#ffffff;text-decoration:none;padding:11px 20px;border-radius:10px;font-weight:bold">Review &amp; Approve / Reject</a>
            </td></tr>
            <tr><td style="padding:12px 24px;background:#F7F2EA;font-size:12px;color:#7A746C">Employee Portal · automatic e-mail, sent only after the correction was submitted.</td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
