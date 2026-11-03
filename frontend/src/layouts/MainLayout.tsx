import React, { useState, useEffect, useRef } from "react";
import { Outlet, Link, useLocation } from "react-router-dom";
import { useAuth } from "../context/AuthContext";
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
  PanelLeftClose,
  PanelLeftOpen,
  User as UserIcon,
} from "lucide-react";
import api from "../services/api";

const SIDEBAR_COLLAPSED_KEY = "simpatik.sidebar.collapsed";

const readSidebarCollapsed = (): boolean => {
  try {
    return window.localStorage.getItem(SIDEBAR_COLLAPSED_KEY) === "true";
  } catch {
    return false;
  }
};

export const MainLayout: React.FC = () => {
  const { user, logout } = useAuth();
  const location = useLocation();
  const [unreadCount, setUnreadCount] = useState(0);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [collapsed, setCollapsed] = useState(readSidebarCollapsed);
  const [profileOpen, setProfileOpen] = useState(false);
  const [confirmLogout, setConfirmLogout] = useState(false);
  const [loggingOut, setLoggingOut] = useState(false);
  const profileRef = useRef<HTMLDivElement>(null);

  const fetchUnread = async () => {
    try {
      const res = await api.get("/notifications/unread-count");
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
      if (e.key === "Escape") {
        setDrawerOpen(false);
        setProfileOpen(false);
      }
    };
    window.addEventListener("keydown", handleKeyDown);
    return () => window.removeEventListener("keydown", handleKeyDown);
  }, []);

  // Prevent background scroll when mobile drawer is open
  useEffect(() => {
    if (drawerOpen) {
      document.body.style.overflow = "hidden";
    } else {
      document.body.style.overflow = "unset";
    }
    return () => {
      document.body.style.overflow = "unset";
    };
  }, [drawerOpen]);

  // Close profile dropdown on outside click
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (profileRef.current && !profileRef.current.contains(event.target as Node)) {
        setProfileOpen(false);
      }
    };
    if (profileOpen) {
      document.addEventListener("mousedown", handleClickOutside);
    }
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, [profileOpen]);

  const canViewReports =
    user?.peran === "admin" || user?.peran === "pimpinan" || Boolean(user?.is_ketua);

  const mainNavItems = [
    { label: "Dashboard", path: "/", icon: LayoutDashboard },
    { label: "Kalender Kegiatan", path: "/calendar", icon: Calendar },
    { label: "Daftar Kegiatan", path: "/activities", icon: ClipboardList },
    { label: "Monitoring Beban Kerja", path: "/workload", icon: BarChart3 },
    { label: "Kendala Mingguan", path: "/issues", icon: AlertTriangle },
    ...(canViewReports
      ? [{ label: "Laporan & Ekspor", path: "/reports", icon: FileSpreadsheet }]
      : []),
  ];

  const adminNavItems = [
    { label: "Manajemen Pengguna & Tim", path: "/users", icon: Users },
    { label: "Pengaturan Sistem", path: "/settings", icon: Settings },
    { label: "Audit Log", path: "/audit-logs", icon: Shield },
  ];

  const handleNavClick = () => {
    setDrawerOpen(false);
  };

  const toggleCollapsed = () => {
    setCollapsed((value) => {
      const next = !value;
      try {
        window.localStorage.setItem(SIDEBAR_COLLAPSED_KEY, String(next));
      } catch {
        // localStorage tidak tersedia; status tetap berlaku selama sesi ini.
      }
      return next;
    });
  };

  const handleLogout = async () => {
    setLoggingOut(true);
    try {
      await logout();
    } catch {
      // AuthContext tetap membersihkan sesi lokal bila permintaan logout gagal.
    } finally {
      setLoggingOut(false);
      setConfirmLogout(false);
    }
  };

  const initials = user?.nama
    ? user.nama
        .split(" ")
        .map((part) => part[0])
        .join("")
        .substring(0, 2)
        .toUpperCase()
    : "BPS";

  const sidebarWidth = collapsed ? "w-20" : "w-64";

  const renderNavItem = (item: { label: string; path: string; icon: React.ComponentType<{ className?: string }> }, compact: boolean) => {
    const Icon = item.icon;
    const isActive =
      location.pathname === item.path ||
      (item.path !== "/" && location.pathname.startsWith(item.path));
    return (
      <Link
        key={item.path}
        to={item.path}
        onClick={(event) => {
          event.stopPropagation();
          handleNavClick();
        }}
        title={compact ? item.label : undefined}
        className={`flex items-center gap-3 rounded-lg text-sm font-medium transition-colors min-h-[44px] ${
          compact ? "justify-center px-2" : "px-3.5"
        } ${
          isActive
            ? "bg-sky-600 text-white shadow-sm"
            : "text-slate-300 hover:bg-white/10 hover:text-white"
        }`}
      >
        <Icon className="w-5 h-5 shrink-0" />
        {!compact && <span className="truncate">{item.label}</span>}
      </Link>
    );
  };

  const sidebarContent = (compact: boolean) => (
    <>
      {/* Brand Header (desktop) */}
      <div
        className={`border-b border-white/10 flex items-center ${
          compact ? "flex-col justify-center gap-1 p-2" : "justify-between p-4 sm:p-5 gap-3"
        }`}
      >
        <div className={`flex items-center ${compact ? "justify-center" : "gap-3"}`}>
          <img
            src="/LogoBPS2.png"
            alt="Logo BPS"
            className={`${compact ? "w-8 h-8" : "w-12 h-12"} object-contain flex-shrink-0`}
          />
          {!compact && (
            <div className="min-w-0">
              <h1 className="font-bold text-sm tracking-wide text-white leading-tight">
                SIMPATIK
              </h1>
              <p className="text-xs text-sky-400 font-medium">
                Sistem Penjadwalan & Monitoring Kegiatan Statistik
              </p>
            </div>
          )}
        </div>
        <div className="flex items-center">
          {/* Close drawer button for mobile */}
          <button
            onClick={() => setDrawerOpen(false)}
            aria-label="Tutup menu sidebar"
            className="p-2 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 lg:hidden min-h-[44px] min-w-[44px] flex items-center justify-center"
          >
            <X className="w-5 h-5" />
          </button>
        </div>
      </div>

      {/* Navigation Items */}
      <nav className="flex-1 px-2 py-4 space-y-1 overflow-y-auto no-scrollbar">
        {!compact && (
          <p className="px-3.5 mb-3 text-xs font-semibold tracking-wider text-slate-400 uppercase">
            Main Menu
          </p>
        )}
        {mainNavItems.map((item) => renderNavItem(item, compact))}

        {user?.peran === "admin" && (
          <div className="mt-6 space-y-1">
            {!compact && (
              <p className="px-3.5 mb-3 text-xs font-semibold tracking-wider text-slate-400 uppercase">
                Administration
              </p>
            )}
            {adminNavItems.map((item) => renderNavItem(item, compact))}
          </div>
        )}
      </nav>
    </>
  );

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
            if (e.key === "Enter" || e.key === " ") setDrawerOpen(false);
          }}
          className="fixed inset-0 bg-bps/60 backdrop-blur-xs z-40 lg:hidden transition-opacity"
        />
      )}

      {/* Sidebar: Drawer on mobile, collapsible on desktop */}
      <aside
        className={`fixed lg:static top-0 bottom-0 left-0 z-50 ${sidebarWidth} bg-bps text-slate-100 flex flex-col border-r border-white/10 transform transition-all duration-300 ease-in-out ${
          drawerOpen ? "translate-x-0" : "-translate-x-full lg:translate-x-0"
        }`}
        onClick={toggleCollapsed}
      >
        {sidebarContent(drawerOpen ? false : collapsed)}
      </aside>

      {/* Main Content Area */}
      <div className="flex-1 flex flex-col min-w-0 overflow-hidden">
        {/* Top Navbar */}
        <header className="h-16 bg-bps border-b border-white/10 px-4 sm:px-6 flex items-center justify-between shrink-0 shadow-xs">
          <div className="flex items-center gap-3">
            {/* Hamburger button on Mobile/Tablet */}
            <button
              onClick={() => setDrawerOpen(true)}
              aria-label="Buka menu navigasi"
              className="p-2 rounded-lg text-slate-300 hover:text-white hover:bg-white/10 lg:hidden min-h-[44px] min-w-[44px] flex items-center justify-center"
            >
              <Menu className="w-6 h-6" />
            </button>

            {/* Chevron toggle sidebar (desktop only) - hanya muncul saat collapse */}
            {/* Tombol hamburger toggle sidebar (desktop only) */}
            <button
              type="button"
              onClick={(event) => {
                event.stopPropagation();
                toggleCollapsed();
              }}
              aria-label={collapsed ? "Perluas sidebar" : "Perkecil sidebar"}
              aria-expanded={!collapsed}
              title={collapsed ? "Perluas sidebar" : "Perkecil sidebar"}
              className="hidden lg:flex items-center justify-center rounded-lg p-2 text-slate-300 hover:bg-white/10 hover:text-white transition-colors min-h-[44px] min-w-[44px]"
            >
              <Menu className="w-5 h-5" />
            </button>

            {/* Judul aplikasi pindah ke navbar saat sidebar diperkecil */}
            {collapsed && !drawerOpen && (
              <div className="flex flex-col leading-tight">
                <span className="font-bold text-sm tracking-wide text-white">
                  SIMPATIK
                </span>
                <span className="text-[10px] text-sky-400 font-medium">
                  Sistem Penjadwalan & Monitoring Kegiatan Statistik
                </span>
              </div>
            )}
          </div>

          <div className="flex items-center gap-2 sm:gap-4">
            <Link
              to="/notifications"
              className="relative p-2.5 rounded-full hover:bg-white/10 text-white transition min-h-[44px] min-w-[44px] flex items-center justify-center"
              title="Notifikasi"
              aria-label="Lihat Notifikasi"
            >
              <Bell className="w-5 h-5 text-white" />
              {unreadCount > 0 && (
                <span className="absolute top-1 right-1 w-4 h-4 bg-rose-600 text-white text-[10px] font-bold rounded-full flex items-center justify-center">
                  {unreadCount > 9 ? "9+" : unreadCount}
                </span>
              )}
            </Link>

            {/* Profile dropdown */}
            <div className="relative" ref={profileRef}>
              <button
                onClick={() => setProfileOpen((open) => !open)}
                aria-haspopup="menu"
                aria-expanded={profileOpen}
                className="group flex items-center gap-2 pl-2 sm:pl-3 border-l border-slate-700 text-sm font-medium text-white hover:text-sky-300 min-h-[44px] rounded-lg px-2 transition-colors duration-200"
              >
                <div className="w-8 h-8 rounded-full bg-sky-100 text-sky-800 flex items-center justify-center font-bold text-xs shrink-0">
                  {initials}
                </div>
                <div className="hidden md:flex flex-col items-start leading-tight">
                  <span className="truncate max-w-[120px] text-white transition-colors duration-200 group-hover:text-sky-300">
                    {user?.nama}
                  </span>
                  <span className="text-[10px] text-slate-300 capitalize transition-colors duration-200 group-hover:text-sky-300">
                    {user?.peran}
                  </span>
                </div>
              </button>

              {profileOpen && (
                <div
                  role="menu"
                  className="absolute right-0 mt-2 w-60 rounded-xl border border-slate-200 bg-white shadow-lg z-50 overflow-hidden"
                >
                  <div className="px-4 py-3 border-b border-slate-100">
                    <p className="text-sm font-semibold text-slate-800 truncate">
                      {user?.nama}
                    </p>
                    <p className="text-xs text-slate-500 capitalize">{user?.peran}</p>
                  </div>
                  <div className="py-1">
                    <Link
                      to="/profile"
                      onClick={() => setProfileOpen(false)}
                      className="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"
                      role="menuitem"
                    >
                      <UserIcon className="w-4 h-4" />
                      Profil & Keamanan Akun
                    </Link>
                    <button
                      onClick={() => {
                        setProfileOpen(false);
                        setConfirmLogout(true);
                      }}
                      className="w-full flex items-center gap-2 px-4 py-2 text-sm text-rose-600 hover:bg-rose-50"
                      role="menuitem"
                    >
                      <LogOut className="w-4 h-4" />
                      Keluar
                    </button>
                  </div>
                </div>
              )}
            </div>
          </div>
        </header>

        {/* Dynamic Outlet Container */}
        <main className="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
          <Outlet />
        </main>
      </div>

      {/* Logout confirmation dialog */}
      {confirmLogout && (
        <div className="fixed inset-0 z-[60] flex items-center justify-center bg-bps/60 p-4">
          <div className="w-full max-w-sm rounded-xl bg-white p-5 shadow-xl">
            <h2 className="text-base font-bold text-slate-800">Konfirmasi Keluar</h2>
            <p className="mt-2 text-sm text-slate-600">
              Apakah Anda yakin ingin keluar dari aplikasi?
            </p>
            <div className="mt-4 flex justify-end gap-2">
              <button
                onClick={() => setConfirmLogout(false)}
                disabled={loggingOut}
                className="px-4 py-2 text-sm rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 disabled:opacity-50"
              >
                Batal
              </button>
              <button
                onClick={handleLogout}
                disabled={loggingOut}
                className="px-4 py-2 text-sm rounded-lg bg-rose-600 text-white hover:bg-rose-700 disabled:opacity-50"
              >
                {loggingOut ? "Memproses..." : "Keluar"}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};