@extends('layouts.app')
@section('title','Forgot password')
@section('content')
<div class="auth-shell" style="background-image:url('https://images.unsplash.com/photo-1488590528505-98d2b5aba04b?auto=format&fit=crop&w=1800&q=85')"><div class="auth-card"><a class="brand" href="#"><span class="brand-mark"><i class="fa-solid fa-lock"></i></span></a><h1>Reset your password</h1><p>Enter your work email and we’ll send a secure reset link.</p><form data-demo><div class="mb-3"><label class="form-label">Work email</label><input class="form-control" type="email" placeholder="you@company.com"></div><button class="btn btn-primary w-100">Send reset link</button></form><div class="auth-foot"><a class="auth-link" href="{{ route('login') }}"><i class="fa-solid fa-arrow-left me-2"></i>Back to sign in</a></div></div></div>
@endsection
