@extends('layouts.app')
@section('title','Raise Complaint')
@section('content')
@include('partials.page-header',[
    'title'=>'Raise a Complaint',
    'description'=>'Capture the issue and requester details. A unique complaint number will be generated automatically.',
])

<form method="POST" action="{{ route('complaints.store') }}">
    @csrf
    <div class="row g-3">
        <div class="col-xl-8">
            <div class="panel">
                <div class="panel-header"><div><h2>Complaint Details</h2><p>Describe the service issue clearly</p></div><span class="badge-soft danger">Complaint Raised</span></div>
                <div class="panel-body"><div class="row g-3">
                    <div class="col-12"><label class="form-label">Subject *</label><input class="form-control @error('subject') is-invalid @enderror" name="subject" value="{{ old('subject') }}" required maxlength="255" placeholder="Brief summary of the issue">@error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label class="form-label">Category *</label><select class="form-select" name="category" required><option value="">Select category</option>@foreach($categories as $category)<option value="{{ $category->name }}" @selected(old('category') === $category->name)>{{ $category->name }} ({{ $category->code }})</option>@endforeach</select>@if($categories->isEmpty())<small class="text-danger d-block mt-1">Create an active category under Asset Management first.</small>@endif</div>
                    <div class="col-md-6"><label class="form-label">Related Asset</label><select class="form-select" name="asset_id"><option value="">No related asset</option>@foreach($assets as $asset)<option value="{{ $asset->id }}" @selected((string)old('asset_id') === (string)$asset->id)>{{ $asset->asset_tag }} · {{ $asset->name }}</option>@endforeach</select></div>
                    <div class="col-12"><label class="form-label">Description *</label><textarea class="form-control @error('description') is-invalid @enderror" name="description" rows="8" required placeholder="Explain what happened, when it started, and its impact">{{ old('description') }}</textarea>@error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                </div></div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="panel">
                <div class="panel-header"><h2>Requester & Priority</h2></div>
                <div class="panel-body">
                    <label class="form-label">Requester Name *</label><input class="form-control mb-3" name="requester_name" value="{{ old('requester_name',session('static_auth_user.name')) }}" required>
                    <label class="form-label">Email</label><input class="form-control mb-3" type="email" name="requester_email" value="{{ old('requester_email',session('static_auth_user.email')) }}">
                    <label class="form-label">Contact Number</label><input class="form-control mb-3" name="requester_contact" value="{{ old('requester_contact') }}">
                    <label class="form-label">Priority *</label><select class="form-select mb-4" name="priority" required>@foreach($priorities as $priority)<option value="{{ $priority }}" @selected(old('priority','Medium') === $priority)>{{ $priority }}</option>@endforeach</select>
                    <button class="btn btn-primary w-100"><i class="fa-solid fa-circle-exclamation me-2"></i>Raise Complaint</button>
                    <a class="btn btn-soft w-100 mt-2" href="{{ route('complaints.index') }}">Cancel</a>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
