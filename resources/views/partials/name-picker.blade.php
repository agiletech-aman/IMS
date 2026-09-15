@php
    $inputName = $inputName ?? 'name';
    $selected = $selected ?? '';
    $isRequired = $required ?? true;
    $isOther = $selected !== '' && !$options->contains($selected);
    $showInput = $isOther || $options->isEmpty();
@endphp
<div data-name-field>
    <select
        class="form-select"
        data-name-select
        @if($isRequired) required @endif
        onchange="var i=this.nextElementSibling;if(this.value==='__other__'){i.value='';i.hidden=false;i.focus();}else if(this.value===''){i.hidden=true;i.value='';}else{i.value=this.value;i.hidden=true;}"
    >
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
        oninput="this.value=this.value.replace(/[^A-Za-z ]/g,'')"
        @if(!$showInput) hidden @endif
        @if($isRequired) required @endif
    >
</div>
