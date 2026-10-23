<?php

namespace Tests\Feature\Catalogos;

use App\Models\Barrio;
use App\Models\GrupoEvento;
use App\Models\PuntoMonitoreo;
use App\Models\TipoEvento;
use Database\Seeders\PuntoMonitoreoSeeder;
use Database\Seeders\TipoEventoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Catálogos reales (registro de intervenciones y mapa de cámaras). deploy.sh
 * corre estos seeders en cada deploy: no deben pisar lo editado desde la UI.
 */
class CatalogosSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_carga_los_grupos_y_tipos_del_registro_de_intervenciones(): void
    {
        $this->seed(TipoEventoSeeder::class);

        $this->assertSame(
            ['policia' => 'Policía', 'guardia_local' => 'Guardia Local', 'internos' => 'Internos'],
            GrupoEvento::opciones(),
        );
        $this->assertSame(41, TipoEvento::count());
        // "Otros" existe en los tres grupos: el nombre es único por grupo, no global.
        $this->assertSame(3, TipoEvento::where('nombre', 'Otros')->count());
        $this->assertFalse(TipoEvento::where('nombre', 'Apertura de turno')->value('es_cuantificable'));
        $this->assertTrue(TipoEvento::where('nombre', 'Oficios judiciales')->value('es_cuantificable'));
        $this->assertFalse(TipoEvento::where('nombre', 'Oficios judiciales')->value('requiere_ubicacion'));
    }

    public function test_resembrar_no_pisa_los_cambios_hechos_en_un_tipo(): void
    {
        $this->seed(TipoEventoSeeder::class);
        TipoEvento::where('nombre', 'Robos')->update(['requiere_ubicacion' => false]);

        $this->seed(TipoEventoSeeder::class);

        $this->assertSame(41, TipoEvento::count());
        $this->assertFalse(TipoEvento::where('nombre', 'Robos')->value('requiere_ubicacion'));
    }

    public function test_carga_los_puntos_del_mapa_con_tipo_y_barrio(): void
    {
        $this->seed(PuntoMonitoreoSeeder::class);

        $this->assertSame(498, PuntoMonitoreo::count());

        $pm = PuntoMonitoreo::where('codigo', 'PM-M-001')->with(['tipoPuntoMonitoreo', 'barrio'])->first();
        $this->assertSame('municipal', $pm->tipoPuntoMonitoreo->codigo);
        $this->assertSame('Almirante Brown', $pm->barrio->nombre);
        $this->assertSame('alarma', PuntoMonitoreo::where('codigo', 'PM-A-001')->first()->tipoPuntoMonitoreo->codigo);
    }

    public function test_resembrar_no_pisa_el_barrio_asignado_a_mano(): void
    {
        $this->seed(PuntoMonitoreoSeeder::class);
        $otro = Barrio::create(['nombre' => 'Barrio de prueba']);
        PuntoMonitoreo::where('codigo', 'PM-M-001')->update(['barrio_id' => $otro->id]);

        $this->seed(PuntoMonitoreoSeeder::class);

        $this->assertSame(498, PuntoMonitoreo::count());
        $this->assertSame($otro->id, PuntoMonitoreo::where('codigo', 'PM-M-001')->value('barrio_id'));
    }

    public function test_reemplaza_los_placeholder_de_desarrollo(): void
    {
        PuntoMonitoreo::factory()->create(['codigo' => 'PM-M-001', 'nombre' => 'PM municipal 001 (placeholder)']);
        PuntoMonitoreo::factory()->create(['codigo' => 'PM-M-300', 'nombre' => 'PM municipal 300 (placeholder)']);

        $this->seed(PuntoMonitoreoSeeder::class);

        $this->assertSame('Bv. V. Sarsfield esq Gral Paz', PuntoMonitoreo::where('codigo', 'PM-M-001')->value('nombre'));
        // PM-M-300 no está en el mapa: el placeholder se borra.
        $this->assertFalse(PuntoMonitoreo::where('codigo', 'PM-M-300')->exists());
    }
}
