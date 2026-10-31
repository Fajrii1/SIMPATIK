import React, { useState, useEffect } from 'react';
import { Outlet, Link, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import {
  Calendar,
  LayoutDashboard,
  ClipboardList,
  BarChart3,
  AlertTriangle,
  Users,
  Settings,
  Bell,
  LogOut,
  Shield,
  FileSpreadsheet,
  Menu,
  X,
} from 'lucide-react';
import api from '../services/api';

export const MainLayout: React.FC = () => {
  const { user, logout } = useAuth();
  const location = useLocation();
  const [unreadCount, setUnreadCount] = useState(0);
  const [drawerOpen, setDrawerOpen] = useState(false);

  const fetchUnread = async () => {
    try {
      const res = await api.get('/notifications/unread-count');
      setUnreadCount(res.data.unread_count || 0);
    } catch {
      // ignore
    }
  };

  useEffect(() => {
    fetchUnread();
    const interval = setInterval(fetchUnread, 30000);
    return () => clearInterval(interval);
  }, []);

  // Close drawer on escape key
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && drawerOpen) {
        setDrawerOpen(false);
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [drawerOpen]);

  // Prevent background scroll when mobile drawer is open
  useEffect(() => {
    if (drawerOpen) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = 'unset';
    }
  }, [drawerOpen]);

  const navItems = [
    { label: 'Dashboard', path: '/', icon: LayoutDashboard },
    { label: 'Kalender Kegiatan', path: '/calendar', icon: Calendar },
    { label: 'Daftar Kegiatan', path: '/activities', icon: ClipboardList },
    { label: 'Monitoring Beban Kerja', path: '/workload', icon: BarChart3 },
    { label: 'Kendala Mingguan', path: '/issues', icon: AlertTriangle },
    { label: 'Laporan & Ekspor', path: '/reports', icon: FileSpreadsheet },
    ...(user?.peran === 'admin'
      ? [
          { label: 'Manajemen Pengguna & Tim', path: '/users', icon: Users },
          { label: 'Pengaturan Sistem', path: '/settings', icon: Settings },
          { label: 'Audit Log', path: '/audit-logs', icon: Shield },
        ]
      : []),
  ];

  const handleNavClick = () => {
    setDrawerOpen(false);
  };

  return (
    <div className="flex h-screen bg-slate-50 overflow-hidden">
      {/* Mobile Drawer Backdrop Overlay */}
      {drawerOpen && (
        <div
          role="button"
          tabIndex={0}
          aria-label="Tutup navigasi"
          onClick={() => setDrawerOpen(false)}
          onKeyDown={(e) => {
            if (e.key === 'Enter' || e.key === ' ') setDrawerOpen(false);
          }}
          className="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-40 lg:hidden transition-opacity"
        />
      )}

      {/* Sidebar: Drawer on mobile/tablet (<1024px), Static left sidebar on desktop (>=1024px) */}
      <aside
        className={`fixed lg:static top-0 bottom-0 left-0 z-50 w-72 lg:w-64 bg-slate-900 text-slate-100 flex flex-col border-r border-slate-800 transform transition-transform duration-300 ease-in-out ${
          drawerOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
        }`}
      >
        {/* Brand Header */}
        <div className="p-4 sm:p-5 border-b border-slate-800 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <img 
            src="/LogoBPS2.png"
            alt="Logo BPS" 
            className="w-12 h-12 object-contain flex-shrink-0" 
            />
            <div>
              <h1 className="font-bold text-sm tracking-wide text-white leading-tight">SIMPATIK</h1>
              <p className="text-xs text-sky-400 font-medium">Sistem Penjadwalan & Monitoring Kegiatan Statistik</p>
            </div>
          </div>
          {/* Close drawer button for mobile */}
          <button
            onClick={() => setDrawerOpen(false)}
            aria-label="Tutup menu sidebar"
            className="p-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 lg:hidden min-h-[44px] min-w-[44px] flex items-center justify-center"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Navigation Items */}
        <nav className="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
          {navItems.map((item) => {
            const Icon = item.icon;
            const isActive =
              location.pathname === item.path ||
              (item.path !== '/' && location.pathname.startsWith(item.path));
            return (
              <Link
                key={item.path}
                to={item.path}
                onClick={handleNavClick}
                className={`flex items-center gap-3 px-3.5 py-3 rounded-lg text-sm font-medium transition-colors min-h-[44px] ${
                  isActive
                    ? 'bg-sky-600 text-white shadow-sm'
                    : 'text-slate-300 hover:bg-slate-800 hover:text-white'
                }`}
              >
                <Icon className="w-5 h-5 shrink-0" />
                <span>{item.label}</span>
              </Link>
            );
          })}
        </nav>

        {/* User profile footer */}
        <div className="p-4 border-t border-slate-800 bg-slate-950/40">
          <div className="flex items-center justify-between">
            <div className="min-w-0 flex-1 mr-2">
              <p className="text-sm font-semibold text-white truncate">{user?.nama}</p>
              <p className="text-xs text-slate-400 capitalize">{user?.peran}</p>
            </div>
            <button
              onClick={() => logout()}
              title="Keluar"
              aria-label="Keluar dari akun"
              className="p-2.5 rounded-lg hover:bg-rose-900/40 text-slate-400 hover:text-rose-400 transition min-h-[44px] min-w-[44px] flex items-center justify-center"
            >
              <LogOut className="w-5 h-5" />
            </button>
          </div>
        </div>
      </aside>

      {/* Main Content Area */}
      <div className="flex-1 flex flex-col min-w-0 overflow-hidden">
        {/* Top Navbar */}
        <header className="h-16 bg-white border-b border-slate-200 px-4 sm:px-6 flex items-center justify-between shrink-0 shadow-xs">
          <div className="flex items-center gap-3">
            {/* Hamburger button on Mobile/Tablet */}
            <button
              onClick={() => setDrawerOpen(true)}
              aria-label="Buka menu navigasi"
              className="p-2 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 lg:hidden min-h-[44px] min-w-[44px] flex items-center justify-center"
            >
              <Menu className="w-6 h-6" />
            </button>

            <span className="hidden sm:inline-block text-xs font-semibold px-2.5 py-1 bg-sky-50 text-sky-700 rounded-full border border-sky-100">
              Sistem Aktif
            </span>
          </div>

          <div className="flex items-center gap-2 sm:gap-4">
            <Link
              to="/notifications"
              className="relative p-2.5 rounded-full hover:bg-slate-100 text-slate-600 transition min-h-[44px] min-w-[44px] flex items-center justify-center"
              title="Notifikasi"
              aria-label="Lihat Notifikasi"
            >
              <Bell className="w-5 h-5" />
              {unreadCount > 0 && (
                <span className="absolute top-1 right-1 w-4 h-4 bg-rose-600 text-white text-[10px] font-bold rounded-full flex items-center justify-center">
                  {unreadCount > 9 ? '9+' : unreadCount}
                </span>
              )}
            </Link>

            <Link
              to="/profile"
              className="flex items-center gap-2 pl-2 sm:pl-3 border-l border-slate-200 text-sm font-medium text-slate-700 hover:text-sky-600 min-h-[44px]"
            >
              <div className="w-8 h-8 rounded-full bg-sky-100 text-sky-800 flex items-center justify-center font-bold text-xs shrink-0">
                {user?.nama ? user.nama.substring(0, 2).toUpperCase() : 'BPS'}
              </div>
              <span className="hidden md:inline truncate max-w-[120px]">{user?.nama}</span>
            </Link>
          </div>
        </header>

        {/* Dynamic Outlet Container */}
        <main className="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
          <Outlet />
        </main>
      </div>
    </div>
  );
};
