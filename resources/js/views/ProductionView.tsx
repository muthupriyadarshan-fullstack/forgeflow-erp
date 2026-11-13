import React, { useEffect, useState } from 'react';
import { api } from '../services/api';
import { BillOfMaterial, Product, ProductionOrder, Warehouse } from '../types/erp';
import {
    Activity,
    AlertCircle,
    ArrowRight,
    Boxes,
    CheckCircle2,
    Clock,
    Cog,
    Factory,
    Layers,
    Play,
    Plus,
    RefreshCw,
    ShieldCheck,
    Workflow,
    Wrench,
    X,
} from 'lucide-react';

export const ProductionView: React.FC = () => {
    const [orders, setOrders] = useState<ProductionOrder[]>([]);
    const [products, setProducts] = useState<Product[]>([]);
    const [boms, setBoms] = useState<BillOfMaterial[]>([]);
    const [warehouses, setWarehouses] = useState<Warehouse[]>([]);
    const [machines, setMachines] = useState<any[]>([]);
    const [employees, setEmployees] = useState<any[]>([]);
    const [loading, setLoading] = useState(true);
    const [actionLoading, setActionLoading] = useState(false);
    const [feedback, setFeedback] = useState<string | null>(null);

    // Create Order Modal
    const [createModalOpen, setCreateModalOpen] = useState(false);
    const [newOrder, setNewOrder] = useState({
        product_id: 0,
        bill_of_materials_id: 0,
        target_warehouse_id: 0,
        planned_quantity: 2,
        due_date: new Date(Date.now() + 7 * 86400000).toISOString().split('T')[0],
        priority: 'high',
        notes: 'High-precision manufacturing order',
    });

    // Complete Order Modal
    const [completeModalOpen, setCompleteModalOpen] = useState(false);
    const [activeOrder, setActiveOrder] = useState<ProductionOrder | null>(null);
    const [completionForm, setCompletionForm] = useState({
        quantity_produced: 2,
        quantity_rejected: 0,
        quantity_scrapped: 0,
        labour_cost: 4500,
        machine_cost: 6200,
        batch_number: 'BAT-GBX-PROD-99',
    });

    const loadData = async () => {
        setLoading(true);
        try {
            const [oRes, pRes, bRes, wRes, mRes, eRes] = await Promise.all([
                api.getProductionOrders(),
                api.getProducts(),
                api.getBOMs(),
                api.getWarehouses(),
                api.getMachines(),
                api.getEmployees(),
            ]);

            setOrders(oRes.data || []);
            setProducts(pRes.data || []);
            setBoms(bRes.data || []);
            setWarehouses(wRes || []);
            setMachines(mRes || []);
            setEmployees(eRes || []);

            const finishedProducts = (pRes.data || []).filter((p) => p.type === 'finished_good' || p.type === 'work_in_progress');
            if (finishedProducts.length > 0 && newOrder.product_id === 0) {
                const firstP = finishedProducts[0];
                const matchedBom = (bRes.data || []).find((b) => b.product_id === firstP.id);
                setNewOrder((prev) => ({
                    ...prev,
                    product_id: firstP.id,
                    bill_of_materials_id: matchedBom?.id || (bRes.data?.[0]?.id ?? 0),
                    target_warehouse_id: wRes?.[2]?.id || wRes?.[0]?.id || 1,
                }));
            }
        } catch (e) {
            console.error('Failed to load production data', e);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadData();
    }, []);

    const handleCreateOrder = async (e: React.FormEvent) => {
        e.preventDefault();
        setActionLoading(true);
        try {
            const res = await api.createProductionOrder ? await api.request('/production/orders', {
                method: 'POST',
                body: JSON.stringify(newOrder),
            }) : null;
            setFeedback('Production work order created with sequential stages.');
            setCreateModalOpen(false);
            loadData();
            setTimeout(() => setFeedback(null), 5000);
        } catch (e: any) {
            alert('Order creation failed: ' + e.message);
        } finally {
            setActionLoading(false);
        }
    };

    const handleReserve = async (orderId: number) => {
        setActionLoading(true);
        try {
            const res = await api.reserveMaterials(orderId);
            setFeedback(res.message || 'Materials reserved successfully.');
            loadData();
            setTimeout(() => setFeedback(null), 5000);
        } catch (e: any) {
            alert('Reservation failed: ' + e.message);
        } finally {
            setActionLoading(false);
        }
    };

    const handleIssue = async (orderId: number) => {
        setActionLoading(true);
        try {
            const res = await api.issueMaterials(orderId);
            setFeedback(res.message || 'Materials issued to shop floor; stock deducted.');
            loadData();
            setTimeout(() => setFeedback(null), 5000);
        } catch (e: any) {
            alert('Material issue failed: ' + e.message);
        } finally {
            setActionLoading(false);
        }
    };

    const openCompleteModal = (order: ProductionOrder) => {
        setActiveOrder(order);
        setCompletionForm({
            quantity_produced: Number(order.planned_quantity),
            quantity_rejected: 0,
            quantity_scrapped: 0,
            labour_cost: 3500,
            machine_cost: 4800,
            batch_number: `BAT-${order.order_number.replace('WO-', '')}-${Math.floor(10 + Math.random() * 90)}`,
        });
        setCompleteModalOpen(true);
    };

    const handleCompleteSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!activeOrder) return;
        setActionLoading(true);
        try {
            const res = await api.completeProduction(activeOrder.id, completionForm);
            setFeedback(res.message || 'Production order completed; finished goods added to inventory.');
            setCompleteModalOpen(false);
            loadData();
            setTimeout(() => setFeedback(null), 5000);
        } catch (e: any) {
            alert('Completion failed: ' + e.message);
        } finally {
            setActionLoading(false);
        }
    };

    const formatInr = (val: number) => {
        return '₹' + Number(val || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
    };

    return (
        <div className="space-y-6">
            {/* View Header */}
            <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 className="text-lg font-bold text-slate-900 tracking-tight">Shop Floor & Production Work Orders</h2>
                    <p className="text-xs text-slate-500">
                        Multi-stage manufacturing, raw material reservations, shop floor issues and finished output
                    </p>
                </div>

                <button
                    onClick={() => setCreateModalOpen(true)}
                    className="flex items-center space-x-1.5 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs rounded-xl shadow-xs transition"
                >
                    <Plus className="w-4 h-4" />
                    <span>Create Production Order</span>
                </button>
            </div>

            {feedback && (
                <div className="p-4 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center space-x-3 text-xs font-bold text-emerald-800">
                    <CheckCircle2 className="w-5 h-5 text-emerald-600 shrink-0" />
                    <span>{feedback}</span>
                </div>
            )}

            {/* Production Machine Bay Status Bar */}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                {machines.map((m) => (
                    <div key={m.id} className="bg-white p-3 rounded-xl border border-slate-200 shadow-2xs">
                        <div className="flex items-center justify-between text-[11px] mb-1">
                            <span className="font-mono font-bold text-slate-500">{m.code}</span>
                            <span className="flex items-center text-emerald-700 font-bold">
                                <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1 animate-pulse"></span>
                                {m.status}
                            </span>
                        </div>
                        <div className="font-bold text-slate-900 text-xs truncate" title={m.name}>
                            {m.name}
                        </div>
                        <div className="text-[10px] text-slate-500 mt-1">
                            Rate: {formatInr(m.hourly_rate)}/hr • Cap: {m.capacity_per_hour}/hr
                        </div>
                    </div>
                ))}
            </div>

            {/* Orders Table */}
            <div className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div>
                        <h3 className="font-bold text-slate-900 text-sm">Active & Historical Work Orders</h3>
                        <p className="text-xs text-slate-500">Track progress through Reservation &rarr; Issue &rarr; Completion</p>
                    </div>
                    <span className="text-xs font-bold text-slate-700 bg-slate-100 px-2.5 py-1 rounded-lg">
                        {orders.length} Work Orders
                    </span>
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs">
                        <thead className="bg-slate-50 text-slate-500 uppercase font-semibold text-[10px] tracking-wider border-b border-slate-100">
                            <tr>
                                <th className="px-6 py-3.5">Order Number</th>
                                <th className="px-6 py-3.5">Assembly / Product</th>
                                <th className="px-6 py-3.5 text-right">Planned Qty</th>
                                <th className="px-6 py-3.5 text-right">Produced Qty</th>
                                <th className="px-6 py-3.5 text-right">Total Cost</th>
                                <th className="px-6 py-3.5 text-center">Status</th>
                                <th className="px-6 py-3.5 text-right">Workflow Execution</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {orders.map((order) => {
                                const statusColors: Record<string, string> = {
                                    planned: 'bg-slate-100 text-slate-700 border-slate-200',
                                    material_reserved: 'bg-purple-50 text-purple-800 border-purple-200',
                                    in_progress: 'bg-amber-50 text-amber-800 border-amber-200',
                                    completed: 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                    cancelled: 'bg-rose-50 text-rose-800 border-rose-200',
                                };

                                return (
                                    <tr key={order.id} className="hover:bg-slate-50/70 transition">
                                        <td className="px-6 py-3.5 font-mono font-bold text-slate-900">{order.order_number}</td>
                                        <td className="px-6 py-3.5">
                                            <div className="font-bold text-slate-900">{order.product?.name}</div>
                                            <div className="text-[10px] font-mono text-slate-400">
                                                Target WH: {order.target_warehouse?.name || 'Talegaon FG'}
                                            </div>
                                        </td>
                                        <td className="px-6 py-3.5 text-right font-mono font-bold text-slate-900">
                                            {order.planned_quantity} {order.product?.unit?.symbol}
                                        </td>
                                        <td className="px-6 py-3.5 text-right font-mono text-emerald-700 font-bold">
                                            {order.produced_quantity} {order.product?.unit?.symbol}
                                        </td>
                                        <td className="px-6 py-3.5 text-right font-mono font-bold text-slate-900">
                                            {formatInr(order.actual_cost > 0 ? order.actual_cost : order.estimated_cost)}
                                        </td>
                                        <td className="px-6 py-3.5 text-center">
                                            <span
                                                className={`px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border ${
                                                    statusColors[order.status] || 'bg-slate-100'
                                                }`}
                                            >
                                                {order.status.replace('_', ' ')}
                                            </span>
                                        </td>
                                        <td className="px-6 py-3.5 text-right">
                                            <div className="flex items-center justify-end space-x-2">
                                                {/* Step 1: Reserve Materials */}
                                                {order.status === 'planned' && (
                                                    <button
                                                        onClick={() => handleReserve(order.id)}
                                                        disabled={actionLoading}
                                                        className="px-2.5 py-1 bg-purple-600 hover:bg-purple-700 text-white font-semibold rounded-lg text-[11px] transition shadow-xs"
                                                    >
                                                        Reserve Stock
                                                    </button>
                                                )}

                                                {/* Step 2: Issue Materials */}
                                                {order.status === 'material_reserved' && (
                                                    <button
                                                        onClick={() => handleIssue(order.id)}
                                                        disabled={actionLoading}
                                                        className="px-2.5 py-1 bg-amber-600 hover:bg-amber-700 text-white font-semibold rounded-lg text-[11px] transition shadow-xs"
                                                    >
                                                        Issue to Floor
                                                    </button>
                                                )}

                                                {/* Step 3: Complete Production */}
                                                {order.status === 'in_progress' && (
                                                    <button
                                                        onClick={() => openCompleteModal(order)}
                                                        className="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg text-[11px] transition shadow-xs flex items-center space-x-1"
                                                    >
                                                        <CheckCircle2 className="w-3.5 h-3.5" />
                                                        <span>Complete Order</span>
                                                    </button>
                                                )}

                                                {order.status === 'completed' && (
                                                    <span className="text-emerald-700 font-semibold text-[11px] flex items-center space-x-1">
                                                        <CheckCircle2 className="w-3.5 h-3.5" />
                                                        <span>Output Received</span>
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* CREATE ORDER MODAL */}
            {createModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs">
                    <div className="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
                        <div className="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                            <div>
                                <h3 className="font-bold text-slate-900 text-base">Schedule Production Work Order</h3>
                                <p className="text-xs text-slate-500">Plan manufacturing with BOM explosion and sequential shop floor stages</p>
                            </div>
                            <button onClick={() => setCreateModalOpen(false)} className="text-slate-400 hover:text-slate-600">
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleCreateOrder} className="space-y-4 text-xs">
                            <div>
                                <label className="font-semibold text-slate-700 block mb-1">Target Finished Assembly</label>
                                <select
                                    value={newOrder.product_id}
                                    onChange={(e) => {
                                        const pId = Number(e.target.value);
                                        const matchedBom = boms.find((b) => b.product_id === pId);
                                        setNewOrder({
                                            ...newOrder,
                                            product_id: pId,
                                            bill_of_materials_id: matchedBom?.id || boms[0]?.id || 1,
                                        });
                                    }}
                                    className="w-full px-3 py-2 border border-slate-200 rounded-xl"
                                >
                                    {products
                                        .filter((p) => p.type === 'finished_good' || p.type === 'work_in_progress')
                                        .map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name} ({p.sku})
                                            </option>
                                        ))}
                                </select>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="font-semibold text-slate-700 block mb-1">Planned Quantity</label>
                                    <input
                                        type="number"
                                        min="1"
                                        value={newOrder.planned_quantity}
                                        onChange={(e) => setNewOrder({ ...newOrder, planned_quantity: Number(e.target.value) })}
                                        className="w-full px-3 py-2 border border-slate-200 rounded-xl font-bold font-mono"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="font-semibold text-slate-700 block mb-1">Due Date</label>
                                    <input
                                        type="date"
                                        value={newOrder.due_date}
                                        onChange={(e) => setNewOrder({ ...newOrder, due_date: e.target.value })}
                                        className="w-full px-3 py-2 border border-slate-200 rounded-xl"
                                        required
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="font-semibold text-slate-700 block mb-1">Target Finished Goods Warehouse</label>
                                <select
                                    value={newOrder.target_warehouse_id}
                                    onChange={(e) => setNewOrder({ ...newOrder, target_warehouse_id: Number(e.target.value) })}
                                    className="w-full px-3 py-2 border border-slate-200 rounded-xl"
                                >
                                    {warehouses.map((w) => (
                                        <option key={w.id} value={w.id}>{w.name}</option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="font-semibold text-slate-700 block mb-1">Order Notes / Customer Reference</label>
                                <input
                                    type="text"
                                    value={newOrder.notes}
                                    onChange={(e) => setNewOrder({ ...newOrder, notes: e.target.value })}
                                    className="w-full px-3 py-2 border border-slate-200 rounded-xl"
                                />
                            </div>

                            <div className="pt-3 border-t border-slate-100 flex items-center justify-end space-x-2">
                                <button
                                    type="button"
                                    onClick={() => setCreateModalOpen(false)}
                                    className="px-4 py-2 border border-slate-200 text-slate-600 font-semibold rounded-xl hover:bg-slate-50 transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={actionLoading}
                                    className="px-4 py-2 bg-blue-600 text-white font-semibold rounded-xl hover:bg-blue-700 transition"
                                >
                                    {actionLoading ? 'Creating Order...' : 'Generate Work Order'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* COMPLETE ORDER MODAL */}
            {completeModalOpen && activeOrder && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs">
                    <div className="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
                        <div className="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                            <div>
                                <h3 className="font-bold text-slate-900 text-base">Complete Work Order #{activeOrder.order_number}</h3>
                                <p className="text-xs text-slate-500">Record final finished goods, scrap, and roll up total manufacturing cost</p>
                            </div>
                            <button onClick={() => setCompleteModalOpen(false)} className="text-slate-400 hover:text-slate-600">
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleCompleteSubmit} className="space-y-4 text-xs">
                            <div className="grid grid-cols-3 gap-3">
                                <div>
                                    <label className="font-semibold text-slate-700 block mb-1">Produced Qty</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        value={completionForm.quantity_produced}
                                        onChange={(e) => setCompletionForm({ ...completionForm, quantity_produced: Number(e.target.value) })}
                                        className="w-full px-3 py-2 border border-slate-200 rounded-xl font-bold font-mono text-emerald-800"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="font-semibold text-slate-700 block mb-1">Rejected Qty</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={completionForm.quantity_rejected}
                                        onChange={(e) => setCompletionForm({ ...completionForm, quantity_rejected: Number(e.target.value) })}
                                        className="w-full px-3 py-2 border border-slate-200 rounded-xl font-mono text-rose-700"
                                    />
                                </div>
                                <div>
                                    <label className="font-semibold text-slate-700 block mb-1">Scrapped Qty</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={completionForm.quantity_scrapped}
                                        onChange={(e) => setCompletionForm({ ...completionForm, quantity_scrapped: Number(e.target.value) })}
                                        className="w-full px-3 py-2 border border-slate-200 rounded-xl font-mono text-amber-700"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="font-semibold text-slate-700 block mb-1">Actual Labour Cost (₹)</label>
                                    <input
                                        type="number"
                                        min="0"
                                        value={completionForm.labour_cost}
                                        onChange={(e) => setCompletionForm({ ...completionForm, labour_cost: Number(e.target.value) })}
                                        className="w-full px-3 py-2 border border-slate-200 rounded-xl font-mono"
                                    />
                                </div>
                                <div>
                                    <label className="font-semibold text-slate-700 block mb-1">Actual Machine Cost (₹)</label>
                                    <input
                                        type="number"
                                        min="0"
                                        value={completionForm.machine_cost}
                                        onChange={(e) => setCompletionForm({ ...completionForm, machine_cost: Number(e.target.value) })}
                                        className="w-full px-3 py-2 border border-slate-200 rounded-xl font-mono"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="font-semibold text-slate-700 block mb-1">Finished Goods Batch Number</label>
                                <input
                                    type="text"
                                    value={completionForm.batch_number}
                                    onChange={(e) => setCompletionForm({ ...completionForm, batch_number: e.target.value })}
                                    className="w-full px-3 py-2 border border-slate-200 rounded-xl font-mono font-bold"
                                    required
                                />
                            </div>

                            <div className="pt-3 border-t border-slate-100 flex items-center justify-end space-x-2">
                                <button
                                    type="button"
                                    onClick={() => setCompleteModalOpen(false)}
                                    className="px-4 py-2 border border-slate-200 text-slate-600 font-semibold rounded-xl hover:bg-slate-50 transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={actionLoading}
                                    className="px-4 py-2 bg-emerald-600 text-white font-semibold rounded-xl hover:bg-emerald-700 transition"
                                >
                                    {actionLoading ? 'Closing Order...' : 'Complete & Stock Finished Goods'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
};

