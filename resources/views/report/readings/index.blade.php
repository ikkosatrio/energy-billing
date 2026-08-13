@extends('layouts.app')

@section('title', 'Data Meter Mentah')
@section('subtitle', 'Pembacaan asli dari gateway, untuk menelusuri angka tagihan dan data yang bolong')

@section('content')
  @livewire('report.reading-report-page')
@endsection
