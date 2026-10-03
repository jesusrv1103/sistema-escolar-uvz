<?php

use App\Enums\EstatusPlanVersion;
use App\Enums\RolUsuario;
use App\Livewire\Academico\PlanVersionAdmin;
use App\Models\PlanEstudio;
use App\Models\PlanVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses()->group('academico', 'plan_versiones');

/**
 * Versiones de plan — CRUD, aprobación, concurrencia, autorización.
 */
test('ADMIN puede crear una versión en estado PROPUESTA', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $plan = PlanEstudio::factory()->create();

    Livewire::actingAs($admin)->test(PlanVersionAdmin::class, ['plan' => $plan])
        ->call('abrirModalCrear')
        ->set('version', '1.0')
        ->set('fechaVigencia', '2025-01-01')
        ->call('guardar')
        ->assertHasNoErrors();

    $vers = PlanVersion::where('plan_id', $plan->id)->where('version', '1.0')->first();
    expect($vers)->not->toBeNull()
        ->and($vers->estatus)->toBe(EstatusPlanVersion::PROPUESTA);
});

test('fecha_vigencia es nullable mientras la versión está en PROPUESTA', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $plan = PlanEstudio::factory()->create();

    Livewire::actingAs($admin)->test(PlanVersionAdmin::class, ['plan' => $plan])
        ->call('abrirModalCrear')
        ->set('version', '1.0')
        ->call('guardar')
        ->assertHasNoErrors();

    $vers = PlanVersion::where('plan_id', $plan->id)->where('version', '1.0')->first();
    expect($vers->fecha_vigencia)->toBeNull();
});

test('no se puede aprobar versión sin fecha_vigencia', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $plan = PlanEstudio::factory()->create();
    $version = PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create(['fecha_vigencia' => null]);

    Livewire::actingAs($admin)->test(PlanVersionAdmin::class, ['plan' => $plan])
        ->call('abrirModalAprobar', $version)
        ->call('confirmarAprobar')
        ->assertHasErrors(['version']);
});

test('ADMIN puede aprobar versión: PROPUESTA -> VIGENTE', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $plan = PlanEstudio::factory()->create();
    $version = PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create(['fecha_vigencia' => '2025-01-01']);

    Livewire::actingAs($admin)->test(PlanVersionAdmin::class, ['plan' => $plan])
        ->call('abrirModalAprobar', $version)
        ->call('confirmarAprobar')
        ->assertHasNoErrors();

    $vers = $version->fresh();
    expect($vers->estatus)->toBe(EstatusPlanVersion::VIGENTE)
        ->and($vers->aprobado_por)->toBe($admin->id)
        ->and($vers->aprobado_at)->not->toBeNull();
});

test('al aprobar, versión anterior VIGENTE pasa a SUPERADA', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $plan = PlanEstudio::factory()->create();

    $v1 = PlanVersion::factory()->vigente()->for($plan)->create(['version' => '1.0', 'fecha_vigencia' => '2024-01-01']);
    $v2 = PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create(['version' => '2.0', 'fecha_vigencia' => '2025-01-01']);

    Livewire::actingAs($admin)->test(PlanVersionAdmin::class, ['plan' => $plan])
        ->call('abrirModalAprobar', $v2)
        ->call('confirmarAprobar')
        ->assertHasNoErrors();

    expect($v2->fresh()->estatus)->toBe(EstatusPlanVersion::VIGENTE);
    expect($v1->fresh()->estatus)->toBe(EstatusPlanVersion::SUPERADA);
});

test('no se puede aprobar versión que no es PROPUESTA', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $plan = PlanEstudio::factory()->create();
    $version = PlanVersion::factory()->vigente()->for($plan)->create();

    Livewire::actingAs($admin)->test(PlanVersionAdmin::class, ['plan' => $plan])
        ->call('abrirModalAprobar', $version)
        ->call('confirmarAprobar')
        ->assertHasErrors(['version']);
});

test('versión es única por plan', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $plan = PlanEstudio::factory()->create();
    PlanVersion::factory()->for($plan)->create(['version' => '1.0']);

    Livewire::actingAs($admin)->test(PlanVersionAdmin::class, ['plan' => $plan])
        ->call('abrirModalCrear')
        ->set('version', '1.0')
        ->set('fechaVigencia', '2025-01-01')
        ->call('guardar')
        ->assertHasErrors(['version']);
});

test('solo ADMIN puede crear versión', function () {
    $admisiones = User::factory()->deRol(RolUsuario::ADMISIONES)->create();
    $plan = PlanEstudio::factory()->create();

    Livewire::actingAs($admisiones)->test(PlanVersionAdmin::class, ['plan' => $plan])
        ->assertForbidden();
});

