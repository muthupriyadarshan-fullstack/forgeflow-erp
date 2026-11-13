import React from 'react';
import {
    Archive,
    Boxes,
    FileText,
    Gauge,
    Layers,
    Receipt,
    ShieldAlert,
    ShoppingCart,
    TrendingUp,
    Workflow,
} from 'lucide-react';

export type TabKey =
    | 'dashboard'
    | 'products'
    | 'inventory'
    | 'purchasing'
    | 'production'
    | 'reports'
    | 'audit';

interface SidebarProps {
    currentTab: TabKey;
    onSelectTab: (tab: TabKey) => void;
    lowStockCount?: number;
    pendingPOCount?: number;
}

export const Sidebar: React.FC<SidebarProps> = ({
    currentTab,
    onSelectTab,
    lowStockCount = 0,
    pendingPOCount = 0,
}) => {
    const navItems: Array<{
        key: TabKey;
        label: string;
        description: string;
        icon: React.ElementType;
        badge?: number | string;
        badgeColor?: string;
    }> = [
        {
            key: 'dashboard',
            label: 'Command Center',
            description: 'Plant KPIs & Metrics',
            icon: Gauge,
        },
        {
            key: 'products',
            label: 'Product Master & BOM',
            description: 'SKUs & Multi-Level BOM',
            icon: Boxes,
        },
        {
            key: 'inventory',
            label: 'Inventory & Stock Ledger',
            description: 'Double-Entry Movements',
            icon: Archive,
            badge: lowStockCount > 0 ? `${lowStockCount} Low` : undefined,
            badgeColor: 'bg-amber-100 text-amber-800 border-amber-200',
        },
        {
            key: 'purchasing',
            label: 'Procurement & GRN',
            description: 'PO Approvals & Receipts',
            icon: ShoppingCart,
            badge: pendingPOCount > 0 ? pendingPOCount : undefined,
            badgeColor: 'bg-blue-100 text-blue-800 border-blue-200',
        },
        {
            key: 'production',
            label: 'Shop Floor & Work Orders',
            description: 'Stages, Issue & Output',
            icon: Workflow,
        },
        {
            key: 'reports',
            label: 'Finance & Valuation',
            description: 'Weighted Avg & CSV Exports',
            icon: TrendingUp,
        },
        {
            key: 'audit',
            label: 'System Audit Logs',
            description: 'Tamper-Proof Ledger Trail',
            icon: ShieldAlert,
        },
    ];

    return (
        <aside className="w-64 bg-slate-900 text-slate-300 flex flex-col shrink-0 min-h-[calc(100vh-61px)] border-r border-slate-800">
            <div className="p-4">
                <div className="text-[11px] font-bold tracking-wider text-slate-400 uppercase px-3 mb-2">
                    Industrial Modules
                </div>
                <nav className="space-y-1">
                    {navItems.map((item) => {
                        const Icon = item.icon;
                        const isActive = currentTab === item.key;
                        return (
                            <button
                                key={item.key}
                                onClick={() => onSelectTab(item.key)}
                                className={`w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-left transition ${
                                    isActive
                                        ? 'bg-blue-600 text-white font-semibold shadow-md shadow-blue-900/40'
                                        : 'hover:bg-slate-800 hover:text-white text-slate-300'
                                }`}
                            >
                                <div className="flex items-center space-x-3">
                                    <Icon className={`w-4 h-4 shrink-0 ${isActive ? 'text-white' : 'text-slate-400'}`} />
                                    <div>
                                        <div className="text-xs font-semibold leading-tight">{item.label}</div>
                                        <div className={`text-[10px] ${isActive ? 'text-blue-100' : 'text-slate-500'}`}>
                                            {item.description}
                                        </div>
                                    </div>
                                </div>

                                {item.badge && (
                                    <span
                                        className={`px-1.5 py-0.5 text-[10px] font-bold rounded-full border ${
                                            isActive
                                                ? 'bg-white text-blue-900 border-transparent'
                                                : item.badgeColor || 'bg-slate-800 text-slate-300 border-slate-700'
                                        }`}
                                    >
                                        {item.badge}
                                    </span>
                                )}
                            </button>
                        );
                    })}
                </nav>
            </div>

            {/* Warehouse Quick Info */}
            <div className="mt-auto p-4 border-t border-slate-800 text-xs">
                <div className="bg-slate-800/80 rounded-xl p-3 border border-slate-700/60">
                    <div className="flex items-center space-x-2 text-slate-400 mb-1">
                        <Layers className="w-3.5 h-3.5 text-blue-400" />
                        <span className="font-semibold text-slate-200">Active Warehouses</span>
                    </div>
                    <ul className="text-[11px] text-slate-400 space-y-1 pl-5 list-disc">
                        <li>Bhosari Raw Yard (WH-01)</li>
                        <li>Chakan Staging Bay (WH-02)</li>
                        <li>Talegaon Finished Depot (WH-03)</li>
                    </ul>
                </div>
            </div>
        </aside>
    );
};

