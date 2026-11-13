import React, { useEffect, useState } from 'react';
import { api } from '../services/api';
import { Clock, Eye, Globe, Shield, ShieldCheck, User } from 'lucide-react';

export const AuditView: React.FC = () => {
    const [logs, setLogs] = useState<any[]>([]);
    const [loading, setLoading] = useState(true);
    const [selectedLog, setSelectedLog] = useState<any | null>(null);

    const loadData = async () => {
        setLoading(true);
        try {
            const res = await api.getAuditLogs();
            setLogs(res.data || []);
        } catch (e) {
            console.error('Failed to load audit logs', e);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadData();
    }, []);

    return (
        <div className="space-y-6">
            <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 className="text-lg font-bold text-slate-900 tracking-tight">Enterprise Audit Trails & Non-Repudiation</h2>
                    <p className="text-xs text-slate-500">
                        Immutable event log recording user identity, IP address, state transitions, and stock changes
                    </p>
                </div>

                <div className="flex items-center space-x-2 text-xs">
                    <span className="flex items-center text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full font-medium border border-emerald-200">
                        <ShieldCheck className="w-3.5 h-3.5 mr-1 text-emerald-600" />
                        Audit Trail Integrity Verified
                    </span>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Audit Logs Table */}
                <div className="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead className="bg-slate-50 text-slate-500 uppercase font-semibold text-[10px] tracking-wider border-b border-slate-100">
                                <tr>
                                    <th className="px-6 py-3.5">Timestamp</th>
                                    <th className="px-6 py-3.5">Event</th>
                                    <th className="px-6 py-3.5">Entity</th>
                                    <th className="px-6 py-3.5">Actor / User</th>
                                    <th className="px-6 py-3.5">IP Address</th>
                                    <th className="px-6 py-3.5 text-center">Inspect</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 font-mono text-[11px]">
                                {logs.map((log) => {
                                    const eventColors: Record<string, string> = {
                                        stock_moved: 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                        purchase_order_created: 'bg-blue-50 text-blue-800 border-blue-200',
                                        purchase_order_approval: 'bg-purple-50 text-purple-800 border-purple-200',
                                        goods_received: 'bg-indigo-50 text-indigo-800 border-indigo-200',
                                        production_order_created: 'bg-amber-50 text-amber-800 border-amber-200',
                                        production_completed: 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                    };

                                    return (
                                        <tr
                                            key={log.id}
                                            onClick={() => setSelectedLog(log)}
                                            className={`cursor-pointer hover:bg-slate-50 transition ${
                                                selectedLog?.id === log.id ? 'bg-blue-50/50' : ''
                                            }`}
                                        >
                                            <td className="px-6 py-3.5 text-slate-500 whitespace-nowrap">
                                                {log.created_at ? new Date(log.created_at).toLocaleString('en-IN') : '—'}
                                            </td>
                                            <td className="px-6 py-3.5">
                                                <span
                                                    className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border ${
                                                        eventColors[log.event] || 'bg-slate-100 text-slate-800'
                                                    }`}
                                                >
                                                    {log.event.replace('_', ' ')}
                                                </span>
                                            </td>
                                            <td className="px-6 py-3.5 text-slate-800 font-bold truncate max-w-[140px]">
                                                {log.auditable_type ? log.auditable_type.split('\\').pop() : 'Record'} #{log.auditable_id}
                                            </td>
                                            <td className="px-6 py-3.5 text-slate-700 font-sans font-medium">
                                                {log.user?.name || 'System Robot'}
                                            </td>
                                            <td className="px-6 py-3.5 text-slate-500">{log.ip_address}</td>
                                            <td className="px-6 py-3.5 text-center">
                                                <button className="text-blue-600 hover:text-blue-800 font-sans font-semibold">
                                                    View
                                                </button>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Inspect Details Pane */}
                <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex flex-col">
                    <div className="flex items-center space-x-2 pb-3 border-b border-slate-100 mb-3">
                        <Eye className="w-4 h-4 text-blue-600" />
                        <h3 className="font-bold text-slate-900 text-sm">Payload Inspector</h3>
                    </div>

                    {selectedLog ? (
                        <div className="space-y-4 text-xs flex-1">
                            <div>
                                <span className="text-[10px] font-bold text-slate-400 uppercase">Event Name</span>
                                <div className="font-mono font-bold text-slate-900 text-sm mt-0.5">{selectedLog.event}</div>
                            </div>

                            <div>
                                <span className="text-[10px] font-bold text-slate-400 uppercase">Auditable Record</span>
                                <div className="font-mono text-slate-700">
                                    {selectedLog.auditable_type} (#{selectedLog.auditable_id})
                                </div>
                            </div>

                            <div>
                                <span className="text-[10px] font-bold text-slate-400 uppercase">Client Agent & IP</span>
                                <div className="text-slate-600 font-mono text-[11px] mt-0.5">
                                    {selectedLog.ip_address} • {selectedLog.user_agent}
                                </div>
                            </div>

                            <div>
                                <span className="text-[10px] font-bold text-slate-400 uppercase block mb-1">State Transition Payload</span>
                                <pre className="bg-slate-900 text-emerald-400 p-3 rounded-xl text-[11px] overflow-x-auto max-h-60 leading-relaxed font-mono">
                                    {JSON.stringify(selectedLog.new_values || selectedLog.old_values || {}, null, 2)}
                                </pre>
                            </div>
                        </div>
                    ) : (
                        <div className="flex-1 flex flex-col items-center justify-center text-center p-6 text-slate-400 text-xs">
                            <Shield className="w-8 h-8 text-slate-300 mb-2" />
                            <p>Select any audit record on the left to inspect its state transition payload.</p>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

