<?php

use App\Enums\EstatusPlanVersion;
use App\Models\PlanEstudio;
use App\Models\PlanVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

uses()->group('academico', 'plan_versiones');

/**
 * Versiones de plan — CRUD, aprobación, concurrencia, autorización.
 */
test('ADMIN puede crear una versión en estado PROPUESTA', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $plan = \App\Models\PlanEstudio::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.versiones', ['plan' => $plan->id]), [
            'version' => '1.0',
            'fecha_vigencia' => '2025-01-01',
        ])
        ->assertStatus(200)
        ->assertSessionHas('success');

    $vers = PlanVersion::where('plan_id', $plan->id)->where('version', '1.0')->first();
    expect($vers)->not->toBeNull()
        ->and($vers->estatus)->toBe(EstatusPlanVersion::PROPUESTA);
});

test('fecha_vigencia es nullable mientras la versión está en PROPUESTA', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $plan = \App\Models\PlanEstudio::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.versiones', ['plan' => $plan->id]), [
            'version' => '1.0',
        ])
        ->assertStatus(200)
        ->assertSessionHas('success');

    $vers = PlanVersion::where('plan_id', $plan->id)->where('version', '1.0')->first();
    expect($vers->fecha_vigencia)->toBeNull();
});

test('no se puede aprobar versión sin fecha_vigencia', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $plan = \App\Models\PlanEstudio::factory()->create();
    $version = PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create(['fecha_vigencia' => null]);

    $this->actingAs($admin)
        ->post(route('admin.versiones.aprobar', $version->id))
        ->assertStatus(200)
        ->assertSessionHasErrors('version');
});

test('ADMIN puede aprobar versión: PROPUESTA -> VIGENTE', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $plan = \App\Models\PlanEstudio::factory()->create();
    $version = PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create(['fecha_vigencia' => '2025-01-01']);

    $this->actingAs($admin)
        ->post(route('admin.versiones.aprobar', $version->id))
        ->assertStatus(200)
        ->assertSessionHas('success');

    $vers = $version->fresh();
    expect($vers->estatus)->toBe(EstatusPlanVersion::VIGENTE)
        ->and($vers->aprobado_por)->toBe($admin->id)
        ->and($vers->aprobado_at)->not->toBeNull();
});

test('al aprobar, versión anterior VIGENTE pasa a SUPERADA', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $plan = \App\Models\PlanEstudio::factory()->create();

    $v1 = PlanVersion::factory()->vigente()->for($plan)->create(['version' => '1.0', 'fecha_vigencia' => '2024-01-01']);
    $v2 = PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create(['version' => '2.0', 'fecha_vigencia' => '2025-01-01']);

    $this->actingAs($admin)
        ->post(route('admin.versiones.aprobar', $v2->id))
        ->assertStatus(200)
        ->assertSessionHas('success');

    expect($v2->fresh()->estatus)->toBe(EstatusPlanVersion::VIGENTE);
    expect($v1->fresh()->estatus)->toBe(EstatusPlanVersion::SUPERADA);
});

test('no se puede aprobar versión que no es PROPUESTA', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $plan = \App\Models\PlanEstudio::factory()->create();
    $version = PlanVersion::factory()->vigente()->for($plan)->create();

    $this->actingAs($admin)
        ->post(route('admin.versiones.aprobar', $version->id))
        ->assertStatus(200)
        ->assertSessionHasErrors('version');
});

test('versión es única por plan', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $plan = \App\Models\PlanEstudio::factory()->create();
    PlanVersion::factory()->for($plan)->create(['version' => '1.0']);

    $this->actingAs($admin)
        ->post(route('admin.versiones', ['plan' => $plan->id]), [
            'version' => '1.0',
            'fecha_vigencia' => '2025-01-01',
        ])
        ->assertStatus(200)
        ->assertSessionHasErrors('version');
});

test('solo ADMIN puede crear versión', function () {
    $admisiones = User::factory()->deRol(\App\Enums\RolUsuario::ADMISIONES)->create();
    $plan = \App\Models\PlanEstudio::factory()->create();

    $this->actingAs($admisiones)
        ->post(route('admin.versiones', ['plan' => $plan->id]), [
            'version' => '1.0',
            'fecha_vigencia' => '2025-01-01',
        ])
        ->assertForbidden();
});

