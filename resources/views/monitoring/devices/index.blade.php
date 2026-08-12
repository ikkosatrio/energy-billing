@extends('layouts.app')

@section('title', 'Status Perangkat')
@section('subtitle', 'Kesehatan koneksi dan kelengkapan data tiap power meter')

@section('content')
  @livewire('monitoring.device-page')
@endsection
