<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - BarberAja</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-slate-950 flex items-center justify-center">
    <div class="w-full max-w-md bg-slate-900/60 border border-slate-800 rounded-2xl p-8 shadow-xl">
        <h1 class="text-2xl font-semibold text-white mb-6 text-center">
            BarberAja Dashboard
        </h1>

        @if ($errors->any())
            <div class="mb-4 text-sm text-rose-300 bg-rose-900/40 border border-rose-700 rounded-lg p-3">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('login.post') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm text-slate-300 mb-1" for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                    class="w-full rounded-lg border border-slate-700 bg-slate-900 text-slate-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
            </div>
            <div>
                <label class="block text-sm text-slate-300 mb-1" for="password">Password</label>
                <input id="password" name="password" type="password" required
                    class="w-full rounded-lg border border-slate-700 bg-slate-900 text-slate-100 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
            </div>
            <div class="flex items-center justify-between text-sm text-slate-400">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="remember"
                        class="rounded border-slate-700 bg-slate-900 text-sky-500 focus:ring-sky-500">
                    <span>Ingat saya</span>
                </label>
            </div>
            <button type="submit"
                class="w-full inline-flex justify-center items-center rounded-lg bg-sky-500 hover:bg-sky-400 text-slate-900 font-semibold text-sm px-4 py-2.5 transition">
                Masuk
            </button>
        </form>
    </div>
</body>
</html>

