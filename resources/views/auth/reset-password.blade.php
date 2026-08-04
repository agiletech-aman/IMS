@extends('layouts.app')
@section('title','Choose new password')
@section('content')
<div class="auth-shell" style="background-image:url('https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=1800&q=85')"><div class="auth-card"><a class="brand" href="#"><span class="brand-mark"><i class="fa-solid fa-key"></i></span></a><h1>Choose a new password</h1><p>Use at least eight characters with a number and symbol.</p><form data-demo><div class="mb-3"><label class="form-label">Email</label><input class="form-control" type="email" value="admin@nexacore.com"></div><div class="mb-3"><label class="form-label">New password</label><input class="form-control" type="password"></div><div class="mb-3"><label class="form-label">Confirm new password</label><input class="form-control" type="password"></div><button class="btn btn-primary w-100">Update password</button></form></div></div>
@endsection
