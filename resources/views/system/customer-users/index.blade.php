@extends('layouts.app')

@section('title', 'Akun Portal Pelanggan')
@section('subtitle', 'Akun login pelanggan beserta role portal dan pelanggan yang bisa diaksesnya')

@section('content')
  @livewire('system.customer-user-page')
@endsection
