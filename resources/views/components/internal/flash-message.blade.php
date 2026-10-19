@if(session('status'))
    <div class="shell-alert" role="status">{{ session('status') }}</div>
@endif
@if(session('success'))
    <div class="shell-alert shell-alert-success" role="status">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="shell-alert shell-alert-error" role="alert">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="shell-alert shell-alert-error" role="alert">
        <strong>Periksa kembali data yang Anda masukkan.</strong>
        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
