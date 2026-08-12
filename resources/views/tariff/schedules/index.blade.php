@extends('layouts.app')

@section('title', 'Jadwal WBP / LWBP')
@section('subtitle', 'Konfigurasi jadwal tarif waktu per power meter')

@section('content')
  @livewire('tariff.tariff-schedule-page')
@endsection
