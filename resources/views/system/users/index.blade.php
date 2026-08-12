@extends('layouts.app')

@section('title', 'User Management')
@section('subtitle', 'Pengguna aplikasi beserta role dan hak aksesnya')

@section('content')
  @livewire('system.user-page')
@endsection
