@extends('layouts.app')

@section('title', 'Energy History')
@section('subtitle', 'Riwayat pemakaian kWh per jam, per hari, dan per bulan')

@section('content')
  @livewire('monitoring.history-page')
@endsection
