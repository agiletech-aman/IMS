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
        <option value="__other__" @selected($isOther)>Other (Add New)</option>
    </select>
    <input
        type="text"
        class="form-control mt-2"
        name="{{ $inputName }}"
        data-name-input
        minlength="3"
        maxlength="50"
        placeholder="{{ $placeholder ?? 'Enter new '.($label ?? 'name') }}"
        value="{{ $selected }}"
        style="display:{{ $showInput ? 'block' : 'none' }}"
        @if($isRequired) required @endif
    >
</div>
