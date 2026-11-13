import React, { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { Header } from './components/Header';
import { Sidebar, TabKey } from './components/Sidebar';
import { api } from './services/api';
import { DashboardData, User } from './types/erp';
import { AuditView } from './views/AuditView';
import { DashboardView } from './views/DashboardView';
import { InventoryView } from './views/InventoryView';
import { ProductionView } from './views/ProductionView';
import { ProductsView } from './views/ProductsView';
import { PurchasingView } from './views/PurchasingView';
import { ReportsView } from './views/ReportsView';

const App: React.FC = () => {
    const [user, setUser] = useState<User | null>(null);
    const [currentTab, setCurrentTab] = useState<TabKey>('dashboard');
    const [dashboardData, setDashboardData] = useState<DashboardData | null>(null);
    const [loading, setLoading] = useState(true);

    const initializeAuthAndData = async () => {
        setLoading(true);
        try {
            // If no token exists, log in as Company Admin by default
            if (!api.getToken()) {
                const res = await api.login('admin@forgeflow.in', 'Password123!');
                setUser(res.user);
            } else {
                try {
                    const uRes = await api.getMe();
                    setUser(uRes.user);
                } catch {
                    // Fallback to fresh login
                    const res = await api.login('admin@forgeflow.in', 'Password123!');
                    setUser(res.user);
                }
            }

            // Fetch live dashboard metrics
            const dData = await api.getDashboard();
            setDashboardData(dData);
        } catch (e) {
            console.error('Initialization failed', e);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        initializeAuthAndData();
    }, []);

    const handleSwitchRole = async (email: string) => {
        setLoading(true);
        try {
            const res = await api.login(email, 'Password123!');
            setUser(res.user);
            const dData = await api.getDashboard();
            setDashboardData(dData);
        } catch (e: any) {
            alert('Failed to switch role: ' + e.message);
        } finally {
            setLoading(false);
        }
    };

    const handleLogout = async () => {
        await api.logout();
        setUser(null);
        // Quick relogin as Employee demo to prevent blank screen
        handleSwitchRole('employee@forgeflow.in');
    };

    return (
        <div className="min-h-screen flex flex-col bg-slate-50 text-slate-900">
            <Header user={user} onSwitchRole={handleSwitchRole} onLogout={handleLogout} />

            <div className="flex-1 flex overflow-hidden">
                <Sidebar
                    currentTab={currentTab}
                    onSelectTab={(tab) => setCurrentTab(tab)}
                    lowStockCount={dashboardData?.metrics?.low_stock_count || 0}
                    pendingPOCount={dashboardData?.metrics?.pending_purchase_orders_count || 0}
                />

                <main className="flex-1 p-6 overflow-y-auto max-h-[calc(100vh-61px)]">
                    {currentTab === 'dashboard' && (
                        <DashboardView
                            data={dashboardData}
                            loading={loading}
                            onNavigate={(tab) => setCurrentTab(tab)}
                        />
                    )}

                    {currentTab === 'products' && <ProductsView />}

                    {currentTab === 'inventory' && <InventoryView />}

                    {currentTab === 'purchasing' && <PurchasingView />}

                    {currentTab === 'production' && <ProductionView />}

                    {currentTab === 'reports' && <ReportsView />}

                    {currentTab === 'audit' && <AuditView />}
                </main>
            </div>
        </div>
    );
};

const rootElement = document.getElementById('root');
if (rootElement) {
    createRoot(rootElement).render(<App />);
}

