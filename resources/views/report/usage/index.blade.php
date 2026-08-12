@extends('layouts.app')

@section('title', 'Rekap Pemakaian kWh')
@section('subtitle', 'Pemakaian LWBP dan WBP per pelanggan dalam satu rentang periode')

@section('content')
  @livewire('report.usage-report-page')
@endsection