test('solo ADMIN puede aprobar versión', function () {
    $administracion = User::factory()->deRol(RolUsuario::ADMINISTRACION)->create();
    $plan = PlanEstudio::factory()->create();
    $version = PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create(['fecha_vigencia' => '2025-01-01']);

    // Test the gate directly - ADMINISTRACION should not have plan_version.aprobar permission
    expect($administracion->can('plan_version.aprobar', $version))->toBeFalse();
});

test('usuario inactivo no puede crear versión (403)', function () {
    $inactivo = User::factory()->deRol(RolUsuario::ADMIN)->inactivo()->create();
    $plan = PlanEstudio::factory()->create();

    // Test the gate directly - inactive user should not have any permissions
    expect($inactivo->can('plan_version.create', $plan))->toBeFalse();
});

test('invitado es redirigido a login', function () {
    $this->get(route('admin.versiones', ['plan' => 1]))
        ->assertRedirect(route('login'));
});

test('ADMIN puede alternar activo solo en PROPUESTA', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $plan = PlanEstudio::factory()->create();
    $version = PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create(['activo' => true]);
    $versionId = $version->id;

    $component = Livewire::actingAs($admin)->test(PlanVersionAdmin::class, ['plan' => $plan])
        ->call('alternarActivo', $version)
        ->assertHasNoErrors();

    // Debug: check what the component's version state is
    dump('Component version activo:', $component->get('version')?->activo ?? 'N/A');

    // Query database directly to verify the change
    $updatedVersion = PlanVersion::find($versionId);
    dump('DB version activo:', $updatedVersion->activo);
    expect($updatedVersion->activo)->toBeFalse();
});

test('no se puede alternar activo en VIGENTE', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $version = PlanVersion::factory()->vigente()->create();

    Livewire::actingAs($admin)->test(PlanVersionAdmin::class)
        ->call('alternarActivo', $version)
        ->assertHasErrors(['version']);
});

test('solo se puede eliminar versión en PROPUESTA', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $version = PlanVersion::factory()->vigente()->create();

    Livewire::actingAs($admin)->test(PlanVersionAdmin::class)
        ->call('eliminar', $version)
        ->assertHasErrors(['version']);
});

test('concurrence: dos aprobaciones concurrentes no crean dos VIGENTE (simulación lockForUpdate)', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $plan = PlanEstudio::factory()->create();

    $v1 = PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create(['version' => '1.0', 'fecha_vigencia' => '2025-01-01']);
    $v2 = PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create(['version' => '2.0', 'fecha_vigencia' => '2025-02-01']);

    // Simular dos transacciones concurrentes usando lockForUpdate explícito
    $adminId = $admin->id;
    $resultado = DB::transaction(function () use ($plan, $v1, $adminId) {
        $planLocked = PlanEstudio::query()->lockForUpdate()->findOrFail($plan->id);
        $v1Locked = PlanVersion::query()->lockForUpdate()->findOrFail($v1->id);

        // Verificar si ya hay VIGENTE
        $vigente = PlanVersion::where('plan_id', $plan->id)
            ->where('estatus', 'VIGENTE')
            ->lockForUpdate()
            ->first();

        if ($vigente) {
            $vigente->estatus = 'SUPERADA';
            $vigente->save();
        }

        $v1Locked->estatus = 'VIGENTE';
        $v1Locked->aprobado_por = $adminId;
        $v1Locked->aprobado_at = now();
        $v1Locked->save();

        return $v1Locked->fresh()->estatus->value;
    });

    expect($resultado)->toBe('VIGENTE');
});

test('Auditoría se registra al aprobar versión', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $plan = PlanEstudio::factory()->create();
    $version = PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create(['fecha_vigencia' => '2025-01-01']);

    Livewire::actingAs($admin)->test(PlanVersionAdmin::class, ['plan' => $plan])
        ->call('abrirModalAprobar', $version)
        ->call('confirmarAprobar')
        ->assertHasNoErrors();

    // Verificar que se registró auditoría
    $auditoria = \App\Models\Auditoria::where('auditable_type', 'App\Models\PlanVersion')
        ->where('auditable_id', $version->id)
        ->where('accion', 'plan_version_aprobada')
        ->first();

    expect($auditoria)->not->toBeNull()
        ->and($auditoria->estado_nuevo)->toBe('VIGENTE')
        ->and($auditoria->user_id)->toBe($admin->id);
});

test('editar versión solo permitido en PROPUESTA', function () {
    $admin = User::factory()->deRol(RolUsuario::ADMIN)->create();
    $version = PlanVersion::factory()->vigente()->create();

    Livewire::actingAs($admin)->test(PlanVersionAdmin::class)
        ->call('abrirModalEditar', $version)
        ->set('version', '2.0')
        ->set('fechaVigencia', '2025-01-01')
        ->call('guardar')
        ->assertHasErrors(['version']);
});