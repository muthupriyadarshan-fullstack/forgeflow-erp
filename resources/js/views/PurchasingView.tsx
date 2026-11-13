import React, { useEffect, useState } from 'react';
import { api } from '../services/api';
import { PurchaseOrder, Supplier } from '../types/erp';
import {
    CheckCircle2,
    Clock,
    FileCheck2,
    FileText,
    IndianRupee,
    PackageCheck,
    Plus,
    RefreshCw,
    Search,
    ShieldCheck,
    Truck,
    X,
    XCircle,
} from 'lucide-react';

export const PurchasingView: React.FC = () => {
    const [orders, setOrders] = useState<PurchaseOrder[]>([]);
    const [suppliers, setSuppliers] = useState<Supplier[]>([]);
    const [loading, setLoading] = useState(true);
    const [actionLoading, setActionLoading] = useState(false);
    const [feedback, setFeedback] = useState<string | null>(null);

    // GRN Modal State
    const [grnModalOpen, setGrnModalOpen] = useState(false);
    const [activePo, setActivePo] = useState<PurchaseOrder | null>(null);
    const [grnForm, setGrnForm] = useState({
        delivery_challan_number: 'DC/MH/2026/8891',
        transporter_name: 'VRL Heavy Freight Logistics',
        vehicle_number: 'MH-12-Q-4451',
        notes: 'Quality inspected and accepted at receiving bay',
        items: [] as Array<{
            purchase_order_item_id: number;
            product_name: string;
            quantity_ordered: number;
            quantity_received: number;
            quantity_accepted: number;
            quantity_rejected: number;
            rejection_reason: string;
            batch_number: string;
        }>,
    });

    const loadData = async () => {
        setLoading(true);
        try {
            const [oRes, sRes] = await Promise.all([
                api.getPurchaseOrders(),
                api.getSuppliers(),
            ]);
            setOrders(oRes.data || []);
            setSuppliers(sRes || []);
        } catch (e) {
            console.error('Failed to load purchase orders', e);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadData();
    }, []);

    const handleApprove = async (po: PurchaseOrder, approve: boolean) => {
        setActionLoading(true);
        try {
            const res = await api.approvePurchaseOrder(
                po.id,
                approve,
                approve ? 'Approved under annual procurement plan' : 'Budget rejected'
            );
            setFeedback(res.message);
            loadData();
            setTimeout(() => setFeedback(null), 5000);
        } catch (e: any) {
            alert('Approval action failed: ' + e.message);
        } finally {
            setActionLoading(false);
        }
    };

    const openGrnModal = (po: PurchaseOrder) => {
        setActivePo(po);
        setGrnForm({
            delivery_challan_number: `DC/${po.supplier?.code || 'SUP'}/${Math.floor(10000 + Math.random() * 90000)}`,
            transporter_name: 'TCI Freight Services India',
            vehicle_number: 'MH-14-EA-9912',
            notes: 'Goods verified and certified against metallurgical test certificates',
            items: (po.items || []).map((it) => ({
                purchase_order_item_id: it.id,
                product_name: it.product?.name || 'Item',
                quantity_ordered: Number(it.quantity_ordered),
                quantity_received: Number(it.quantity_ordered),
                quantity_accepted: Number(it.quantity_ordered),
                quantity_rejected: 0,
                rejection_reason: '',
                batch_number: `LOT-${Date.now().toString().slice(-6)}`,
            })),
        });
        setGrnModalOpen(true);
    };

    const handleGrnSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!activePo) return;
        setActionLoading(true);
        try {
            const res = await api.processGRN(activePo.id, {
                delivery_challan_number: grnForm.delivery_challan_number,
                transporter_name: grnForm.transporter_name,
                vehicle_number: grnForm.vehicle_number,
                notes: grnForm.notes,
                items: grnForm.items.map((i) => ({
                    purchase_order_item_id: i.purchase_order_item_id,
                    quantity_received: i.quantity_received,
                    quantity_accepted: i.quantity_accepted,
                    quantity_rejected: i.quantity_rejected,
                    rejection_reason: i.rejection_reason,
                    batch_number: i.batch_number,
                })),
            });
            setFeedback(res.message || 'Goods receipt verified and stock added to inventory.');
            setGrnModalOpen(false);
            loadData();
            setTimeout(() => setFeedback(null), 5000);
        } catch (e: any) {
            alert('GRN processing failed: ' + e.message);
        } finally {
            setActionLoading(false);
        }
    };

    const formatInr = (val: number) => {
        return '₹' + Number(val || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
    };

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 className="text-lg font-bold text-slate-900 tracking-tight">Procurement, PO Approvals & Goods Receipt (GRN)</h2>
                    <p className="text-xs text-slate-500">
                        Indian GST-compliant purchase workflows, two-step approvals and warehouse receipt notes
                    </p>
                </div>

                <div className="flex items-center space-x-2 text-xs">
                    <span className="font-semibold text-slate-600">Registered Suppliers:</span>
                    <span className="font-bold text-slate-900 px-2 py-0.5 bg-slate-100 rounded-md">
                        {suppliers.length} Active Vendors
                    </span>
                </div>
            </div>

            {feedback && (
                <div className="p-4 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center space-x-3 text-xs font-bold text-emerald-800">
                    <CheckCircle2 className="w-5 h-5 text-emerald-600 shrink-0" />
                    <span>{feedback}</span>
                </div>
            )}

            {/* PO List Table */}
            <div className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div>
                        <h3 className="font-bold text-slate-900 text-sm">Purchase Orders</h3>
                        <p className="text-xs text-slate-500">Track PO commitment status, GST breakdown, and GRN receipts</p>
                    </div>
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs">
                        <thead className="bg-slate-50 text-slate-500 uppercase font-semibold text-[10px] tracking-wider border-b border-slate-100">
                            <tr>
                                <th className="px-6 py-3.5">PO Number</th>
                                <th className="px-6 py-3.5">Vendor</th>
                                <th className="px-6 py-3.5">Delivery Warehouse</th>
                                <th className="px-6 py-3.5 text-right">Subtotal</th>
                                <th className="px-6 py-3.5 text-right">18% GST</th>
                                <th className="px-6 py-3.5 text-right">Total (INR)</th>
                                <th className="px-6 py-3.5 text-center">Status</th>
                                <th className="px-6 py-3.5 text-right">Workflow Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {orders.map((po) => {
                                const statusColors: Record<string, string> = {
                                    draft: 'bg-slate-100 text-slate-700',
                                    pending_approval: 'bg-amber-50 text-amber-800 border-amber-200',
                                    approved: 'bg-blue-50 text-blue-800 border-blue-200',
                                    partially_received: 'bg-indigo-50 text-indigo-800 border-indigo-200',
                                    received: 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                    cancelled: 'bg-rose-50 text-rose-800 border-rose-200',
                                };

                                return (
                                    <tr key={po.id} className="hover:bg-slate-50/70 transition">
                                        <td className="px-6 py-3.5 font-mono font-bold text-slate-900">{po.po_number}</td>
                                        <td className="px-6 py-3.5">
                                            <div className="font-bold text-slate-900">{po.supplier?.name}</div>
                                            <div className="text-[10px] font-mono text-slate-400">
                                                GSTIN: {po.supplier?.gstin || '—'}
                                            </div>
                                        </td>
                                        <td className="px-6 py-3.5 text-slate-600">{po.warehouse?.name}</td>
                                        <td className="px-6 py-3.5 text-right font-mono">{formatInr(po.subtotal)}</td>
                                        <td className="px-6 py-3.5 text-right font-mono text-slate-600">{formatInr(po.tax_amount)}</td>
                                        <td className="px-6 py-3.5 text-right font-mono font-bold text-slate-900">
                                            {formatInr(po.total_amount)}
                                        </td>
                                        <td className="px-6 py-3.5 text-center">
                                            <span
                                                className={`px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border ${
                                                    statusColors[po.status] || 'bg-slate-100'
                                                }`}
                                            >
                                                {po.status.replace('_', ' ')}
                                            </span>
                                        </td>
                                        <td className="px-6 py-3.5 text-right">
                                            <div className="flex items-center justify-end space-x-2">
                                                {/* If pending approval, show Approve/Reject */}
                                                {po.approval_status === 'pending' && (
                                                    <>
                                                        <button
                                                            onClick={() => handleApprove(po, true)}
                                                            disabled={actionLoading}
                                                            className="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg text-[11px] transition shadow-xs"
                                                        >
                                                            Approve PO
                                                        </button>
                                                        <button
                                                            onClick={() => handleApprove(po, false)}
                                                            disabled={actionLoading}
                                                            className="px-2.5 py-1 bg-rose-50 hover:bg-rose-100 text-rose-700 font-semibold rounded-lg text-[11px] border border-rose-200 transition"
                                                        >
                                                            Reject
                                                        </button>
                                                    </>
                                                )}

                                                {/* If approved or partially received, show Process GRN */}
                                                {(po.status === 'approved' || po.status === 'partially_received') && (
                                                    <button
                                                        onClick={() => openGrnModal(po)}
                                                        className="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg text-[11px] transition shadow-xs flex items-center space-x-1"
                                                    >
                                                        <PackageCheck className="w-3.5 h-3.5" />
                                                        <span>Receive (GRN)</span>
                                                    </button>
                                                )}

                                                {po.status === 'received' && (
                                                    <span className="text-emerald-700 font-semibold text-[11px] flex items-center space-x-1">
                                                        <CheckCircle2 className="w-3.5 h-3.5" />
                                                        <span>Completed</span>
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

            {/* GRN RECEIVING MODAL */}
            {grnModalOpen && activePo && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs">
                    <div className="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-2xl border border-slate-200 max-h-[90vh] overflow-y-auto">
                        <div className="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                            <div>
                                <h3 className="font-bold text-slate-900 text-base">
                                    Goods Receipt Note (GRN) Verification
                                </h3>
                                <p className="text-xs text-slate-500">
                                    Receive items against PO #{activePo.po_number} from {activePo.supplier?.name}
                                </p>
                            </div>
                            <button onClick={() => setGrnModalOpen(false)} className="text-slate-400 hover:text-slate-600">
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleGrnSubmit} className="space-y-4 text-xs">
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label className="font-semibold text-slate-700 block mb-1">Challan / DC Number</label>
                                    <input
                                        type="text"
                                        value={grnForm.delivery_challan_number}
                                        onChange={(e) => setGrnForm({ ...grnForm, delivery_challan_number: e.target.value })}
                                        className="w-full px-3 py-2 border border-slate-200 rounded-xl font-mono"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="font-semibold text-slate-700 block mb-1">Transporter Name</label>
                                    <input
                                        type="text"
                                        value={grnForm.transporter_name}
                                        onChange={(e) => setGrnForm({ ...grnForm, transporter_name: e.target.value })}
                                        className="w-full px-3 py-2 border border-slate-200 rounded-xl"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="font-semibold text-slate-700 block mb-1">Vehicle Number</label>
                                    <input
                                        type="text"
                                        value={grnForm.vehicle_number}
                                        onChange={(e) => setGrnForm({ ...grnForm, vehicle_number: e.target.value })}
                                        className="w-full px-3 py-2 border border-slate-200 rounded-xl font-mono"
                                        required
                                    />
                                </div>
                            </div>

                            {/* Inspection Items Table */}
                            <div className="border border-slate-200 rounded-xl overflow-hidden">
                                <div className="bg-slate-50 p-2.5 font-bold text-slate-700 border-b border-slate-200 text-[11px] uppercase tracking-wider">
                                    Quality Inspection & Batch Assignment
                                </div>
                                <div className="p-3 space-y-3">
                                    {grnForm.items.map((item, idx) => (
                                        <div key={idx} className="p-3 bg-slate-50/60 rounded-xl border border-slate-200/80 space-y-2">
                                            <div className="font-bold text-slate-900">{item.product_name}</div>
                                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                                <div>
                                                    <label className="text-[10px] text-slate-500 block">Ordered</label>
                                                    <div className="font-mono font-bold text-slate-700">{item.quantity_ordered}</div>
                                                </div>
                                                <div>
                                                    <label className="text-[10px] text-slate-500 block">Accepted Qty</label>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        value={item.quantity_accepted}
                                                        onChange={(e) => {
                                                            const val = Number(e.target.value);
                                                            const newItems = [...grnForm.items];
                                                            newItems[idx].quantity_accepted = val;
                                                            newItems[idx].quantity_rejected = item.quantity_received - val;
                                                            setGrnForm({ ...grnForm, items: newItems });
                                                        }}
                                                        className="w-full px-2 py-1 border border-slate-200 rounded-lg font-bold text-emerald-800 bg-white"
                                                        required
                                                    />
                                                </div>
                                                <div>
                                                    <label className="text-[10px] text-slate-500 block">Rejected Qty</label>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        value={item.quantity_rejected}
                                                        onChange={(e) => {
                                                            const val = Number(e.target.value);
                                                            const newItems = [...grnForm.items];
                                                            newItems[idx].quantity_rejected = val;
                                                            setGrnForm({ ...grnForm, items: newItems });
                                                        }}
                                                        className="w-full px-2 py-1 border border-slate-200 rounded-lg font-mono text-rose-700 bg-white"
                                                    />
                                                </div>
                                                <div>
                                                    <label className="text-[10px] text-slate-500 block">Batch / Lot Assigned</label>
                                                    <input
                                                        type="text"
                                                        value={item.batch_number}
                                                        onChange={(e) => {
                                                            const newItems = [...grnForm.items];
                                                            newItems[idx].batch_number = e.target.value;
                                                            setGrnForm({ ...grnForm, items: newItems });
                                                        }}
                                                        className="w-full px-2 py-1 border border-slate-200 rounded-lg font-mono bg-white"
                                                        required
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div>
                                <label className="font-semibold text-slate-700 block mb-1">Receiving Notes / Quality Remarks</label>
                                <textarea
                                    value={grnForm.notes}
                                    onChange={(e) => setGrnForm({ ...grnForm, notes: e.target.value })}
                                    className="w-full px-3 py-2 border border-slate-200 rounded-xl"
                                    rows={2}
                                />
                            </div>

                            <div className="pt-3 border-t border-slate-100 flex items-center justify-end space-x-2">
                                <button
                                    type="button"
                                    onClick={() => setGrnModalOpen(false)}
                                    className="px-4 py-2 border border-slate-200 text-slate-600 font-semibold rounded-xl hover:bg-slate-50 transition"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={actionLoading}
                                    className="px-4 py-2 bg-blue-600 text-white font-semibold rounded-xl hover:bg-blue-700 transition"
                                >
                                    {actionLoading ? 'Processing Receipt...' : 'Confirm Goods Receipt & Add to Stock'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
};

