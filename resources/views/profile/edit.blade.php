<x-app-layout titulo="Meu perfil">
    <div class="page-head">
        <div>
            <div class="eyebrow">Conta</div>
            <h1>Meu perfil</h1>
            <p>Seus dados de acesso. As informações financeiras ficam nos módulos ao lado.</p>
        </div>
    </div>

    <div class="grid g2" style="align-items:start">
        <div class="card">@include('profile.partials.update-profile-information-form')</div>
        <div class="card">@include('profile.partials.update-password-form')</div>
    </div>
    <div class="card">@include('profile.partials.delete-user-form')</div>
</x-app-layout>
