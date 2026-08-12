@extends('layouts.app')

@section('title', 'Log Aktivitas')
@section('subtitle', 'Jejak audit perubahan data dan aktivitas pengguna')

@section('content')
  @livewire('system.activity-log-page')
@endsection
