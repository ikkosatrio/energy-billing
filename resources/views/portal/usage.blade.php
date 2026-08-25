@extends('layouts.portal')

@section('title', 'Rekap Pemakaian kWh')
@section('subtitle', 'Total pemakaian per lokasi pada rentang tanggal pilihan')

@section('content')
  @livewire('portal.usage-page')
@endsection
