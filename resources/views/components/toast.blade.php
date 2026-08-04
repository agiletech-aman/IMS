@props([
    'type' => 'info',
    'title' => null,
    'message' => '',
    'duration' => 4000,
])

<script>
    document.addEventListener('DOMContentLoaded', () => {
        window.IIM?.toast(@js($message), @js($type), @js($title), {
            duration: {{ (int) $duration }},
        });
    });
</script>
