@extends('errors.layout')

@section('code', '403')
@section('title', 'Akses Dibatasi')
@section('message', $exception->getMessage() ?: 'Anda tidak memiliki hak akses untuk membuka halaman ini. Hubungi administrator bila Anda merasa ini keliru.')
