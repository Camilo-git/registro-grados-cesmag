<?php

namespace App\Filament\Widgets;

use App\Models\Graduate;
use App\Models\Guest;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GraduationStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        // Base: graduados que siguen vigentes (no retirados del listado oficial).
        $base = Graduate::whereNull('removed_from_list_at');

        $totalGraduates = (clone $base)->count();

        // "Confirmado" = ya llenó el Form (profile_confirmed_at lo pone solo
        // FormsResponsesImporter, nunca se edita a mano).
        $confirmedCount = (clone $base)->whereNotNull('profile_confirmed_at')->count();

        // Los que faltan por registrar = simplemente la diferencia. En este
        // flujo, "no confirmado" y "no ha registrado invitados" son lo mismo,
        // porque registrar invitados solo pasa al llenar el Form.
        $notRegistered = $totalGraduates - $confirmedCount;

        // Caso límite: confirmó el perfil pero solo le quedó guardado 1 invitado
        // (normalmente por un error de validación en el invitado 2, ej. cédula
        // repetida o menor de edad). Puede dar 0 mientras no ocurra ese caso.
        $withOneGuest = (clone $base)
            ->whereNotNull('profile_confirmed_at')
            ->has('guests', '=', 1)
            ->count();

        $guestsRegistered = Guest::count();
        // attended_at no nulo = asistencia real tomada el día de la ceremonia,
        // NO lo mismo que "registrado" (eso solo significa que llenó el Form).
        $guestsAttended = Guest::whereNotNull('attended_at')->count();

        $graduatesAttended = (clone $base)->whereNotNull('attended_at')->count();

        return [
            Stat::make('Total de graduados', $totalGraduates)
                ->description('Según el último listado importado')
                ->icon('heroicon-o-academic-cap'),

            Stat::make('Perfil confirmado', $confirmedCount)
                ->description($totalGraduates > 0
                    ? round($confirmedCount / $totalGraduates * 100).'% del total'
                    : 'Sin graduados todavía')
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Aún no han registrado', $notRegistered)
                ->description('No han llenado el formulario todavía')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($notRegistered > 0 ? 'warning' : 'success'),

            Stat::make('Con un solo invitado', $withOneGuest)
                ->description('Caso límite: le faltó uno por un error de validación')
                ->icon('heroicon-o-user')
                ->color($withOneGuest > 0 ? 'warning' : 'success'),

            // --- A partir de aquí, asistencia real del día del evento ---
            Stat::make('Invitados que ya ingresaron', $guestsAttended)
                ->description("de {$guestsRegistered} registrados en total")
                ->icon('heroicon-o-user-group')
                ->color('success'),

            Stat::make('Graduandos que ya ingresaron', $graduatesAttended)
                ->description("de {$confirmedCount} confirmados")
                ->icon('heroicon-o-academic-cap')
                ->color('success'),
        ];
    }
}