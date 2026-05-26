@extends('layouts.app')
@section('title', 'Edit Signature')
@section('page-title', 'Edit Email Signature')

@section('content')
    @include('email-signatures._form', ['signature' => $signature])
@endsection
