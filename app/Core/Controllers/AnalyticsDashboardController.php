<?php

namespace App\Core\Controllers;

use App\Core\Services\AnalyticsReportingService;
use App\Http\Controllers\Controller;
use App\Platform\Security\AuditLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsDashboardController extends Controller
{
    public function __construct(
        protected AnalyticsReportingService $analyticsService,
        protected AuditLogger $auditLogger
    ) {}

    /**
     * Display Executive Analytics Dashboard.
     */
    public function index(Request $request): Response
    {
        $startStr = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endStr = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());

        $startDate = Carbon::parse((string) $startStr);
        $endDate = Carbon::parse((string) $endStr);

        $kpis = $this->analyticsService->getExecutiveKpis($startDate, $endDate);
        $doctorProductivity = $this->analyticsService->getDoctorProductivity($startDate, $endDate);
        $receivablesAging = $this->analyticsService->getReceivablesAging();
        $chairOccupancy = $this->analyticsService->getChairOccupancy($startDate, $endDate);

        return Inertia::render('Clinic/Analytics/Index', [
            'startDate' => $startDate->toDateString(),
            'endDate' => $endDate->toDateString(),
            'kpis' => $kpis,
            'doctorProductivity' => $doctorProductivity,
            'receivablesAging' => $receivablesAging,
            'chairOccupancy' => $chairOccupancy,
        ]);
    }

    /**
     * Export Executive Analytics Report to CSV.
     */
    /**
     * Export Executive Analytics Report to CSV.
     */
    public function export(Request $request): \Illuminate\Http\Response
    {
        $startStr = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endStr = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());

        $startDate = Carbon::parse((string) $startStr);
        $endDate = Carbon::parse((string) $endStr);

        $kpis = $this->analyticsService->getExecutiveKpis($startDate, $endDate);

        $this->auditLogger->logTenant('analytics.report_exported', 'AnalyticsReport', 'financial_kpis', [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
        ]);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="reporte_ejecutivo_bsdental.csv"',
        ];

        $handle = fopen('php://temp', 'r+');
        if ($handle !== false) {
            fputcsv($handle, ['BSDental — Reporte Ejecutivo Gerencial']);
            fputcsv($handle, ['Periodo:', "{$startDate->toDateString()} al {$endDate->toDateString()}"]);
            fputcsv($handle, []);
            fputcsv($handle, ['Métrica', 'Valor ($)']);
            fputcsv($handle, ['Producción Clínica Ejecutada', number_format($kpis['production'], 2)]);
            fputcsv($handle, ['Total Cobrado Bruto', number_format($kpis['gross_collected'], 2)]);
            fputcsv($handle, ['Reembolsos y Devoluciones', number_format($kpis['refunds'], 2)]);
            fputcsv($handle, ['Total Cobrado Neto', number_format($kpis['net_collected'], 2)]);
            fputcsv($handle, ['Cuentas por Cobrar Pendientes (CxC)', number_format($kpis['receivables'], 2)]);
            fputcsv($handle, ['Costos Directos de Insumos / Materiales', number_format($kpis['direct_material_costs'], 2)]);
            fputcsv($handle, ['Costos de Laboratorios Dentales', number_format($kpis['direct_lab_costs'], 2)]);
            fputcsv($handle, ['Comisiones Médicas Devengadas', number_format($kpis['professional_commissions'], 2)]);
            fputcsv($handle, ['Margen de Contribución Gerencial', number_format($kpis['contribution_margin'], 2)]);
            fputcsv($handle, ['Flujo Neto de Caja Atribuible', number_format($kpis['net_cash_flow'], 2)]);

            rewind($handle);
            $csv = (string) stream_get_contents($handle);
            fclose($handle);
        } else {
            $csv = '';
        }

        return response($csv, 200, $headers);
    }
}
