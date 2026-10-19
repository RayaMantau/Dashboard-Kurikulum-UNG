@extends('layouts.internal')

@section('title', 'Masuk Internal')

@section('content')
    <div class="login-layout">
        <section class="login-intro" aria-labelledby="portal-heading">
            <p class="eyebrow">PORTAL AKADEMIK</p>
            <h1 id="portal-heading">Kelola informasi kurikulum dalam satu tempat.</h1>
            <p>Akses internal untuk Admin Universitas, Admin Fakultas, Admin Prodi, dan Kaprodi.</p>
            <p class="public-note">Pengunjung, mahasiswa, dan dosen dapat melihat informasi kurikulum publik tanpa akun.</p>
        </section>
        <section class="portal-card" aria-labelledby="login-heading">
            <p class="eyebrow">AKSES INTERNAL</p>
            <h2 id="login-heading">Masuk ke akun Anda</h2>
            <p>Gunakan email dan kata sandi akun internal yang aktif.</p>
            <form method="POST" action="{{ route('login.store') }}" data-pending-form>
                @csrf
                <div class="form-field">
                    <label for="email">Alamat email <span>(wajib)</span></label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" maxlength="255" required autofocus
                        aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                        @error('email') aria-describedby="email-error" @enderror>
                    @error('email')
                        <p id="email-error" class="field-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>
                <div class="form-field">
                    <label for="password">Kata sandi <span>(wajib)</span></label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required
                        aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                        @error('password') aria-describedby="password-error" @enderror>
                    @error('password')
                        <p id="password-error" class="field-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>
                <button class="primary-button" type="submit" data-pending-label="Sedang masuk…">Masuk</button>
                <p class="form-help">Jika akun tidak dapat digunakan, hubungi administrator universitas.</p>
            </form>
        </section>
    </div>
@endsection
