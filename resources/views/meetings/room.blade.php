@extends('layouts.app')

@section('title', 'Meeting')
@section('back', route($portal.'.meetings.index'))

@section('content')
@include('partials.meeting-room', [
    'm' => $m,
    'api' => \App\Http\Controllers\MeetingRoomController::api($m, false),
    'backUrl' => route($portal.'.meetings.index'),
    'reviewAction' => in_array($m->status, ['upcoming', 'live'], true) ? route($portal.'.meetings.review', $m) : null,
])
@endsection

@push('scripts')
<script src="{{ asset('js/meeting-room.js') }}?v={{ filemtime(public_path('js/meeting-room.js')) }}"></script>
@endpush
