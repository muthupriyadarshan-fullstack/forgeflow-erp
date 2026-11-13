import React from 'react';
import { DashboardData } from '../types/erp';
import {
    AlertTriangle,
    ArrowDownRight,
    ArrowUpRight,
    Boxes,
    Clock,
    Factory,
    FileSpreadsheet,
    IndianRupee,
    Layers,
    Package,
    ShieldCheck,
    ShoppingCart,
    TrendingUp,
} from 'lucide-react';

interface DashboardViewProps {
    data: DashboardData | null;
    loading: boolean;
    onNavigate: (tab: any) => void;
}

export const DashboardView: React.FC<DashboardViewProps> = ({ data, loading, onNavigate }) => {
    if (loading || !data) {
        return (
            <div className="p-8 flex items-center justify-center min-h-[500px]">
                <div className="flex flex-col items-center space-y-3">
                    <div className="w-10 h-10 border-4 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
                    <p className="text-sm text-slate-500 font-medium">Aggregating industrial ERP metrics...</p>
                </div>
            </div>
        );
    }

    const { metrics, low_stock_items, monthly_trends, recent_movements } = data;

    const formatInr = (val: number) => {
        return '₹' + Number(val || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
    };

    return (
        <div className="space-y-6">
            {/* Top Metric Cards */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                {/* 1. Production Output */}
                <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs hover:shadow-md transition">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Production</span>
                        <div className="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                            <Factory className="w-4 h-4" />
                        </div>
                    </div>
                    <div className="mt-2 flex items-baseline justify-between">
                        <div className="text-2xl font-bold text-slate-900">{formatInr(metrics.total_production_value)}</div>
                    </div>
                    <div className="mt-1 flex items-center text-xs text-slate-500">
                        <span className="font-semibold text-blue-600 mr-1.5">{metrics.total_production_volume} units</span>
                        <span>manufactured</span>
                    </div>
                    <div className="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                        <span className="text-slate-500">Shop Efficiency</span>
                        <span className="font-bold text-emerald-600">{metrics.production_efficiency_pct}%</span>
                    </div>
                </div>

                {/* 2. Total Inventory Valuation */}
                <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs hover:shadow-md transition">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Inventory Valuation</span>
                        <div className="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            <Layers className="w-4 h-4" />
                        </div>
                    </div>
                    <div className="mt-2 flex items-baseline justify-between">
                        <div className="text-2xl font-bold text-slate-900">{formatInr(metrics.total_inventory_valuation)}</div>
                    </div>
                    <div className="mt-1 flex items-center text-xs text-slate-500">
                        <span className="font-semibold text-slate-700 mr-1.5">Raw: {formatInr(metrics.raw_material_value)}</span>
                    </div>
                    <div className="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                        <span className="text-slate-500">Finished Goods</span>
                        <span className="font-bold text-slate-800">{formatInr(metrics.finished_goods_value)}</span>
                    </div>
                </div>

                {/* 3. Procurement & Orders */}
                <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs hover:shadow-md transition">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Pending Purchase</span>
                        <div className="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                            <ShoppingCart className="w-4 h-4" />
                        </div>
                    </div>
                    <div className="mt-2 flex items-baseline justify-between">
                        <div className="text-2xl font-bold text-slate-900">{formatInr(metrics.pending_purchase_orders_value)}</div>
                    </div>
                    <div className="mt-1 flex items-center text-xs text-slate-500">
                        <span className="font-semibold text-amber-600 mr-1.5">{metrics.pending_purchase_orders_count} Orders</span>
                        <span>awaiting delivery/GRN</span>
                    </div>
                    <div className="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                        <span className="text-slate-500">This Month</span>
                        <span className="font-bold text-slate-800">{formatInr(metrics.monthly_procurement_value)}</span>
                    </div>
                </div>

                {/* 4. Plant Quality & Scrap */}
                <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs hover:shadow-md transition">
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Quality & Reorder</span>
                        <div className="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center">
                            <AlertTriangle className="w-4 h-4" />
                        </div>
                    </div>
                    <div className="mt-2 flex items-baseline justify-between">
                        <div className="text-2xl font-bold text-slate-900">
                            {metrics.low_stock_count}{' '}
                            <span className="text-xs font-normal text-slate-500">alerts</span>
                        </div>
                        <span className="px-2 py-0.5 rounded text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                            Action Needed
                        </span>
                    </div>
                    <div className="mt-1 flex items-center text-xs text-slate-500">
                        <span>Items below reorder point</span>
                    </div>
                    <div className="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                        <span className="text-slate-500">Avg Scrap Rate</span>
                        <span className="font-bold text-rose-600">{metrics.scrap_rate_pct}%</span>
                    </div>
                </div>
            </div>

            {/* Middle Row: Monthly Operations Trend & Low Stock Warning */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Monthly Trend Chart */}
                <div className="lg:col-span-2 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs">
                    <div className="flex items-center justify-between mb-4">
                        <div>
                            <h3 className="font-bold text-slate-900 text-sm">Monthly Procurement vs Production Output</h3>
                            <p className="text-xs text-slate-500">6-Month historical comparison of manufacturing vs procurement expenditure</p>
                        </div>
                        <div className="flex items-center space-x-3 text-xs">
                            <span className="flex items-center text-slate-600">
                                <span className="w-3 h-3 rounded-xs bg-indigo-600 mr-1.5"></span> Procurement
                            </span>
                            <span className="flex items-center text-slate-600">
                                <span className="w-3 h-3 rounded-xs bg-emerald-500 mr-1.5"></span> Production
                            </span>
                        </div>
                    </div>

                    <div className="space-y-4 pt-2">
                        {monthly_trends.map((item, idx) => {
                            const maxVal = Math.max(
                                ...monthly_trends.map((t) => Math.max(t.procurement, t.production)),
                                100000
                            );
                            const procPct = Math.min(100, Math.round((item.procurement / maxVal) * 100));
                            const prodPct = Math.min(100, Math.round((item.production / maxVal) * 100));

                            return (
                                <div key={idx} className="space-y-1">
                                    <div className="flex justify-between text-xs font-semibold text-slate-700">
                                        <span>{item.month}</span>
                                        <span className="text-slate-500 text-[11px]">
                                            PO: {formatInr(item.procurement)} | Prod: {formatInr(item.production)}
                                        </span>
                                    </div>
                                    <div className="h-2.5 w-full bg-slate-100 rounded-full overflow-hidden flex">
                                        <div
                                            className="bg-indigo-600 h-full rounded-l-full transition-all duration-500"
                                            style={{ width: `${procPct}%` }}
                                            title={`Procurement: ${formatInr(item.procurement)}`}
                                        ></div>
                                        <div
                                            className="bg-emerald-500 h-full rounded-r-full transition-all duration-500"
                                            style={{ width: `${prodPct}%` }}
                                            title={`Production: ${formatInr(item.production)}`}
                                        ></div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* Low Stock Alerts Card */}
                <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs flex flex-col">
                    <div className="flex items-center justify-between mb-3">
                        <div className="flex items-center space-x-2">
                            <AlertTriangle className="w-4 h-4 text-amber-600" />
                            <h3 className="font-bold text-slate-900 text-sm">Low-Stock Reorder Alerts</h3>
                        </div>
                        <span className="text-xs font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200">
                            {low_stock_items.length} Critical
                        </span>
                    </div>

                    <p className="text-xs text-slate-500 mb-3">Materials below minimum safety buffer require immediate purchase requisitions.</p>

                    <div className="space-y-2.5 overflow-y-auto max-h-72 flex-1 pr-1">
                        {low_stock_items.length === 0 ? (
                            <div className="p-4 text-center text-xs text-slate-400 bg-slate-50 rounded-xl">
                                All raw materials and consumables are above safety thresholds.
                            </div>
                        ) : (
                            low_stock_items.map((item) => (
                                <div
                                    key={item.id}
                                    className="p-3 bg-amber-50/60 border border-amber-200/70 rounded-xl flex items-center justify-between text-xs"
                                >
                                    <div>
                                        <div className="font-bold text-slate-900">{item.name}</div>
                                        <div className="text-[11px] text-slate-500 font-mono">
                                            {item.sku} • {item.category}
                                        </div>
                                        <div className="text-[11px] text-amber-800 font-semibold mt-0.5">
                                            Available: <strong>{item.current_stock} {item.unit}</strong> (Min: {item.reorder_level} {item.unit})
                                        </div>
                                    </div>
                                    <button
                                        onClick={() => onNavigate('purchasing')}
                                        className="px-2.5 py-1 bg-amber-600 hover:bg-amber-700 text-white font-semibold rounded-lg text-[11px] shrink-0 transition"
                                    >
                                        Procure
                                    </button>
                                </div>
                            ))
                        )}
                    </div>

                    <button
                        onClick={() => onNavigate('inventory')}
                        className="mt-4 w-full text-center text-xs font-semibold text-blue-600 hover:text-blue-800 py-1.5 border border-blue-200 rounded-xl bg-blue-50/50 hover:bg-blue-50 transition"
                    >
                        View Comprehensive Inventory &rarr;
                    </button>
                </div>
            </div>

            {/* Bottom Row: Recent Immutable Stock Movement Ledger Feed */}
            <div className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 className="font-bold text-slate-900 text-sm">Real-Time Stock Movement Ledger</h3>
                        <p className="text-xs text-slate-500">Immutable double-entry inventory audit transactions</p>
                    </div>
                    <button
                        onClick={() => onNavigate('inventory')}
                        className="text-xs font-semibold text-blue-600 hover:underline"
                    >
                        View Full Stock Ledger &rarr;
                    </button>
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs">
                        <thead className="bg-slate-50/80 text-slate-500 uppercase font-semibold text-[10px] tracking-wider border-b border-slate-100">
                            <tr>
                                <th className="px-6 py-3">Movement Ref</th>
                                <th className="px-6 py-3">Type</th>
                                <th className="px-6 py-3">Item / SKU</th>
                                <th className="px-6 py-3">Source Warehouse</th>
                                <th className="px-6 py-3">Destination Warehouse</th>
                                <th className="px-6 py-3 text-right">Quantity</th>
                                <th className="px-6 py-3 text-right">Total Cost</th>
                                <th className="px-6 py-3">Batch / Lot</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {recent_movements.map((mov) => {
                                const typeColors: Record<string, string> = {
                                    opening_stock: 'bg-slate-100 text-slate-800 border-slate-200',
                                    purchase_receipt: 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                    production_consumption: 'bg-amber-50 text-amber-800 border-amber-200',
                                    production_output: 'bg-blue-50 text-blue-800 border-blue-200',
                                    stock_transfer: 'bg-purple-50 text-purple-800 border-purple-200',
                                    stock_adjustment: 'bg-orange-50 text-orange-800 border-orange-200',
                                    damaged_goods: 'bg-rose-50 text-rose-800 border-rose-200',
                                };

                                return (
                                    <tr key={mov.id} className="hover:bg-slate-50/60 transition">
                                        <td className="px-6 py-3 font-mono font-bold text-slate-900">{mov.movement_number}</td>
                                        <td className="px-6 py-3">
                                            <span
                                                className={`px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border ${
                                                    typeColors[mov.movement_type] || 'bg-slate-100 text-slate-800'
                                                }`}
                                            >
                                                {mov.movement_type.replace('_', ' ')}
                                            </span>
                                        </td>
                                        <td className="px-6 py-3">
                                            <div className="font-semibold text-slate-800">{mov.product?.name}</div>
                                            <div className="text-[10px] font-mono text-slate-400">{mov.product?.sku}</div>
                                        </td>
                                        <td className="px-6 py-3 text-slate-600">{mov.source_warehouse?.name || '—'}</td>
                                        <td className="px-6 py-3 text-slate-600">{mov.dest_warehouse?.name || '—'}</td>
                                        <td className="px-6 py-3 text-right font-bold text-slate-900">
                                            {mov.quantity} {mov.product?.unit?.symbol}
                                        </td>
                                        <td className="px-6 py-3 text-right font-semibold text-slate-800">{formatInr(mov.total_cost)}</td>
                                        <td className="px-6 py-3 font-mono text-[11px] text-slate-500">{mov.batch_number || '—'}</td>
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

