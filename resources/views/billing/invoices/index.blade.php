@extends('layouts.app')

@section('title', 'Daftar Invoice')
@section('subtitle', 'Invoice yang digenerate dari pemakaian kWh tiap periode')

@section('content')
  @livewire('billing.invoice-page')
@endsection
