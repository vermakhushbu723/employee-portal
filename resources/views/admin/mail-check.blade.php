@extends('layouts.app')

@section('title', 'Email Check')

@section('content')
<div class="mb-3">
    <h1 class="h4 fw-bold mb-0">Email Check</h1>
    <div class="small text-muted">Sends one test e-mail through each alert mailbox and shows the exact answer of the mail server.</div>
</div>

<form method="POST" action="{{ route('admin.mail-check.send') }}" class="card-soft card-pad mb-3 row g-2 align-items-end">
    @csrf
    <div class="col-md-6">
        <label class="form-label small required">Send test e-mails to</label>
        <input type="email" name="to" class="form-control" required value="{{ old('to', auth()->user()->email) }}" placeholder="you@gmail.com">
        @error('to')<div class="small text-danger">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3"><button class="btn btn-primary w-100"><i class="bi bi-send me-1"></i>Send test</button></div>
    <div class="col-12 small-xs text-muted">Check the inbox <b>and the Spam folder</b>. "Sent" here means the mail server accepted it.</div>
</form>

<div class="card-soft">
    <div class="table-responsive">
        <table class="table table-soft align-middle mb-0">
            <thead><tr><th>Mailbox</th><th>Server</th><th>Password</th><th>Result</th></tr></thead>
            <tbody>
                @foreach ($mailboxes as $box)
                    @php $r = $results[$box['mailer']] ?? null; @endphp
                    <tr>
                        <td class="small"><b>{{ $box['username'] ?: '—' }}</b><div class="small-xs text-muted">{{ $box['mailer'] }}</div></td>
                        <td class="small">{{ $box['host'] }}{{ $box['port'] ? ':'.$box['port'] : '' }} {{ $box['scheme'] ? '('.$box['scheme'].')' : '' }}</td>
                        <td class="small">@if ($box['has_password'])<span class="text-success"><i class="bi bi-check-circle"></i> set</span>@else<span class="text-danger"><i class="bi bi-x-circle"></i> missing</span>@endif</td>
                        <td class="small">
                            @if (! $r)<span class="text-muted">Not tested</span>
                            @elseif ($r['ok'])<x-badge tone="success"><i class="bi bi-check-circle-fill"></i> Sent</x-badge> <span class="text-muted">{{ $r['message'] }}</span>
                            @else<x-badge tone="danger"><i class="bi bi-x-circle-fill"></i> Failed</x-badge><div class="text-danger small-xs mt-1 text-break">{{ $r['message'] }}</div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
