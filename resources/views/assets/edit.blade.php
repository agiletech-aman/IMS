@extends('layouts.app')
@section('title','Edit Asset')
@section('content')
@include('partials.page-header',['title'=>'Edit Asset '.$asset->asset_tag,'description'=>'Update asset assignment, specifications, or lifecycle information.'])
@include('partials.asset-form')
@endsection
