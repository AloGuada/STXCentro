import type { InfraChartBomba, InfraChartCompresor, InfraChartTanque, InfraChartTransformador } from '@/types/models';
import { CartesianGrid, Legend, Line, LineChart, ReferenceLine, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

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

function hasData(data: Record<string, unknown>[], keys: string[]): boolean {
    return data.some((row) => keys.some((k) => Number(row[k]) !== 0));
}

function EmptyChart() {
    return (
        <div className="flex h-[300px] items-center justify-center text-base-content/50">
            <p>Sin datos para este periodo</p>
        </div>
    );
}

function CompresoresChart({ data }: { data: InfraChartCompresor[] }) {
    if (!hasData(data, ['c1', 'c2', 'c3'])) return <EmptyChart />;

    return (
        <ResponsiveContainer width="100%" height={300}>
            <LineChart data={data}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="mes" tickFormatter={formatMes} />
                <YAxis />
                <Tooltip labelFormatter={formatMes} />
                <Legend />
                <Line type="monotone" dataKey="c1" name="Compresor 1" stroke={COLORS[0]} strokeWidth={2} unit=" hrs" />
                <Line type="monotone" dataKey="c2" name="Compresor 2" stroke={COLORS[1]} strokeWidth={2} unit=" hrs" />
                <Line type="monotone" dataKey="c3" name="Compresor 3" stroke={COLORS[2]} strokeWidth={2} unit=" hrs" />
            </LineChart>
        </ResponsiveContainer>
    );
}

function BombasChart({ data }: { data: InfraChartBomba[] }) {
    if (!hasData(data, ['avg_presion', 'max_presion', 'min_presion'])) return <EmptyChart />;

    const IDEAL = 45; // Centro del rango ideal 40-50 PSI

    const chartData = data.map((d) => ({
        ...d,
        desviacion: d.avg_presion ? +(d.avg_presion - IDEAL).toFixed(2) : 0,
    }));

    return (
        <ResponsiveContainer width="100%" height={300}>
            <LineChart data={chartData}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="mes" tickFormatter={formatMes} />
                <YAxis />
                <Tooltip labelFormatter={formatMes} />
                <Legend />
                <ReferenceLine y={0} stroke="#888" strokeDasharray="3 3" label="Ideal (45 PSI)" />
                <Line type="monotone" dataKey="desviacion" name="Desviación vs ideal" stroke={COLORS[0]} strokeWidth={2} unit=" PSI" />
                <Line type="monotone" dataKey="stddev" name="Dispersión (σ)" stroke={COLORS[2]} strokeWidth={2} unit=" PSI" />
                <Line type="monotone" dataKey="max_presion" name="Máximo" stroke={COLORS[1]} strokeWidth={2} unit=" PSI" dot={false} strokeDasharray="5 5" />
                <Line type="monotone" dataKey="min_presion" name="Mínimo" stroke={COLORS[3]} strokeWidth={2} unit=" PSI" dot={false} strokeDasharray="5 5" />
            </LineChart>
        </ResponsiveContainer>
    );
}

function TransformadoresChart({ data }: { data: InfraChartTransformador[] }) {
    if (!hasData(data, ['total_1', 'total_5', 'lectura_5y5'])) return <EmptyChart />;

    return (
        <ResponsiveContainer width="100%" height={300}>
            <LineChart data={data}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="mes" tickFormatter={formatMes} />
                <YAxis />
                <Tooltip labelFormatter={formatMes} />
                <Legend />
                <Line type="monotone" dataKey="total_1" name="Total 1" stroke={COLORS[0]} strokeWidth={2} unit=" kWh" />
                <Line type="monotone" dataKey="total_5" name="Total 5" stroke={COLORS[1]} strokeWidth={2} unit=" kWh" />
                <Line type="monotone" dataKey="lectura_5y5" name="Lectura 5y5" stroke={COLORS[2]} strokeWidth={2} unit=" kWh" />
            </LineChart>
        </ResponsiveContainer>
    );
}

function TanquesChart({ data }: { data: InfraChartTanque[] }) {
    if (!hasData(data, ['oxigeno_acum', 'argon_acum', 'co2_acum', 'lp_acum'])) return <EmptyChart />;

    return (
        <ResponsiveContainer width="100%" height={300}>
            <LineChart data={data}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="mes" tickFormatter={formatMes} />
                <YAxis />
                <Tooltip labelFormatter={formatMes} />
                <Legend />
                <Line type="monotone" dataKey="oxigeno_acum" name="O₂" stroke={COLORS[0]} strokeWidth={2} unit=" kg" />
                <Line type="monotone" dataKey="argon_acum" name="Ar" stroke={COLORS[1]} strokeWidth={2} unit=" kg" />
                <Line type="monotone" dataKey="co2_acum" name="CO₂" stroke={COLORS[2]} strokeWidth={2} unit=" kg" />
                <Line type="monotone" dataKey="lp_acum" name="LP" stroke={COLORS[3]} strokeWidth={2} unit=" kg" />
            </LineChart>
        </ResponsiveContainer>
    );
}

const chartTitles: Record<ActiveSystem, string> = {
    compresores: 'Horas Laboradas Mensuales (HRS)',
    bombas: 'Variación de Presión vs Ideal (PSI)',
    transformadores: 'Consumos Mensuales (kWh)',
    tanques: 'Consumo Acumulado de Gas (KG)',
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
