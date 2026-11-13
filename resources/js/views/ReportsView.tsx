import React, { useEffect, useState } from 'react';
import { api } from '../services/api';
import {
    Download,
    FileSpreadsheet,
    FileText,
    Layers,
    PieChart,
    Printer,
    RefreshCw,
    TrendingUp,
} from 'lucide-react';

export const ReportsView: React.FC = () => {
    const [valuationData, setValuationData] = useState<any | null>(null);
    const [loading, setLoading] = useState(true);

    const loadData = async () => {
        setLoading(true);
        try {
            const data = await api.getValuationReport();
            setValuationData(data);
        } catch (e) {
            console.error('Failed to load valuation report', e);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadData();
    }, []);

    const formatInr = (val: number) => {
        return '₹' + Number(val || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
    };

    return (
        <div className="space-y-6">
            {/* Header & Export Actions */}
            <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 className="text-lg font-bold text-slate-900 tracking-tight">Finance, Costing & Inventory Valuation</h2>
                    <p className="text-xs text-slate-500">
                        Operational financial metrics, weighted average material valuation and CSV ledger data exports
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <a
                        href={api.getExportUrl('stock_ledger')}
                        download
                        className="flex items-center space-x-1.5 px-3 py-2 bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs rounded-xl shadow-xs transition"
                    >
                        <FileSpreadsheet className="w-3.5 h-3.5 text-blue-400" />
                        <span>Export Stock Ledger (CSV)</span>
                    </a>
                    <a
                        href={api.getExportUrl('inventory_valuation')}
                        download
                        className="flex items-center space-x-1.5 px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs rounded-xl shadow-xs transition"
                    >
                        <Download className="w-3.5 h-3.5" />
                        <span>Export Valuation (CSV)</span>
                    </a>
                    <a
                        href={api.getExportUrl('production_orders')}
                        download
                        className="flex items-center space-x-1.5 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs rounded-xl shadow-xs transition"
                    >
                        <FileText className="w-3.5 h-3.5" />
                        <span>Export Work Orders (CSV)</span>
                    </a>
                </div>
            </div>

            {/* Valuation KPI Banner */}
            {valuationData && (
                <div className="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white p-6 rounded-2xl shadow-md border border-slate-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <div className="text-xs font-semibold uppercase tracking-wider text-indigo-300">Total Enterprise Asset Valuation</div>
                        <div className="text-3xl font-extrabold tracking-tight mt-1">{formatInr(valuationData.total_valuation)}</div>
                        <p className="text-xs text-slate-400 mt-1">Calculated via Moving Weighted Average Cost across all active warehouse bins</p>
                    </div>

                    <div className="flex items-center space-x-4 text-xs">
                        <div className="bg-slate-800/80 px-4 py-2.5 rounded-xl border border-slate-700">
                            <span className="text-slate-400 block text-[10px] uppercase font-bold">Valued SKU Batches</span>
                            <span className="font-extrabold text-base text-white">{valuationData.items_count} records</span>
                        </div>
                        <div className="bg-slate-800/80 px-4 py-2.5 rounded-xl border border-slate-700">
                            <span className="text-slate-400 block text-[10px] uppercase font-bold">Accounting Method</span>
                            <span className="font-extrabold text-base text-emerald-400">Weighted Average</span>
                        </div>
                    </div>
                </div>
            )}

            {/* Detailed Valuation Table */}
            <div className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div>
                        <h3 className="font-bold text-slate-900 text-sm">Inventory Asset Valuation Ledger</h3>
                        <p className="text-xs text-slate-500">Granular valuation per item, warehouse, batch, and reserved allocations</p>
                    </div>
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs">
                        <thead className="bg-slate-50 text-slate-500 uppercase font-semibold text-[10px] tracking-wider border-b border-slate-100">
                            <tr>
                                <th className="px-6 py-3.5">SKU & Item Name</th>
                                <th className="px-6 py-3.5">Classification</th>
                                <th className="px-6 py-3.5">Warehouse Location</th>
                                <th className="px-6 py-3.5">Batch / Lot</th>
                                <th className="px-6 py-3.5 text-right">Quantity On Hand</th>
                                <th className="px-6 py-3.5 text-right">Weighted Avg Cost</th>
                                <th className="px-6 py-3.5 text-right">Asset Valuation (INR)</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 font-sans">
                            {valuationData?.rows?.map((row: any, idx: number) => {
                                const typeColors: Record<string, string> = {
                                    raw_material: 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                    work_in_progress: 'bg-purple-50 text-purple-800 border-purple-200',
                                    finished_good: 'bg-blue-50 text-blue-800 border-blue-200',
                                    consumable: 'bg-amber-50 text-amber-800 border-amber-200',
                                };

                                return (
                                    <tr key={idx} className="hover:bg-slate-50/70 transition">
                                        <td className="px-6 py-3.5">
                                            <div className="font-bold text-slate-900">{row.product_name}</div>
                                            <div className="font-mono text-[10px] text-slate-400">{row.sku}</div>
                                        </td>
                                        <td className="px-6 py-3.5">
                                            <span
                                                className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border ${
                                                    typeColors[row.product_type] || 'bg-slate-100'
                                                }`}
                                            >
                                                {row.product_type.replace('_', ' ')}
                                            </span>
                                        </td>
                                        <td className="px-6 py-3.5 text-slate-600">{row.warehouse_name}</td>
                                        <td className="px-6 py-3.5 font-mono text-slate-500">{row.batch_number || 'Standard'}</td>
                                        <td className="px-6 py-3.5 text-right font-mono font-bold text-slate-900">
                                            {row.quantity_on_hand} {row.unit}
                                        </td>
                                        <td className="px-6 py-3.5 text-right font-mono text-slate-700">
                                            {formatInr(row.average_cost_unit)}
                                        </td>
                                        <td className="px-6 py-3.5 text-right font-mono font-extrabold text-slate-900">
                                            {formatInr(row.total_valuation)}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
};

