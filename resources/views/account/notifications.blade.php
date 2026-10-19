@extends('layouts.app')

@section('title', 'Announcements & Alerts')
@section('back', route('home'))

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card-soft card-pad">
            @forelse ($announcements as $announcement)
                @include('account.partials.announcement', ['announcement' => $announcement])
            @empty
                <x-empty-state icon="megaphone" title="No announcements yet" text="Company announcements, task alerts and leave updates appear here." />
            @endforelse

            @if ($announcements->hasPages())
                <div class="mt-3">{{ $announcements->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
