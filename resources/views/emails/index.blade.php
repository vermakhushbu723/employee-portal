@extends('layouts.app')

@section('title', 'Email Templates')

@section('content')
@php $cats = \App\Models\EmailTemplate::CATEGORIES; @endphp
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div class="flex-grow-1">
        <h1 class="h4 fw-bold mb-0">Send Email / Email Templates</h1>
        <div class="small text-muted">Templates appear in every <b>Send Email</b> popup (Meetings, My Leads). Placeholders: {{ implode(' ', \App\Models\EmailTemplate::PLACEHOLDERS) }}</div>
    </div>
    <button type="button" class="btn btn-light" data-email-compose='{}'><i class="bi bi-send me-1"></i>Send Email</button>
    <button type="button" class="btn btn-primary" data-modal="#templateModal" data-action="{{ route('admin.emails.templates.store') }}" data-fill='{"name":"","category":"meeting","subject":"","body":"","template_title":"New template"}'><i class="bi bi-plus-lg me-1"></i>New Template</button>
</div>

<div class="tpl-cats mb-3">
    <a href="{{ route('admin.emails.index') }}" @class(['tpl-cat', 'active' => ! $category])><i class="bi bi-collection-fill"></i><span>All Templates<b>{{ $total }}</b></span></a>
    @foreach ($cats as $key => [$clabel, $cicon, $ctone])
        <a href="{{ route('admin.emails.index', ['category' => $key]) }}" @class(['tpl-cat', 'tone-'.$ctone, 'active' => $category === $key])><i class="bi bi-{{ $cicon }}"></i><span>{{ $clabel }}<b>{{ $counts[$key] ?? 0 }}</b></span></a>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card-soft">
            @forelse ($templates as $t)
                @php [$clabel, $cicon, $ctone] = $t->categoryMeta(); $tplFill = ['name' => $t->name, 'category' => $t->category, 'subject' => $t->subject, 'body' => $t->body, 'template_title' => 'Edit template']; $sendFill = ['template' => $t->name]; @endphp
                <div class="tpl-row @if (! $t->active) opacity-50 @endif">
                    <span class="icon-box tone-{{ $ctone }}"><i class="bi bi-{{ $cicon }}"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-600">{{ $t->name }} <x-badge :tone="$ctone">{{ $clabel }}</x-badge>@unless ($t->active) <x-badge tone="secondary">Off</x-badge>@endunless</div>
                        <div class="small text-muted text-truncate">{{ $t->subject }}</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-light" data-email-compose='@json($sendFill)' title="Send with this template"><i class="bi bi-send"></i></button>
                    <button type="button" class="btn btn-sm btn-light" data-modal="#templateModal" data-action="{{ route('admin.emails.templates.update', $t) }}" data-fill='@json($tplFill)' data-active="{{ $t->active ? 1 : 0 }}" title="Edit"><i class="bi bi-pencil"></i></button>
                    <form method="POST" action="{{ route('admin.emails.templates.destroy', $t) }}" data-confirm="Delete template {{ $t->name }}?">@csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger" aria-label="Delete {{ $t->name }}"><i class="bi bi-trash"></i></button></form>
                </div>
            @empty
                <x-empty-state icon="envelope" title="No templates here" text="Add one with New Template." />
            @endforelse
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card-soft">
            <div class="card-pad pb-2"><h3 class="section-title mb-0">Recently sent from the portal</h3></div>
            @forelse ($history as $h)
                <div class="tpl-row">
                    <span class="icon-box sm tone-{{ $h->status === 'sent' ? 'success' : 'danger' }}"><i class="bi bi-envelope{{ $h->status === 'sent' ? '-check' : '-x' }}"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="small fw-600 text-truncate">{{ $h->subject }}</div>
                        <div class="small-xs text-muted text-truncate">To {{ $h->to }} · {{ $h->user?->name }} · {{ $h->created_at->format('d M, h:i A') }}{{ $h->status === 'sent' ? '' : ' · not sent' }}</div>
                    </div>
                </div>
            @empty
                <div class="small text-muted card-pad pt-0">Nothing sent yet.</div>
            @endforelse
        </div>
    </div>
</div>

{{-- New / edit template --}}
<div class="modal fade" id="templateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" action="" class="modal-content rounded-4 border-0" data-template-form>
            @csrf
            <input type="hidden" name="_method" value="POST" data-template-method>
            <div class="modal-header border-0"><h5 class="modal-title fw-bold" data-field="template_title">Template</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body pt-0 row g-2">
                <div class="col-md-7"><label class="form-label small required">Name</label><input name="name" class="form-control" required maxlength="120" placeholder="e.g. Interview Invitation"></div>
                <div class="col-md-5"><label class="form-label small required">Category</label>
                    <select name="category" class="form-select">@foreach ($cats as $key => [$clabel])<option value="{{ $key }}">{{ $clabel }}</option>@endforeach</select></div>
                <div class="col-12"><label class="form-label small required">Subject</label><input name="subject" class="form-control" required maxlength="200"></div>
                <div class="col-12"><label class="form-label small required">Message</label><textarea name="body" rows="10" class="form-control" required maxlength="10000"></textarea>
                    <div class="small-xs text-muted mt-1">Placeholders: {{ implode(' ', \App\Models\EmailTemplate::PLACEHOLDERS) }} — filled in when the e-mail is written.</div></div>
                <div class="col-12"><input type="hidden" name="active" value="0"><label class="form-check small"><input type="checkbox" name="active" value="1" class="form-check-input" checked> Active (shown in Send Email)</label></div>
            </div>
            <div class="modal-footer border-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save template</button></div>
        </form>
    </div>
</div>

@include('partials.email-compose', ['composeTemplates' => $composeTemplates])

@push('scripts')
<script>
// Edit = PATCH, new = POST (same popup)
document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-modal="#templateModal"]');
    if (!b) return;
    const isEdit = !b.dataset.action.endsWith('/templates');
    document.querySelector('[data-template-method]').value = isEdit ? 'PATCH' : 'POST';
    document.querySelector('[data-template-form] input[type=checkbox][name=active]').checked = !isEdit || b.dataset.active === '1';
}, true);
</script>
@endpush
@endsection
