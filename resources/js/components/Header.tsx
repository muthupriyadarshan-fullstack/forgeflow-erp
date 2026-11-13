import React, { useState } from 'react';
import { RoleName, User } from '../types/erp';
import {
    Activity,
    Bell,
    CheckCircle2,
    ChevronDown,
    Factory,
    LogOut,
    Shield,
    Sparkles,
    UserCircle2,
} from 'lucide-react';

interface HeaderProps {
    user: User | null;
    onSwitchRole: (email: string) => void;
    onLogout: () => void;
}

const DEMO_ACCOUNTS: Array<{ role: RoleName; email: string; name: string; dept: string }> = [
    { role: 'Super Admin', email: 'superadmin@forgeflow.in', name: 'Vikramaditya S.', dept: 'Executive' },
    { role: 'Company Admin', email: 'admin@forgeflow.in', name: 'Arunachalam M.', dept: 'Plant Operations' },
    { role: 'Production Manager', email: 'production@forgeflow.in', name: 'Devendra D.', dept: 'Manufacturing' },
    { role: 'Inventory Manager', email: 'inventory@forgeflow.in', name: 'Kavita Iyer', dept: 'Materials & Stores' },
    { role: 'Purchase Manager', email: 'purchase@forgeflow.in', name: 'Sanjay B.', dept: 'Procurement' },
    { role: 'Warehouse Staff', email: 'warehouse@forgeflow.in', name: 'Rameshwar Y.', dept: 'Logistics' },
    { role: 'Finance Manager', email: 'finance@forgeflow.in', name: 'Pooja Agarwal', dept: 'Finance & Accounts' },
    { role: 'Employee', email: 'employee@forgeflow.in', name: 'Suresh Shinde', dept: 'Shop Floor' },
];

export const Header: React.FC<HeaderProps> = ({ user, onSwitchRole, onLogout }) => {
    const [roleDropdownOpen, setRoleDropdownOpen] = useState(false);

    return (
        <header className="sticky top-0 z-40 bg-white border-b border-slate-200 shadow-xs">
            <div className="px-6 py-3 flex items-center justify-between">
                {/* Brand & Factory Status */}
                <div className="flex items-center space-x-4">
                    <div className="flex items-center space-x-3">
                        <div className="w-10 h-10 rounded-lg bg-gradient-to-tr from-slate-900 via-blue-900 to-indigo-800 flex items-center justify-center text-white shadow-md">
                            <Factory className="w-5 h-5 text-blue-400" />
                        </div>
                        <div>
                            <div className="flex items-center space-x-2">
                                <span className="font-extrabold text-lg text-slate-900 tracking-tight">ForgeFlow</span>
                                <span className="px-1.5 py-0.5 text-[10px] font-bold tracking-wide uppercase bg-blue-50 text-blue-700 rounded border border-blue-200">
                                    Enterprise ERP
                                </span>
                            </div>
                            <p className="text-xs text-slate-500 font-medium">Chakan Heavy Forgings Plant II • INR (₹)</p>
                        </div>
                    </div>

                    <div className="hidden lg:flex items-center space-x-2 pl-4 border-l border-slate-200 text-xs">
                        <span className="flex items-center text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full font-medium border border-emerald-200">
                            <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>
                            Double-Entry Ledger Active
                        </span>
                        <span className="flex items-center text-slate-600 bg-slate-100 px-2 py-1 rounded-full font-medium">
                            <Activity className="w-3.5 h-3.5 mr-1 text-slate-500" />
                            Locking: Invariant Enforced
                        </span>
                    </div>
                </div>

                {/* Role Switcher & User Profile */}
                <div className="flex items-center space-x-3">
                    {/* Demo Role Switcher Dropdown */}
                    <div className="relative">
                        <button
                            onClick={() => setRoleDropdownOpen(!roleDropdownOpen)}
                            className="flex items-center space-x-2 px-3 py-1.5 rounded-lg border border-indigo-200 bg-indigo-50 hover:bg-indigo-100 text-indigo-900 text-xs font-semibold transition"
                            title="Quick switch between any of the 8 enterprise roles"
                        >
                            <Sparkles className="w-3.5 h-3.5 text-indigo-600" />
                            <span>Role: <strong>{user?.roles?.[0] || 'Switch Role'}</strong></span>
                            <ChevronDown className="w-3.5 h-3.5 text-indigo-500" />
                        </button>

                        {roleDropdownOpen && (
                            <div className="absolute right-0 mt-2 w-72 bg-white rounded-xl shadow-xl border border-slate-200 p-2 z-50">
                                <div className="px-3 py-2 border-b border-slate-100">
                                    <p className="text-xs font-bold text-slate-800">Quick Role Switcher</p>
                                    <p className="text-[11px] text-slate-500">Simulate granular access permissions</p>
                                </div>
                                <div className="py-1 max-h-72 overflow-y-auto">
                                    {DEMO_ACCOUNTS.map((acc) => {
                                        const isActive = user?.email === acc.email;
                                        return (
                                            <button
                                                key={acc.email}
                                                onClick={() => {
                                                    setRoleDropdownOpen(false);
                                                    onSwitchRole(acc.email);
                                                }}
                                                className={`w-full text-left px-3 py-2 rounded-lg text-xs flex items-center justify-between transition ${
                                                    isActive
                                                        ? 'bg-blue-50 text-blue-900 font-bold'
                                                        : 'hover:bg-slate-50 text-slate-700'
                                                }`}
                                            >
                                                <div>
                                                    <div className="font-semibold">{acc.role}</div>
                                                    <div className="text-[11px] text-slate-500">{acc.name} • {acc.dept}</div>
                                                </div>
                                                {isActive && <CheckCircle2 className="w-4 h-4 text-blue-600 shrink-0" />}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* User Info & Logout */}
                    <div className="flex items-center space-x-2 pl-3 border-l border-slate-200">
                        <div className="w-8 h-8 rounded-full bg-slate-900 text-white flex items-center justify-center font-bold text-xs">
                            {user?.name?.charAt(0) || 'U'}
                        </div>
                        <div className="hidden sm:block text-left">
                            <div className="text-xs font-bold text-slate-900 leading-tight">{user?.name}</div>
                            <div className="text-[11px] text-slate-500 font-medium">{user?.roles?.[0]}</div>
                        </div>

                        <button
                            onClick={onLogout}
                            className="p-1.5 text-slate-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition"
                            title="Sign out"
                        >
                            <LogOut className="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>
        </header>
    );
};

