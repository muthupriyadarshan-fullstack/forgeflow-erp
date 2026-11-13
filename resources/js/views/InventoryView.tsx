import React, { useEffect, useState } from 'react';
import { api } from '../services/api';
import { InventoryBalance, Product, StockMovement, Warehouse } from '../types/erp';
import {
    AlertTriangle,
    Archive,
    ArrowLeftRight,
    ArrowRight,
    CheckCircle2,
    Clock,
    FileSpreadsheet,
    Filter,
    Layers,
    Plus,
    RefreshCw,
    Search,
    SlidersHorizontal,
    X,
} from 'lucide-react';

export const InventoryView: React.FC = () => {
    const [balances, setBalances] = useState<InventoryBalance[]>([]);
    const [movements, setMovements] = useState<StockMovement[]>([]);
    const [warehouses, setWarehouses] = useState<Warehouse[]>([]);
    const [products, setProducts] = useState<Product[]>([]);
    const [loading, setLoading] = useState(true);
    const [activeTab, setActiveTab] = useState<'balances' | 'ledger'>('balances');

    // Filters
    const [selectedWarehouseId, setSelectedWarehouseId] = useState<string>('');
    const [selectedMovementType, setSelectedMovementType] = useState<string>('');
    const [searchQuery, setSearchQuery] = useState('');

    // Transfer Modal
    const [transferModalOpen, setTransferModalOpen] = useState(false);
    const [transferData, setTransferData] = useState({
        product_id: 0,
        source_warehouse_id: 0,
        dest_warehouse_id: 0,
        quantity: 10,
        unit_id: 1,
        reason: 'Inter-plant transfer to production staging',
    });

    // Adjustment Modal
    const [adjustModalOpen, setAdjustModalOpen] = useState(false);
    const [adjustData, setAdjustData] = useState({
        product_id: 0,
        warehouse_id: 0,
        quantity: 5,
        unit_id: 1,
        reason: 'Physical cycle count variance reconciliation',
    });

    const [actionLoading, setActionLoading] = useState(false);
    const [feedbackMessage, setFeedbackMessage] = useState<string | null>(null);

    const loadData = async () => {
        setLoading(true);
        try {
            const params: Record<string, string> = {};
            if (selectedWarehouseId) params.warehouse_id = selectedWarehouseId;
            if (searchQuery) params.search = searchQuery;

            const movParams: Record<string, string> = {};
            if (selectedMovementType) movParams.movement_type = selectedMovementType;

            const [bRes, mRes, wRes, pRes] = await Promise.all([
                api.getInventoryBalances(params),
                api.getStockMovements(movParams),
                api.getWarehouses(),
                api.getProducts(),
            ]);

            setBalances(bRes.data || []);
            setMovements(mRes.data || []);
            setWarehouses(wRes || []);
            setProducts(pRes.data || []);

            if (pRes.data && pRes.data.length > 0 && transferData.product_id === 0) {
                const first = pRes.data[0];
                setTransferData((prev) => ({
                    ...prev,
                    product_id: first.id,
                    unit_id: first.unit?.id || 1,
                }));
                setAdjustData((prev) => ({
                    ...prev,
                    product_id: first.id,
                    unit_id: first.unit?.id || 1,
                }));
            }
            if (wRes && wRes.length >= 2 && transferData.source_warehouse_id === 0) {
                setTransferData((prev) => ({
                    ...prev,
                    source_warehouse_id: wRes[0].id,
                    dest_warehouse_id: wRes[1].id,
                }));
                setAdjustData((prev) => ({
                    ...prev,
                    warehouse_id: wRes[0].id,
                }));
            }
        } catch (e) {
            console.error('Failed to load inventory data', e);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadData();
    }, [selectedWarehouseId, selectedMovementType]);

    const handleTransferSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setActionLoading(true);
        try {
            const res = await api.transferStock(transferData);
            setFeedbackMessage(res.message || 'Stock transfer recorded in immutable ledger.');
            setTransferModalOpen(false);
            loadData();
            setTimeout(() => setFeedbackMessage(null), 5000);
        } catch (err: any) {
            alert('Transfer failed: ' + err.message);
        } finally {
            setActionLoading(false);
        }
    };

    const handleAdjustSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setActionLoading(true);
        try {
            const res = await api.adjustStock(adjustData);
            setFeedbackMessage(res.message || 'Stock adjustment recorded in immutable ledger.');
            setAdjustModalOpen(false);
            loadData();
            setTimeout(() => setFeedbackMessage(null), 5000);
        } catch (err: any) {
            alert('Adjustment failed: ' + err.message);
        } finally {
            setActionLoading(false);
        }
    };

    const formatInr = (val: number) => {
        return '₹' + Number(val || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
    };

    return (
        <div className="space-y-6">
            {/* View Header with Action Buttons */}
            <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 className="text-lg font-bold text-slate-900 tracking-tight">Inventory Balances & Stock Ledger</h2>
                    <p className="text-xs text-slate-500">
                        Multi-warehouse stock tracking, moving weighted average valuation & immutable audit movements
                    </p>
                </div>

                <div className="flex items-center space-x-3">
                    <button
                        onClick={() => setTransferModalOpen(true)}
                        className="flex items-center space-x-1.5 px-3 py-2 bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs rounded-xl shadow-xs transition"
                    >
                        <ArrowLeftRight className="w-3.5 h-3.5" />
                        <span>Transfer Stock</span>
                    </button>

                    <button
                        onClick={() => setAdjustModalOpen(true)}
                        className="flex items-center space-x-1.5 px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs rounded-xl shadow-xs transition"
                    >
                        <SlidersHorizontal className="w-3.5 h-3.5" />
                        <span>Adjust Inventory</span>
                    </button>
                </div>
            </div>

            {feedbackMessage && (
                <div className="p-4 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center space-x-3 text-xs font-bold text-emerald-800">
                    <CheckCircle2 className="w-5 h-5 text-emerald-600 shrink-0" />
                    <span>{feedbackMessage}</span>
                </div>
            )}

            {/* Warehouse Overview Summary Cards */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                {warehouses.map((wh) => (
                    <div
                        key={wh.id}
                        onClick={() => setSelectedWarehouseId(selectedWarehouseId === String(wh.id) ? '' : String(wh.id))}
                        className={`p-4 rounded-2xl border cursor-pointer transition ${
                            selectedWarehouseId === String(wh.id)
                                ? 'bg-blue-50/70 border-blue-400 ring-2 ring-blue-500/20'
                                : 'bg-white border-slate-200 hover:bg-slate-50'
                        }`}
                    >
                        <div className="flex items-center justify-between">
                            <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400 font-mono">
                                {wh.code}
                            </span>
                            <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700 capitalize">
                                {wh.type.replace('_', ' ')}
                            </span>
                        </div>
                        <h4 className="font-bold text-slate-900 text-sm mt-1">{wh.name}</h4>
                        <p className="text-xs text-slate-500 mt-0.5">{wh.city || 'Pune, MH'}</p>

                        <div className="mt-3 pt-3 border-t border-slate-100/80 flex items-center justify-between text-xs text-slate-600">
                            <span>Locations: {wh.locations?.length || 0} bins</span>
                            <span className="font-semibold text-blue-600">
                                {selectedWarehouseId === String(wh.id) ? 'Filtering active' : 'Click to filter'}
                            </span>
                        </div>
                    </div>
                ))}
            </div>

            {/* Tabs for Balances vs Stock Ledger */}
            <div className="flex items-center space-x-2 border-b border-slate-200">
                <button
                    onClick={() => setActiveTab('balances')}
                    className={`px-4 py-2 text-xs font-bold border-b-2 transition ${
                        activeTab === 'balances'
                            ? 'border-blue-600 text-blue-600'
                            : 'border-transparent text-slate-500 hover:text-slate-900'
                    }`}
                >
                    Current Stock Balances ({balances.length})
                </button>
                <button
                    onClick={() => setActiveTab('ledger')}
                    className={`px-4 py-2 text-xs font-bold border-b-2 transition ${
                        activeTab === 'ledger'
                            ? 'border-blue-600 text-blue-600'
                            : 'border-transparent text-slate-500 hover:text-slate-900'
                    }`}
                >
                    Immutable Stock Movement Ledger ({movements.length})
                </button>
            </div>

            {/* TAB 1: CURRENT STOCK BALANCES */}
            {activeTab === 'balances' && (
                <div className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                    <div className="p-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
                        <div className="relative w-full max-w-sm">
                            <Search className="w-4 h-4 absolute left-3 top-2.5 text-slate-400" />
                            <input
                                type="text"
                                placeholder="Filter balances by item name, SKU, or batch..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && loadData()}
                                className="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>

                        {selectedWarehouseId && (
                            <button
                                onClick={() => setSelectedWarehouseId('')}
                                className="text-xs font-semibold text-rose-600 hover:text-rose-800 flex items-center space-x-1"
                            >
                                <X className="w-3.5 h-3.5" />
                                <span>Clear Warehouse Filter</span>
                            </button>
                        )}
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-50 text-slate-500 uppercase font-semibold text-[10px] tracking-wider border-b border-slate-100">
                                <tr>
                                    <th className="px-6 py-3.5">Product & SKU</th>
                                    <th className="px-6 py-3.5">Warehouse & Location</th>
                                    <th className="px-6 py-3.5">Batch / Lot</th>
                                    <th className="px-6 py-3.5 text-right">On Hand</th>
                                    <th className="px-6 py-3.5 text-right">Reserved</th>
                                    <th className="px-6 py-3.5 text-right">Available</th>
                                    <th className="px-6 py-3.5 text-right">Weighted Avg Cost</th>
                                    <th className="px-6 py-3.5 text-right">Total Valuation</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {balances.map((b) => {
                                    const totalVal = Number(b.quantity_on_hand) * Number(b.average_cost_unit);
                                    return (
                                        <tr key={b.id} className="hover:bg-slate-50/70 transition">
                                            <td className="px-6 py-3.5">
                                                <div className="font-bold text-slate-900">{b.product?.name}</div>
                                                <div className="text-[10px] font-mono text-slate-500">{b.product?.sku}</div>
                                            </td>
                                            <td className="px-6 py-3.5">
                                                <div className="font-semibold text-slate-800">{b.warehouse?.name}</div>
                                                <div className="text-[10px] text-slate-500 font-mono">
                                                    {b.location?.name || 'Bulk Floor Yard'}
                                                </div>
                                            </td>
                                            <td className="px-6 py-3.5 font-mono text-slate-600">{b.batch_number || 'Standard'}</td>
                                            <td className="px-6 py-3.5 text-right font-mono font-bold text-slate-900">
                                                {b.quantity_on_hand} {b.product?.unit?.symbol}
                                            </td>
                                            <td className="px-6 py-3.5 text-right font-mono text-amber-700 font-semibold">
                                                {b.quantity_reserved} {b.product?.unit?.symbol}
                                            </td>
                                            <td className="px-6 py-3.5 text-right font-mono text-emerald-700 font-bold">
                                                {b.quantity_available} {b.product?.unit?.symbol}
                                            </td>
                                            <td className="px-6 py-3.5 text-right font-mono text-slate-700">
                                                {formatInr(b.average_cost_unit)}
                                            </td>
                                            <td className="px-6 py-3.5 text-right font-mono font-bold text-slate-900">
                                                {formatInr(totalVal)}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {/* TAB 2: IMMUTABLE STOCK LEDGER */}
            {activeTab === 'ledger' && (
                <div className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                    <div className="p-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3 bg-slate-50/60">
                        <div className="flex items-center space-x-2">
                            <span className="text-xs font-bold text-slate-700">Filter Movement:</span>
                            <select
                                value={selectedMovementType}
                                onChange={(e) => setSelectedMovementType(e.target.value)}
                                className="px-2.5 py-1 text-xs border border-slate-200 rounded-lg bg-white"
                            >
                                <option value="">All Movement Types</option>
                                <option value="opening_stock">Opening Stock</option>
                                <option value="purchase_receipt">Purchase Receipt (GRN)</option>
                                <option value="production_consumption">Production Consumption</option>
                                <option value="production_output">Production Output (FG)</option>
                                <option value="stock_transfer">Stock Transfer</option>
                                <option value="stock_adjustment">Stock Adjustment</option>
                                <option value="damaged_goods">Damaged / Scrap</option>
                            </select>
                        </div>

                        <div className="text-xs text-slate-500 font-medium">
                            <span className="font-bold text-slate-800">{movements.length}</span> audit transactions recorded
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-50 text-slate-500 uppercase font-semibold text-[10px] tracking-wider border-b border-slate-100">
                                <tr>
                                    <th className="px-6 py-3.5">Movement #</th>
                                    <th className="px-6 py-3.5">Timestamp</th>
                                    <th className="px-6 py-3.5">Type</th>
                                    <th className="px-6 py-3.5">Product & SKU</th>
                                    <th className="px-6 py-3.5">Source &rarr; Destination</th>
                                    <th className="px-6 py-3.5 text-right">Quantity</th>
                                    <th className="px-6 py-3.5 text-right">Unit / Total Cost</th>
                                    <th className="px-6 py-3.5">Reason / Reference</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 font-mono text-[11px]">
                                {movements.map((m) => (
                                    <tr key={m.id} className="hover:bg-slate-50/70 transition font-sans">
                                        <td className="px-6 py-3.5 font-mono font-bold text-slate-900">{m.movement_number}</td>
                                        <td className="px-6 py-3.5 text-slate-500 text-[11px] whitespace-nowrap">
                                            {m.created_at ? new Date(m.created_at).toLocaleDateString('en-IN') : '—'}
                                        </td>
                                        <td className="px-6 py-3.5">
                                            <span className="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-800 border border-slate-200">
                                                {m.movement_type.replace('_', ' ')}
                                            </span>
                                        </td>
                                        <td className="px-6 py-3.5">
                                            <div className="font-bold text-slate-900 font-sans text-xs">{m.product?.name}</div>
                                            <div className="text-[10px] font-mono text-slate-400">{m.product?.sku}</div>
                                        </td>
                                        <td className="px-6 py-3.5 text-xs text-slate-600">
                                            {m.source_warehouse?.name || 'External'} &rarr; {m.dest_warehouse?.name || 'Consumed'}
                                        </td>
                                        <td className="px-6 py-3.5 text-right font-mono font-bold text-slate-900 text-xs">
                                            {m.quantity} {m.product?.unit?.symbol}
                                        </td>
                                        <td className="px-6 py-3.5 text-right font-mono text-xs">
                                            <div>{formatInr(m.total_cost)}</div>
                                            <div className="text-[10px] text-slate-400 font-mono">@{formatInr(m.unit_cost)}</div>
                                        </td>
                                        <td className="px-6 py-3.5 text-xs text-slate-500 max-w-xs truncate" title={m.reason || ''}>
                                            {m.reason || '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {/* MODAL 1: STOCK TRANSFER MODAL */}
            {transferModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs">
                    <div className="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
                        <div className="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                            <div>
                                <h3 className="font-bold text-slate-900 text-base">Internal Stock Transfer</h3>
                                <p className="text-xs text-slate-500">Atomic relocation with double-entry ledger guarantee</p>
                            </div>
                            <button onClick={() => setTransferModalOpen(false)} className="text-slate-400 hover:text-slate-600">
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleTransferSubmit} className="space-y-4 text-xs">
                            <div>
                                <label className="font-semibold text-slate-700 block mb-1">Select Item to Transfer</label>
                                <select
                                    value={transferData.product_id}
                                    onChange={(e) => {
                                        const p = products.find((prod) => prod.id === Number(e.target.value));
                                        setTransferData({
                                            ...transferData,
                                            product_id: Number(e.target.value),
                                            unit_id: p?.unit?.id || 1,
                                        });
                                    }}
                                    className="w-full px-3 py-2 border border-slate-200 rounded-xl"
                                >
                                    {products.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name} ({p.sku})
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="font-semibold text-slate-700 block mb-1">Source Warehouse</label>
                                    <select
                                        value={transferData.source_warehouse_id}
                                        onChange={(e) => setTransferData({ ...transferData, source_warehouse_id: Number(e.target.value) })}
                                        className="w-full px-3 py-2 border border-slate-200 rounded-xl"
                                    >
                                        {warehouses.map((w) => (
                                            <option key={w.id} value={w.id}>{w.name}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="font-semibold text-slate-700 block mb-1">Destination Warehouse</label>
                                    <select
                                        value={transferData.dest_warehouse_id}
                                        onChange={(e) => setTransferData({ ...transferData, dest_warehouse_id: Number(e.target.value) })}
                                        className="w-full px-3 py-2 border border-slate-200 rounded-xl"
                                    >
                                        {warehouses.map((w) => (
                                            <option key={w.id} value={w.id}>{w.name}</option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label className="font-semibold text-slate-700 block mb-1">Quantity to Relocate</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    value={transferData.quantity}
                                    onChange={(e) => setTransferData({ ...transferData, quantity: Number(e.target.value) })}
                                    className="w-full px-3 py-2 border border-slate-200 rounded-xl font-bold"
                                    required
                                />
                            </div>

                            <div>
                                <label className="font-semibold text-slate-700 block mb-1">Transfer Reason / Audit Remark</label>
                                <input
                                    type="text"
                                    value={transferData.reason}
                                    onChange={(e) => setTransferData({ ...transferData, reason: e.target.value })}
                                    className="w-full px-3 py-2 border border-slate-200 rounded-xl"
                                    required
                                />
                            </div>

                            <div className="pt-3 border-t border-slate-100 flex items-center justify-end space-x-2">
                                <button
                                    type="button"
                                    onClick={() => setTransferModalOpen(false)}
                                    className="px-4 py-2 border border-slate-200 text-slate-600 font-semibold rounded-xl hover:bg-slate-50 transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={actionLoading}
                                    className="px-4 py-2 bg-slate-900 text-white font-semibold rounded-xl hover:bg-slate-800 transition"
                                >
                                    {actionLoading ? 'Recording Movement...' : 'Execute Stock Transfer'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* MODAL 2: STOCK ADJUSTMENT MODAL */}
            {adjustModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs">
                    <div className="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
                        <div className="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                            <div>
                                <h3 className="font-bold text-slate-900 text-base">Physical Stock Adjustment</h3>
                                <p className="text-xs text-slate-500">Record cycle count variance with non-repudiation audit reason</p>
                            </div>
                            <button onClick={() => setAdjustModalOpen(false)} className="text-slate-400 hover:text-slate-600">
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleAdjustSubmit} className="space-y-4 text-xs">
                            <div>
                                <label className="font-semibold text-slate-700 block mb-1">Product</label>
                                <select
                                    value={adjustData.product_id}
                                    onChange={(e) => {
                                        const p = products.find((prod) => prod.id === Number(e.target.value));
                                        setAdjustData({
                                            ...adjustData,
                                            product_id: Number(e.target.value),
                                            unit_id: p?.unit?.id || 1,
                                        });
                                    }}
                                    className="w-full px-3 py-2 border border-slate-200 rounded-xl"
                                >
                                    {products.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name} ({p.sku})
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="font-semibold text-slate-700 block mb-1">Target Warehouse</label>
                                <select
                                    value={adjustData.warehouse_id}
                                    onChange={(e) => setAdjustData({ ...adjustData, warehouse_id: Number(e.target.value) })}
                                    className="w-full px-3 py-2 border border-slate-200 rounded-xl"
                                >
                                    {warehouses.map((w) => (
                                        <option key={w.id} value={w.id}>{w.name}</option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="font-semibold text-slate-700 block mb-1">
                                    Adjustment Quantity (+ to Add, - to Deduct)
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    value={adjustData.quantity}
                                    onChange={(e) => setAdjustData({ ...adjustData, quantity: Number(e.target.value) })}
                                    className="w-full px-3 py-2 border border-slate-200 rounded-xl font-mono font-bold"
                                    required
                                />
                                <p className="text-[11px] text-slate-400 mt-1">
                                    Negative adjustments will fail if current available stock is insufficient.
                                </p>
                            </div>

                            <div>
                                <label className="font-semibold text-slate-700 block mb-1">Mandatory Audit Reason</label>
                                <input
                                    type="text"
                                    value={adjustData.reason}
                                    onChange={(e) => setAdjustData({ ...adjustData, reason: e.target.value })}
                                    className="w-full px-3 py-2 border border-slate-200 rounded-xl"
                                    placeholder="e.g. Month-end physical stock audit reconciliation"
                                    required
                                />
                            </div>

                            <div className="pt-3 border-t border-slate-100 flex items-center justify-end space-x-2">
                                <button
                                    type="button"
                                    onClick={() => setAdjustModalOpen(false)}
                                    className="px-4 py-2 border border-slate-200 text-slate-600 font-semibold rounded-xl hover:bg-slate-50 transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={actionLoading}
                                    className="px-4 py-2 bg-indigo-600 text-white font-semibold rounded-xl hover:bg-indigo-700 transition"
                                >
                                    {actionLoading ? 'Recording Adjustment...' : 'Record Adjustment'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
};

