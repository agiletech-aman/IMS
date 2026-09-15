@php
    $inputName = $inputName ?? 'name';
    $selected = $selected ?? '';
    $isRequired = $required ?? true;
@endphp
<div class="combo-select" data-name-field style="position:relative">
    <input
        type="text"
        class="form-control"
        name="{{ $inputName }}"
        data-name-input
        autocomplete="off"
        minlength="3"
        maxlength="50"
        pattern="[A-Za-z ]+"
        title="Only letters and spaces are allowed."
        placeholder="{{ $placeholder ?? 'Type or select '.($label ?? 'name') }}"
        value="{{ $selected }}"
        oninput="this.value=this.value.replace(/[^A-Za-z ]/g,'');var m=this.closest('[data-name-field]').querySelector('[data-name-menu]'),t=this.value.trim().toLowerCase(),any=false;for(var i=0;i<m.children.length;i++){var o=m.children[i],ok=o.dataset.value.toLowerCase().indexOf(t)!==-1;o.hidden=!ok;if(ok)any=true;}m.hidden=!any;"
        onfocus="var m=this.closest('[data-name-field]').querySelector('[data-name-menu]'),t=this.value.trim().toLowerCase(),any=false;for(var i=0;i<m.children.length;i++){var o=m.children[i],ok=o.dataset.value.toLowerCase().indexOf(t)!==-1;o.hidden=!ok;if(ok)any=true;}m.hidden=!any;"
        onblur="var m=this.closest('[data-name-field]').querySelector('[data-name-menu]');setTimeout(function(){m.hidden=true;},150);"
        @if($isRequired) required @endif
    >
    <div class="combo-menu" data-name-menu hidden style="position:absolute;left:0;right:0;top:calc(100% + 4px);z-index:1070;max-height:220px;overflow-y:auto;background:var(--card-bg,#fff);border:1px solid var(--border-color,#e2e8f0);border-radius:9px;box-shadow:0 8px 24px rgba(15,23,42,.16)">
        @foreach($options as $opt)
            <div
                class="combo-option"
                data-value="{{ $opt }}"
                style="padding:8px 12px;font-size:13px;cursor:pointer"
                onmouseover="this.style.background='var(--bg-secondary,#f8fafc)'"
                onmouseout="this.style.background=''"
                onmousedown="event.preventDefault();var field=this.closest('[data-name-field]');field.querySelector('[data-name-input]').value=this.dataset.value;field.querySelector('[data-name-menu]').hidden=true;"
            >{{ $opt }}</div>
        @endforeach
    </div>
</div>
