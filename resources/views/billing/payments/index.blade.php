@extends('layouts.app')

@section('title', 'Pembayaran')
@section('subtitle', 'Pencatatan pelunasan invoice beserta bukti transfernya')

@section('content')
  @livewire('billing.payment-page')
@endsection
