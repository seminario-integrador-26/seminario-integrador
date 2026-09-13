<?php

namespace Database\Seeders;

use App\Models\PuntoMonitoreo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * US-003: carga los 394 Puntos de Monitoreo (235 municipales + 159 provinciales).
 *
 * - Si existe database/data/puntos_monitoreo.csv lo importa (idempotente, upsert por código).
 *   Encabezado: codigo,nombre,jurisdiccion,latitud,longitud  (UTF-8)
 * - Si no existe y el entorno es local, genera PMs PLACEHOLDER para poder desarrollar.
 *
 * // TODO: conseguir el listado oficial de PMs (código, nombre, coordenadas).
 */
class PuntoMonitoreoSeeder extends Seeder
{
    public const CANTIDAD_MUNICIPALES = 235;

    public const CANTIDAD_PROVINCIALES = 159;

    public function run(): void
    {
        $csv = database_path('data/puntos_monitoreo.csv');

        if (is_file($csv)) {
            $this->upsert($this->leerCsv($csv));
            $this->command?->info('Puntos de monitoreo importados desde '.$csv);

            return;
        }

        if (! app()->environment('local')) {
            $this->command?->warn('No se encontró '.$csv.': no se cargaron puntos de monitoreo.');

            return;
        }

        $this->upsert([
            ...$this->placeholders('municipal', 'M', self::CANTIDAD_MUNICIPALES),
            ...$this->placeholders('provincial', 'P', self::CANTIDAD_PROVINCIALES),
        ]);
        $this->command?->warn('Se generaron puntos de monitoreo PLACEHOLDER (solo desarrollo).');
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function upsert(array $filas): void
    {
        $ahora = Carbon::now();

        $filas = array_map(fn (array $f) => [...$f, 'created_at' => $ahora, 'updated_at' => $ahora], $filas);

        foreach (array_chunk($filas, 200) as $lote) {
            PuntoMonitoreo::upsert($lote, ['codigo'], ['nombre', 'jurisdiccion', 'latitud', 'longitud', 'updated_at']);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function leerCsv(string $ruta): array
    {
        $handle = fopen($ruta, 'r');
        $encabezado = array_map(fn ($c) => trim((string) $c, " \t\n\r\0\x0B\u{FEFF}"), fgetcsv($handle, escape: ''));
        $filas = [];

        while (($linea = fgetcsv($handle, escape: '')) !== false) {
            if ($linea === [null]) {
                continue;
            }

            $f = array_combine($encabezado, array_map('trim', $linea));

            if (! array_key_exists($f['jurisdiccion'], PuntoMonitoreo::JURISDICCIONES)) {
                throw new RuntimeException("Jurisdicción inválida en PM {$f['codigo']}: {$f['jurisdiccion']}");
            }

            $filas[] = [
                'codigo' => $f['codigo'],
                'nombre' => $f['nombre'],
                'jurisdiccion' => $f['jurisdiccion'],
                'latitud' => $f['latitud'] !== '' ? (float) $f['latitud'] : null,
                'longitud' => $f['longitud'] !== '' ? (float) $f['longitud'] : null,
            ];
        }

        fclose($handle);

        return $filas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function placeholders(string $jurisdiccion, string $prefijo, int $cantidad): array
    {
        return array_map(fn (int $n) => [
            'codigo' => sprintf('PM-%s-%03d', $prefijo, $n),
            'nombre' => sprintf('PM %s %03d (placeholder)', $jurisdiccion, $n),
            'jurisdiccion' => $jurisdiccion,
            'latitud' => fake()->latitude(-32.44, -32.38),
            'longitud' => fake()->longitude(-63.28, -63.20),
        ], range(1, $cantidad));
    }
}
