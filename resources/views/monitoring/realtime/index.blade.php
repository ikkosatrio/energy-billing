@extends('layouts.app')

@section('title', 'Real-time Monitoring')
@section('subtitle', 'Pembacaan power meter langsung dari perangkat IoT')

@section('content')
  @livewire('monitoring.realtime-page')
@endsection
