@extends('layouts.app')

@section('title', 'Rekap Tagihan & Penerimaan')
@section('subtitle', 'Nilai yang ditagihkan, sudah terbayar, dan yang masih tertunggak')

@section('content')
  @livewire('report.billing-report-page')
@endsection
