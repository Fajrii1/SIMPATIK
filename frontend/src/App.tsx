import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './context/AuthContext';
import { MainLayout } from './layouts/MainLayout';
import { LoginPage } from './pages/LoginPage';
import { DashboardPage } from './pages/DashboardPage';
import { ActivitiesPage } from './pages/ActivitiesPage';
import { WorkloadPage } from './pages/WorkloadPage';
import { CalendarPage } from './pages/CalendarPage';
import { IssuesPage } from './pages/IssuesPage';
import { ReportsPage } from './pages/ReportsPage';
import { UsersPage } from './pages/UsersPage';
import { NotificationsPage } from './pages/NotificationsPage';
import { SettingsPage } from './pages/SettingsPage';
import { ProfilePage } from './pages/ProfilePage';
import { ChangePasswordPage } from './pages/ChangePasswordPage';

// Proteksi Rute Terautentikasi
const ProtectedRoute: React.FC<{
  children: React.ReactNode;
  adminOnly?: boolean;
  reportsOnly?: boolean;
  allowPendingPassword?: boolean;
}> = ({ children, adminOnly = false, reportsOnly = false, allowPendingPassword = false }) => {
  const { user, loading } = useAuth();

  if (loading) {
    return (
      <div className="h-screen w-screen flex items-center justify-center bg-slate-50">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-sky-600"></div>
      </div>
    );
  }

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  // Pengguna yang wajib ganti kata sandi diarahkan ke halaman khusus sebelum masuk aplikasi.
  if (user.must_change_password && !allowPendingPassword) {
    return <Navigate to="/ganti-sandi" replace />;
  }

  if (adminOnly && user.peran !== 'admin') {
    return <Navigate to="/" replace />;
  }

  const canViewReports =
    user.peran === 'admin' || user.peran === 'pimpinan' || Boolean(user.is_ketua);

  if (reportsOnly && !canViewReports) {
    return <Navigate to="/" replace />;
  }

  return <>{children}</>;
};

export const App: React.FC = () => {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          <Route path="/login" element={<LoginPage />} />

          <Route
            path="/ganti-sandi"
            element={
              <ProtectedRoute allowPendingPassword>
                <ChangePasswordPage />
              </ProtectedRoute>
            }
          />

          <Route
            element={
              <ProtectedRoute>
                <MainLayout />
              </ProtectedRoute>
            }
          >
            <Route path="/" element={<DashboardPage />} />
            <Route path="/calendar" element={<CalendarPage />} />
            <Route path="/activities" element={<ActivitiesPage />} />
            <Route path="/workload" element={<WorkloadPage />} />
            <Route path="/issues" element={<IssuesPage />} />
            <Route
              path="/reports"
              element={
                <ProtectedRoute reportsOnly>
                  <ReportsPage />
                </ProtectedRoute>
              }
            />
            <Route path="/notifications" element={<NotificationsPage />} />
            <Route path="/profile" element={<ProfilePage />} />

            {/* Khusus Admin */}
            <Route
              path="/users"
              element={
                <ProtectedRoute adminOnly>
                  <UsersPage />
                </ProtectedRoute>
              }
            />
            <Route
              path="/settings"
              element={
                <ProtectedRoute adminOnly>
                  <SettingsPage />
                </ProtectedRoute>
              }
            />
          </Route>

          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  );
};

export default App;
