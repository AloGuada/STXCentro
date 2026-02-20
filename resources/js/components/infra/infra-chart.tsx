import type { InfraChartBomba, InfraChartCompresor, InfraChartTanque, InfraChartTransformador } from '@/types/models';
import { CartesianGrid, Legend, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

const MESES = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

type ActiveSystem = 'compresores' | 'bombas' | 'transformadores' | 'tanques' | 'ptar';

type Props = {
    activeSystem: ActiveSystem;
    chartCompresores?: InfraChartCompresor[];
    chartBombas?: InfraChartBomba[];
    chartTransformadores?: InfraChartTransformador[];
    chartTanques?: InfraChartTanque[];
};

const COLORS = ['#36d399', '#f87272', '#fbbd23', '#3abff8', '#a78bfa'];

function formatMes(mes: string): string {
    const idx = parseInt(mes, 10) - 1;
    return MESES[idx] ?? mes;
}

function CompresoresChart({ data }: { data: InfraChartCompresor[] }) {
    return (
        <ResponsiveContainer width="100%" height={300}>
            <LineChart data={data}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="mes" tickFormatter={formatMes} />
                <YAxis />
                <Tooltip labelFormatter={formatMes} />
                <Legend />
                <Line type="monotone" dataKey="c1" name="Compresor 1" stroke={COLORS[0]} strokeWidth={2} />
                <Line type="monotone" dataKey="c2" name="Compresor 2" stroke={COLORS[1]} strokeWidth={2} />
                <Line type="monotone" dataKey="c3" name="Compresor 3" stroke={COLORS[2]} strokeWidth={2} />
            </LineChart>
        </ResponsiveContainer>
    );
}

function BombasChart({ data }: { data: InfraChartBomba[] }) {
    return (
        <ResponsiveContainer width="100%" height={300}>
            <LineChart data={data}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="mes" tickFormatter={formatMes} />
                <YAxis />
                <Tooltip labelFormatter={formatMes} />
                <Legend />
                <Line type="monotone" dataKey="avg_presion" name="Promedio" stroke={COLORS[0]} strokeWidth={2} />
                <Line type="monotone" dataKey="max_presion" name="Máximo" stroke={COLORS[1]} strokeWidth={2} />
                <Line type="monotone" dataKey="min_presion" name="Mínimo" stroke={COLORS[3]} strokeWidth={2} />
            </LineChart>
        </ResponsiveContainer>
    );
}

function TransformadoresChart({ data }: { data: InfraChartTransformador[] }) {
    return (
        <ResponsiveContainer width="100%" height={300}>
            <LineChart data={data}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="mes" tickFormatter={formatMes} />
                <YAxis />
                <Tooltip labelFormatter={formatMes} />
                <Legend />
                <Line type="monotone" dataKey="total_1" name="Total 1" stroke={COLORS[0]} strokeWidth={2} />
                <Line type="monotone" dataKey="total_5" name="Total 5" stroke={COLORS[1]} strokeWidth={2} />
                <Line type="monotone" dataKey="lectura_5y5" name="Lectura 5y5" stroke={COLORS[2]} strokeWidth={2} />
            </LineChart>
        </ResponsiveContainer>
    );
}

function TanquesChart({ data }: { data: InfraChartTanque[] }) {
    return (
        <ResponsiveContainer width="100%" height={300}>
            <LineChart data={data}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="mes" tickFormatter={formatMes} />
                <YAxis />
                <Tooltip labelFormatter={formatMes} />
                <Legend />
                <Line type="monotone" dataKey="oxigeno_acum" name="O₂" stroke={COLORS[0]} strokeWidth={2} />
                <Line type="monotone" dataKey="argon_acum" name="Ar" stroke={COLORS[1]} strokeWidth={2} />
                <Line type="monotone" dataKey="co2_acum" name="CO₂" stroke={COLORS[2]} strokeWidth={2} />
                <Line type="monotone" dataKey="lp_acum" name="LP" stroke={COLORS[3]} strokeWidth={2} />
            </LineChart>
        </ResponsiveContainer>
    );
}

const chartTitles: Record<ActiveSystem, string> = {
    compresores: 'Horas Laboradas Mensuales',
    bombas: 'Estadísticas de Presión Mensual',
    transformadores: 'Consumos Mensuales',
    tanques: 'Consumo Acumulado de Gas (kg)',
    ptar: '',
};

export default function InfraChart({ activeSystem, chartCompresores, chartBombas, chartTransformadores, chartTanques }: Props) {
    if (activeSystem === 'ptar') {
        return (
            <div className="flex h-[300px] items-center justify-center text-base-content/50">
                <p>PTAR no tiene gráfica de tendencias</p>
            </div>
        );
    }

    return (
        <div>
            <h3 className="mb-4 text-center text-lg font-medium">{chartTitles[activeSystem]}</h3>
            {activeSystem === 'compresores' && chartCompresores && <CompresoresChart data={chartCompresores} />}
            {activeSystem === 'bombas' && chartBombas && <BombasChart data={chartBombas} />}
            {activeSystem === 'transformadores' && chartTransformadores && <TransformadoresChart data={chartTransformadores} />}
            {activeSystem === 'tanques' && chartTanques && <TanquesChart data={chartTanques} />}
        </div>
    );
}
