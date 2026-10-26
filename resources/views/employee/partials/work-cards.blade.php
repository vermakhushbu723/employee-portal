{{-- Work given by admin / manager: calling tasks (shared file) and lead-creation tasks. $cards from WorkAssignmentService::cardsFor() --}}
@php
    $callingCount = $cards->where('task.type', 'calling')->count();
    $leadCount = $cards->where('task.type', 'lead_create')->count();
@endphp

@if ($cards->isEmpty())
    <div class="card-soft card-pad mb-3">
        <x-empty-state icon="clipboard-check" title="No work assigned right now" text="When admin or your manager shares a file or gives a lead task, it appears here with its progress." />
    </div>
@else
    <div class="row g-3 mb-3">
        @foreach ($cards as $card)
            @php $t = $card['task']; $p = $card['p']; $calling = $t->type === 'calling'; @endphp
            <div class="col-lg-6">
                <div class="work-card {{ $calling ? 'tone-success' : 'tone-purple' }}">
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <span class="icon-box lg"><i class="bi bi-{{ $calling ? 'telephone-fill' : 'file-earmark-plus-fill' }}"></i></span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="wc-title">{{ $calling ? 'Calling Task' : 'Create Lead Task' }}</div>
                            <div class="small text-muted">{{ $calling ? 'Call the given data and find interested customers' : 'Find interested customers and create new leads' }}</div>
                        </div>
                        @if ($p['done'])
                            <x-badge tone="success"><i class="bi bi-check-circle-fill"></i> Completed</x-badge>
                        @else
                            <x-badge :tone="$calling ? 'success' : 'purple'">{{ $calling ? $callingCount : $leadCount }} {{ $calling ? 'File' : 'Task' }}{{ ($calling ? $callingCount : $leadCount) > 1 ? 's' : '' }} Assigned</x-badge>
                        @endif
                    </div>

                    <div class="wc-file">
                        <i class="bi bi-file-earmark-spreadsheet-fill"></i>
                        <div class="min-w-0">
                            <div class="fw-bold text-truncate">{{ $t->title }}</div>
                            <div class="small text-muted">
                                {{ $calling ? 'Total Records: '.$p['total'] : 'Total Target: '.$t->lead_target.' Leads' }}
                                · Assigned: {{ $t->assigned_at->format('d M Y') }}{{ $t->assigner ? ' by '.$t->assigner->name : '' }}
                            </div>
                        </div>
                    </div>

                    @if ($t->message)
                        <div class="wc-message">
                            <i class="bi bi-chat-square-text-fill"></i>
                            <div><div class="fw-600 small">Work Details (From {{ $t->assigner?->role->portal() === 'manager' ? 'Manager' : 'Admin' }})</div><div class="small">{!! nl2br(e($t->message)) !!}</div></div>
                        </div>
                    @endif

                    <div class="small fw-600 mb-2">Your Progress</div>
                    @if ($calling)
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <div class="d-flex justify-content-between small"><span>Called</span><b>{{ $p['called'] }} / {{ $p['total'] }}</b></div>
                                <x-progress class="mt-1" :value="$p['called_pct']" tone="success" />
                                <div class="small-xs text-muted text-end">{{ $p['called_pct'] }}%</div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex justify-content-between small"><span>Interested</span><b>{{ $p['interested'] }} / {{ $t->interested_target ?: '—' }}</b></div>
                                <x-progress class="mt-1" :value="$p['interested_pct']" tone="warning" />
                                <div class="small-xs text-muted text-end">{{ $t->interested_target ? $p['interested_pct'].'%' : 'No target' }}</div>
                            </div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6"><div class="wc-pending tone-danger"><i class="bi bi-telephone-x"></i><div><span>Pending to Call</span><b>{{ $p['pending_call'] }}</b></div></div></div>
                            <div class="col-6"><div class="wc-pending tone-warning"><i class="bi bi-people"></i><div><span>Pending Interested</span><b>{{ $p['pending_interested'] }}</b></div></div></div>
                        </div>
                        <a href="{{ route('employee.leads.index', ['tab' => 'new', 'batch' => $t->batch_id]) }}" class="btn btn-success w-100"><i class="bi bi-telephone-fill me-1"></i>Start Calling</a>
                    @else
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small"><span>Leads Created</span><b>{{ $p['created'] }} / {{ $t->lead_target }}</b></div>
                            <x-progress class="mt-1" :value="$p['created_pct']" tone="purple" />
                            <div class="small-xs text-muted text-end">{{ $p['created_pct'] }}%</div>
                        </div>
                        <div class="wc-pending tone-danger mb-3"><i class="bi bi-file-earmark-x"></i><div><span>Pending to Create</span><b>{{ $p['pending_create'] }}</b></div></div>
                        <a href="{{ route('employee.leads.create') }}" class="btn w-100 text-white" style="background: var(--purple)"><i class="bi bi-file-earmark-plus me-1"></i>Create Lead</a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif
