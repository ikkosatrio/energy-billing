@extends('layouts.portal')

@section('title', 'Dashboard')
@section('subtitle', 'Kondisi meter, pemakaian energi, dan status tagihan Anda')

@section('content')
  @livewire('portal.dashboard-page')
@endsection
