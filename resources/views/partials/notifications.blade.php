@php
    $notifications = collect([
        ['type' => 'success', 'title' => 'Success', 'message' => session('success')],
        ['type' => 'error', 'title' => 'Error', 'message' => session('error') ?? session('danger')],
        ['type' => 'warning', 'title' => 'Warning', 'message' => session('warning')],
        ['type' => 'info', 'title' => 'Information', 'message' => session('info') ?? session('status')],
    ])->filter(fn ($notification) => filled($notification['message']))->values();

    if ($errors->any()) {
        $notifications->push([
            'type' => 'error',
            'title' => 'Validation failed',
            'message' => $errors->count() === 1
                ? $errors->first()
                : $errors->count().' fields need your attention. '.$errors->first(),
        ]);
    }
@endphp

<script>
    window.IIMFlashNotifications = @json($notifications);
</script>
