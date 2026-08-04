@extends('layouts.app')
@section('title','Add Asset')
@section('content')
@include('partials.page-header',['title'=>'Add New Asset','description'=>'Register an asset and capture its ownership and coverage details.'])
@include('partials.asset-form')
@endsection
