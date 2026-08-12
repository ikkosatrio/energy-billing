@extends('layouts.app')

@section('title', 'Periode & Generate Invoice')
@section('subtitle', 'Menerbitkan tagihan berdasarkan pemakaian kWh tiap periode')

@section('content')
  @livewire('billing.period-page')
@endsection
