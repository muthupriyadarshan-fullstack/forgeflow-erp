import React, { useEffect, useState } from 'react';
import { api } from '../services/api';
import { BillOfMaterial, Product } from '../types/erp';
import {
    AlertCircle,
    Boxes,
    CheckCircle2,
    ChevronRight,
    Layers,
    ListTree,
    Plus,
    RefreshCw,
    Search,
    ShieldAlert,
} from 'lucide-react';

export const ProductsView: React.FC = () => {
    const [products, setProducts] = useState<Product[]>([]);
    const [boms, setBoms] = useState<BillOfMaterial[]>([]);
    const [loading, setLoading] = useState(true);
    const [activeTab, setActiveTab] = useState<'products' | 'bom'>('products');
    const [selectedType, setSelectedType] = useState<string>('');
    const [searchQuery, setSearchQuery] = useState('');

    // Selected BOM for explosion
    const [selectedBomId, setSelectedBomId] = useState<number | null>(null);
    const [explodeQty, setExplodeQty] = useState<number>(5);
    const [explosionData, setExplosionData] = useState<any | null>(null);
    const [availabilityData, setAvailabilityData] = useState<any | null>(null);
    const [calculatingBom, setCalculatingBom] = useState(false);

    const loadData = async () => {
        setLoading(true);
        try {
            const params: Record<string, string> = {};
            if (selectedType) params.type = selectedType;
            if (searchQuery) params.search = searchQuery;

            const [pRes, bRes] = await Promise.all([
                api.getProducts(params),
                api.getBOMs(),
            ]);
            setProducts(pRes.data || []);
            setBoms(bRes.data || []);
            if (bRes.data && bRes.data.length > 0 && !selectedBomId) {
                setSelectedBomId(bRes.data[0].id);
            }
        } catch (e) {
            console.error('Failed to load products', e);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadData();
    }, [selectedType]);

    const handleExplodeBom = async () => {
        if (!selectedBomId) return;
        setCalculatingBom(true);
        try {
            const [exp, avail] = await Promise.all([
                api.explodeBOM(selectedBomId, explodeQty),
                api.checkBOMAvailability(selectedBomId, explodeQty),
            ]);
            setExplosionData(exp);
            setAvailabilityData(avail);
        } catch (e: any) {
            alert('Failed to calculate BOM: ' + e.message);
        } finally {
            setCalculatingBom(false);
        }
    };

    useEffect(() => {
        if (selectedBomId && activeTab === 'bom') {
            handleExplodeBom();
        }
    }, [selectedBomId, activeTab]);

    const formatInr = (val: number) => {
        return '₹' + Number(val || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
    };

    return (
        <div className="space-y-6">
            {/* View Sub-header */}
            <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 className="text-lg font-bold text-slate-900 tracking-tight">Product Master & Bills of Materials</h2>
                    <p className="text-xs text-slate-500">Manage raw material specs, finished goods catalogue and multi-level BOM explosion</p>
                </div>

                <div className="flex items-center space-x-2 bg-slate-100 p-1 rounded-xl">
                    <button
                        onClick={() => setActiveTab('products')}
                        className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition ${
                            activeTab === 'products'
                                ? 'bg-white text-slate-900 shadow-xs'
                                : 'text-slate-600 hover:text-slate-900'
                        }`}
                    >
                        Product Master ({products.length})
                    </button>
                    <button
                        onClick={() => setActiveTab('bom')}
                        className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition ${
                            activeTab === 'bom'
                                ? 'bg-white text-slate-900 shadow-xs'
                                : 'text-slate-600 hover:text-slate-900'
                        }`}
                    >
                        Multi-Level BOM Explorer ({boms.length})
                    </button>
                </div>
            </div>

            {/* TAB 1: PRODUCT MASTER */}
            {activeTab === 'products' && (
                <div className="space-y-4">
                    {/* Filters & Search */}
                    <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex flex-wrap items-center justify-between gap-3">
                        <div className="flex items-center space-x-2 flex-1 min-w-[240px]">
                            <div className="relative w-full max-w-sm">
                                <Search className="w-4 h-4 absolute left-3 top-2.5 text-slate-400" />
                                <input
                                    type="text"
                                    placeholder="Search by SKU, Product Name or HSN..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && loadData()}
                                    className="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-slate-50/50"
                                />
                            </div>
                            <button
                                onClick={loadData}
                                className="px-3 py-1.5 bg-slate-900 text-white rounded-lg text-xs font-semibold hover:bg-slate-800 transition"
                            >
                                Search
                            </button>
                        </div>

                        {/* Type Filter Pills */}
                        <div className="flex items-center space-x-1 overflow-x-auto text-xs">
                            {[
                                { key: '', label: 'All Items' },
                                { key: 'raw_material', label: 'Raw Materials' },
                                { key: 'work_in_progress', label: 'Work-In-Progress' },
                                { key: 'finished_good', label: 'Finished Goods' },
                                { key: 'consumable', label: 'Consumables' },
                            ].map((type) => (
                                <button
                                    key={type.key}
                                    onClick={() => setSelectedType(type.key)}
                                    className={`px-2.5 py-1 rounded-lg font-medium transition ${
                                        selectedType === type.key
                                            ? 'bg-blue-50 text-blue-700 font-bold border border-blue-200'
                                            : 'text-slate-600 hover:bg-slate-100'
                                    }`}
                                >
                                    {type.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    {/* Products Table */}
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead className="bg-slate-50 text-slate-500 uppercase font-semibold text-[10px] tracking-wider border-b border-slate-100">
                                    <tr>
                                        <th className="px-6 py-3.5">SKU & Classification</th>
                                        <th className="px-6 py-3.5">Category</th>
                                        <th className="px-6 py-3.5">HSN Code</th>
                                        <th className="px-6 py-3.5 text-right">Standard Cost</th>
                                        <th className="px-6 py-3.5 text-right">Selling Price</th>
                                        <th className="px-6 py-3.5 text-right">Total On Hand</th>
                                        <th className="px-6 py-3.5 text-right">Reorder Level</th>
                                        <th className="px-6 py-3.5 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {products.map((p) => {
                                        const typeBadge: Record<string, { label: string; cls: string }> = {
                                            raw_material: { label: 'Raw Material', cls: 'bg-emerald-50 text-emerald-800 border-emerald-200' },
                                            work_in_progress: { label: 'WIP Blank', cls: 'bg-purple-50 text-purple-800 border-purple-200' },
                                            finished_good: { label: 'Finished Good', cls: 'bg-blue-50 text-blue-800 border-blue-200' },
                                            consumable: { label: 'Consumable', cls: 'bg-amber-50 text-amber-800 border-amber-200' },
                                        };

                                        const badge = typeBadge[p.type] || { label: p.type, cls: 'bg-slate-100 text-slate-700' };
                                        const onHand = Number(p.total_quantity_on_hand || 0);
                                        const reorder = Number(p.reorder_level || 0);
                                        const isLow = reorder > 0 && onHand <= reorder;

                                        return (
                                            <tr key={p.id} className="hover:bg-slate-50/70 transition">
                                                <td className="px-6 py-3.5">
                                                    <div className="font-bold text-slate-900">{p.name}</div>
                                                    <div className="flex items-center space-x-2 mt-0.5">
                                                        <span className="font-mono text-[11px] text-slate-500 font-semibold">{p.sku}</span>
                                                        <span className={`px-1.5 py-0.2 rounded text-[10px] font-bold border ${badge.cls}`}>
                                                            {badge.label}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-6 py-3.5 text-slate-600">{p.category?.name || '—'}</td>
                                                <td className="px-6 py-3.5 font-mono text-slate-600">{p.hsn_code || '—'}</td>
                                                <td className="px-6 py-3.5 text-right font-mono font-semibold text-slate-800">
                                                    {formatInr(p.standard_cost)}/{p.unit?.symbol}
                                                </td>
                                                <td className="px-6 py-3.5 text-right font-mono font-semibold text-slate-800">
                                                    {p.selling_price > 0 ? formatInr(p.selling_price) : '—'}
                                                </td>
                                                <td className="px-6 py-3.5 text-right">
                                                    <span className={`font-mono font-bold ${isLow ? 'text-rose-600' : 'text-slate-900'}`}>
                                                        {onHand} {p.unit?.symbol}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-3.5 text-right font-mono text-slate-500">
                                                    {reorder} {p.unit?.symbol}
                                                </td>
                                                <td className="px-6 py-3.5 text-center">
                                                    {isLow ? (
                                                        <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                                            Low Stock
                                                        </span>
                                                    ) : (
                                                        <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                            Optimal
                                                        </span>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            )}

            {/* TAB 2: MULTI-LEVEL BOM EXPLORER */}
            {activeTab === 'bom' && (
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* BOM Selector List */}
                    <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                        <div className="flex items-center space-x-2 mb-4">
                            <ListTree className="w-4 h-4 text-blue-600" />
                            <h3 className="font-bold text-slate-900 text-sm">Engineered BOM Catalog</h3>
                        </div>

                        <div className="space-y-2">
                            {boms.map((bom) => {
                                const isSelected = selectedBomId === bom.id;
                                return (
                                    <button
                                        key={bom.id}
                                        onClick={() => setSelectedBomId(bom.id)}
                                        className={`w-full p-3.5 rounded-xl border text-left transition flex items-center justify-between ${
                                            isSelected
                                                ? 'bg-blue-50/70 border-blue-300 text-blue-950 font-bold shadow-xs'
                                                : 'border-slate-200 hover:bg-slate-50 text-slate-700'
                                        }`}
                                    >
                                        <div>
                                            <div className="font-bold text-xs">{bom.name}</div>
                                            <div className="text-[11px] font-mono text-slate-500 mt-0.5">
                                                Code: {bom.code} • Rev {bom.version}
                                            </div>
                                            <div className="text-[10px] text-blue-700 mt-1">
                                                Assembly for: {bom.product?.name}
                                            </div>
                                        </div>
                                        <ChevronRight className={`w-4 h-4 shrink-0 ${isSelected ? 'text-blue-600' : 'text-slate-400'}`} />
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    {/* BOM Explosion & Availability Engine */}
                    <div className="lg:col-span-2 space-y-4">
                        {/* Simulation Header */}
                        <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <span className="text-[11px] font-bold text-blue-600 uppercase tracking-wider">Multi-Level MRP Engine</span>
                                <h3 className="font-bold text-slate-900 text-sm">
                                    Explosion & Material Availability Check
                                </h3>
                            </div>

                            <div className="flex items-center space-x-3">
                                <label className="text-xs font-semibold text-slate-700 flex items-center space-x-2">
                                    <span>Plan Qty:</span>
                                    <input
                                        type="number"
                                        min="1"
                                        value={explodeQty}
                                        onChange={(e) => setExplodeQty(Number(e.target.value) || 1)}
                                        className="w-20 px-2 py-1 text-xs border border-slate-200 rounded-lg text-center font-bold focus:ring-2 focus:ring-blue-500"
                                    />
                                </label>

                                <button
                                    onClick={handleExplodeBom}
                                    disabled={calculatingBom}
                                    className="flex items-center space-x-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs rounded-lg transition"
                                >
                                    <RefreshCw className={`w-3.5 h-3.5 ${calculatingBom ? 'animate-spin' : ''}`} />
                                    <span>Explode BOM</span>
                                </button>
                            </div>
                        </div>

                        {/* Availability Status Card */}
                        {availabilityData && (
                            <div
                                className={`p-4 rounded-xl border flex items-center justify-between text-xs font-semibold ${
                                    availabilityData.can_produce
                                        ? 'bg-emerald-50 text-emerald-900 border-emerald-200'
                                        : 'bg-rose-50 text-rose-900 border-rose-200'
                                }`}
                            >
                                <div className="flex items-center space-x-2.5">
                                    {availabilityData.can_produce ? (
                                        <CheckCircle2 className="w-5 h-5 text-emerald-600" />
                                    ) : (
                                        <AlertCircle className="w-5 h-5 text-rose-600" />
                                    )}
                                    <div>
                                        <div className="font-bold text-sm">
                                            {availabilityData.can_produce
                                                ? '100% Raw Materials Available Across Warehouses'
                                                : `${availabilityData.bottlenecks?.length} Raw Material Shortage(s) Detected`}
                                        </div>
                                        <div className="text-[11px] opacity-90 font-normal">
                                            {availabilityData.can_produce
                                                ? 'Sufficient inventory on hand to issue and start production order immediately.'
                                                : 'Purchase requisitions required before issuing material to shop floor.'}
                                        </div>
                                    </div>
                                </div>

                                {explosionData && (
                                    <div className="text-right">
                                        <span className="text-[10px] uppercase tracking-wider block opacity-75">Estimated Material Cost</span>
                                        <span className="text-base font-extrabold">{formatInr(explosionData.total_material_cost)}</span>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Exploded Component Tree */}
                        {explosionData && (
                            <div className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                                <div className="px-6 py-3.5 border-b border-slate-100 bg-slate-50 text-slate-500 text-[10px] uppercase font-bold tracking-wider">
                                    Exploded Component Breakdown for {explodeQty} Output Units
                                </div>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-xs">
                                        <thead className="bg-slate-50/50 text-slate-500 font-semibold text-[10px] uppercase border-b border-slate-100">
                                            <tr>
                                                <th className="px-6 py-3">Component / Sub-Assembly</th>
                                                <th className="px-6 py-3 text-right">Net Qty</th>
                                                <th className="px-6 py-3 text-right">Scrap %</th>
                                                <th className="px-6 py-3 text-right">Gross Required</th>
                                                <th className="px-6 py-3 text-right">Available Stock</th>
                                                <th className="px-6 py-3 text-right">Est Cost</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {explosionData.components?.map((c: any, i: number) => {
                                                const matchAvail = availabilityData?.materials?.find(
                                                    (m: any) => m.product_id === c.component_id
                                                );
                                                const isShort = matchAvail && !matchAvail.is_sufficient;

                                                return (
                                                    <tr key={i} className={`hover:bg-slate-50/60 ${isShort ? 'bg-rose-50/30' : ''}`}>
                                                        <td className="px-6 py-3">
                                                            <div className="flex items-center space-x-2">
                                                                {c.is_sub_assembly ? (
                                                                    <span className="px-1.5 py-0.5 rounded text-[10px] font-bold bg-purple-50 text-purple-700 border border-purple-200">
                                                                        Sub-Assembly
                                                                    </span>
                                                                ) : (
                                                                    <span className="px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                                        Raw Material
                                                                    </span>
                                                                )}
                                                                <span className="font-bold text-slate-900">{c.name}</span>
                                                            </div>
                                                            <div className="font-mono text-[10px] text-slate-400 pl-1">{c.sku}</div>
                                                        </td>
                                                        <td className="px-6 py-3 text-right font-mono">{c.net_quantity} {c.unit}</td>
                                                        <td className="px-6 py-3 text-right font-mono text-slate-500">{c.scrap_percentage}%</td>
                                                        <td className="px-6 py-3 text-right font-mono font-bold text-slate-900">
                                                            {c.gross_quantity} {c.unit}
                                                        </td>
                                                        <td className="px-6 py-3 text-right font-mono">
                                                            <span className={`font-bold ${isShort ? 'text-rose-600' : 'text-emerald-700'}`}>
                                                                {matchAvail?.available ?? '—'} {c.unit}
                                                            </span>
                                                        </td>
                                                        <td className="px-6 py-3 text-right font-mono font-semibold text-slate-800">
                                                            {formatInr(c.total_cost)}
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
};