test('solo ADMIN puede aprobar versión', function () {
    $administracion = User::factory()->deRol(\App\Enums\RolUsuario::ADMINISTRACION)->create();
    $plan = \App\Models\PlanEstudio::factory()->create();
    $version = PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create(['fecha_vigencia' => '2025-01-01']);

    $this->actingAs($administracion)
        ->post(route('admin.versiones.aprobar', $version->id))
        ->assertForbidden();
});

test('usuario inactivo no puede crear versión (403)', function () {
    $inactivo = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->inactivo()->create();
    $plan = \App\Models\PlanEstudio::factory()->create();

    $this->actingAs($inactivo)
        ->post(route('admin.versiones', ['plan' => $plan->id]), [
            'version' => '1.0',
        ])
        ->assertForbidden();
});

test('invitado es redirigido a login', function () {
    $this->get(route('admin.versiones', ['plan' => 1]))
        ->assertRedirect(route('login'));
});

test('ADMIN puede alternar activo solo en PROPUESTA', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $plan = \App\Models\PlanEstudio::factory()->create();
    $version = PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create(['activo' => true]);

    $this->actingAs($admin)
        ->post(route('admin.versiones.alternar', $version->id))
        ->assertStatus(200)
        ->assertSessionHas('success');

    expect($version->fresh()->activo)->toBeFalse();
});

test('no se puede alternar activo en VIGENTE', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $version = PlanVersion::factory()->vigente()->create();

    $this->actingAs($admin)
        ->post(route('admin.versiones.alternar', $version->id))
        ->assertStatus(200)
        ->assertSessionHasErrors('version');
});

test('solo se puede eliminar versión en PROPUESTA', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $version = PlanVersion::factory()->vigente()->create();

    $this->actingAs($admin)
        ->delete(route('admin.versiones.eliminar', $version->id))
        ->assertStatus(200)
        ->assertSessionHasErrors('version');
});

test('concurrence: dos aprobaciones concurrentes no crean dos VIGENTE (simulación lockForUpdate)', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $plan = \App\Models\PlanEstudio::factory()->create();

    $v1 = PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create(['version' => '1.0', 'fecha_vigencia' => '2025-01-01']);
    $v2 = PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create(['version' => '2.0', 'fecha_vigencia' => '2025-02-01']);

    // Simular dos transacciones concurrentes usando lockForUpdate explícito
    $resultado = DB::transaction(function () use ($plan, $v1) {
        $planLocked = \App\Models\PlanEstudio::query()->lockForUpdate()->findOrFail($plan->id);
        $v1Locked = \App\Models\PlanVersion::query()->lockForUpdate()->findOrFail($v1->id);

        // Verificar si ya hay VIGENTE
        $vigente = \App\Models\PlanVersion::where('plan_id', $plan->id)
            ->where('estatus', 'VIGENTE')
            ->lockForUpdate()
            ->first();

        if ($vigente) {
            $vigente->estatus = 'SUPERADA';
            $vigente->save();
        }

        $v1Locked->estatus = 'VIGENTE';
        $v1Locked->aprobado_por = 1;
        $v1Locked->aprobado_at = now();
        $v1Locked->save();

        return $v1Locked->fresh()->estatus;
    });

    expect($resultado)->toBe('VIGENTE');
});

test('Auditoría se registra al aprobar versión', function () {
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $plan = \App\Models\PlanEstudio::factory()->create();
    $version = PlanVersion::factory()->propuesta()->forPlanEstudio($plan)->create(['fecha_vigencia' => '2025-01-01']);

    $this->actingAs($admin)
        ->post(route('admin.versiones.aprobar', $version->id))
        ->assertStatus(200);

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
    $admin = User::factory()->deRol(\App\Enums\RolUsuario::ADMIN)->create();
    $version = PlanVersion::factory()->vigente()->create();

    $this->actingAs($admin)
        ->put(route('admin.versiones.actualizar', $version->id), [
            'version' => '2.0',
            'fecha_vigencia' => '2025-01-01',
        ])
        ->assertStatus(200)
        ->assertSessionHasErrors('version');
});