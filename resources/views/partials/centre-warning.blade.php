@php
    $needsCentreSelection = filled(session('static_auth_user.admin_id'))
        && ! in_array(session('selected_centre'), ['noida', 'lucknow'], true);
@endphp
@if($needsCentreSelection)
    <div class="alert-centre-warning" role="alert">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <span>No specific Centre is selected. This record needs a Centre — pick <strong>Noida</strong> or <strong>Lucknow</strong> from the Centre menu above before saving.</span>
    </div>
@endif
