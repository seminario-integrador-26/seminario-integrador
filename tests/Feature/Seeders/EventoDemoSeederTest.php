<?php

namespace Tests\Feature\Seeders;

use App\Events\EventoRegistrado;
use App\Models\Errata;
use App\Models\Evento;
use App\Models\TipoEvento;
use App\Services\LibroGuardiaService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\EventoDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EventoDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_siembra_turnos_cerrados_y_eventos_validos_sin_notificar(): void
    {
        $this->seed(DatabaseSeeder::class);
        Event::fake([EventoRegistrado::class]);

        $this->seed(EventoDemoSeeder::class);

        // Cada turno tiene su Apertura y su Cierre; ninguno queda abierto.
        $turnos = EventoDemoSeeder::DIAS * 3 - 1;
        $this->assertSame($turnos, Evento::where('tipo_evento_id', TipoEvento::interno(TipoEvento::APERTURA_TURNO))->count());
        $this->assertSame($turnos, Evento::where('tipo_evento_id', TipoEvento::interno(TipoEvento::CIERRE_TURNO))->count());
        $this->assertNull(app(LibroGuardiaService::class)->aperturaVigente());
        // La Errata la crea el sistema: el seeder no la carga como evento.
        $this->assertSame(0, Evento::where('tipo_evento_id', TipoEvento::interno('Errata'))->count());
        $this->assertGreaterThan(1000, Evento::count());
        $this->assertGreaterThan(0, Errata::count());

        // Ubicación condicional y timestamp de video solo con PM.
        $this->assertSame(0, Evento::whereHas('tipoEvento', fn ($q) => $q->where('requiere_ubicacion', true))->whereNull('punto_monitoreo_id')->count());
        $this->assertSame(0, Evento::whereNotNull('punto_monitoreo_id')->whereNull('timestamp_video')->count());
        $this->assertSame(0, Evento::where('fecha_hora', '>=', today())->count());

        Event::assertNotDispatched(EventoRegistrado::class);
    }

    public function test_no_siembra_si_ya_hay_eventos(): void
    {
        $this->seed(DatabaseSeeder::class);
        Evento::factory()->create();

        $this->seed(EventoDemoSeeder::class);

        $this->assertSame(1, Evento::count());
    }
}
