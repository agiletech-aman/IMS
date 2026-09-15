@php
    $inputName = $inputName ?? 'name';
    $selected = $selected ?? '';
    $isRequired = $required ?? true;
    $isOther = $selected !== '' && !$options->contains($selected);
    $showInput = $isOther || $options->isEmpty();
@endphp
<div data-name-field>
    <select class="form-select" data-name-select @if($isRequired) required @endif>
        <option value="">Select {{ $label ?? 'name' }}</option>
        @foreach($options as $opt)
            <option value="{{ $opt }}" @selected($selected === $opt)>{{ $opt }}</option>
        @endforeach
        <option value="__other__" @selected($isOther)>Other</option>
    </select>
    <input
        type="text"
        class="form-control mt-2"
        name="{{ $inputName }}"
        data-name-input
        minlength="3"
        maxlength="50"
        pattern="[A-Za-z ]+"
        title="Only letters and spaces are allowed."
        placeholder="{{ $placeholder ?? 'Enter new '.($label ?? 'name') }}"
        value="{{ $selected }}"
        @if(!$showInput) hidden @endif
        @if($isRequired) required @endif
    >
</div>
