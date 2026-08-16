@extends('layouts.app')

@section('title', 'Laporan Pembayaran')
@section('subtitle', 'Transaksi pembayaran, breakdown metode, dan tunggakan saat ini')

@section('content')
  @livewire('report.payment-report-page')
@endsection
