@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <h1 class="text-2xl font-semibold mb-4">Dashboard</h1>
    <p class="text-sm text-slate-400 mb-6">
        Ini placeholder dashboard untuk uji auth, tenant middleware, dan multi-tenant scope.
    </p>
    <a href="{{ route('products.index') }}" class="inline-flex items-center px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-sm">
        Kelola Layanan →
    </a>
@endsection

