import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  AlertCircle,
  Eye,
  EyeOff,
  KeyRound,
  Loader2,
  LogOut,
  ShieldCheck,
} from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import api from '../services/api';

const PALETTE = {
  navy: 'var(--color-bps)',
  blue: '#1F4E8C',
  paleBlue: '#E3EEFA',
  bg: '#F7F9FC',
  slate: '#5B6676',
  ink: '#1F2937',
  coral: '#E5604D',
  blush: '#FCE4E0',
  teal: '#0B8F80',
  mint: '#DDF4EF',
};

const fieldClass =
  'h-11 w-full rounded-lg border border-[#E3EEFA] bg-white px-3 pr-11 text-sm outline-none transition focus:border-[#3B82C4] focus:ring-2 focus:ring-[#E3EEFA]';

export const ChangePasswordPage: React.FC = () => {
  const { user, refreshUser, logout } = useAuth();
  const navigate = useNavigate();

  const [currentPassword, setCurrentPassword] = useState('');
  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const forced = Boolean(user?.must_change_password);

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    setError('');

    if (!currentPassword) {
      setError('Kata sandi saat ini wajib diisi.');
      return;
    }
    if (password.length < 8) {
      setError('Kata sandi baru minimal 8 karakter.');
      return;
    }
    if (password !== confirmation) {
      setError('Konfirmasi kata sandi baru tidak cocok.');
      return;
    }
    if (password === currentPassword) {
      setError('Kata sandi baru harus berbeda dari kata sandi saat ini.');
      return;
    }

    setLoading(true);

    try {
      await api.post('/change-password', {
        current_password: currentPassword,
        password,
        password_confirmation: confirmation,
      });

      await refreshUser();
      navigate('/', { replace: true });
    } catch (requestError: unknown) {
      const maybeError = requestError as {
        response?: { data?: { message?: string; errors?: Record<string, string[]> } };
        message?: string;
      };

      const validationErrors = maybeError.response?.data?.errors;
      const firstValidationError = validationErrors
        ? Object.values(validationErrors).flat()[0]
        : undefined;

      setError(
        firstValidationError ||
          maybeError.response?.data?.message ||
          maybeError.message ||
          'Gagal mengubah kata sandi. Silakan coba lagi.'
      );
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flex min-h-screen w-full items-center justify-center bg-[#F7F9FC] p-4 sm:p-8">
      <div className="w-full max-w-md">
        <div className="mb-6 flex items-center justify-center gap-3">
          <img src="/LogoBPS.png" alt="Logo BPS" className="h-12 w-14 object-contain" />
          <div>
            <p className="text-base font-extrabold tracking-wider" style={{ color: PALETTE.navy }}>
              SIMPATIK
            </p>
            <p className="text-[11px] font-medium" style={{ color: PALETTE.slate }}>
              BPS Kota Palembang
            </p>
          </div>
        </div>

        <div className="rounded-xl border border-[#E3EEFA] bg-white p-5 shadow-sm sm:p-6">
          <span
            className="mb-3 flex h-10 w-10 items-center justify-center rounded-lg"
            style={{ backgroundColor: PALETTE.paleBlue, color: PALETTE.blue }}
          >
            <KeyRound className="h-5 w-5" />
          </span>

          <h1 className="text-lg font-bold leading-tight" style={{ color: PALETTE.navy }}>
            Ganti Kata Sandi
          </h1>
          <p className="mt-1 text-xs" style={{ color: PALETTE.slate }}>
            {forced
              ? 'Akun Anda wajib mengganti kata sandi sebelum dapat menggunakan aplikasi.'
              : 'Perbarui kata sandi akun Anda secara berkala untuk menjaga keamanan.'}
          </p>

          {forced && (
            <div
              className="mt-3 flex items-start gap-2 rounded-lg border px-3 py-2 text-[11px]"
              style={{
                backgroundColor: PALETTE.mint,
                borderColor: PALETTE.teal,
                color: PALETTE.teal,
              }}
            >
              <ShieldCheck className="mt-0.5 h-3.5 w-3.5 shrink-0" />
              <span>
                Seluruh menu dikunci sampai kata sandi berhasil diubah. Gunakan kata sandi baru
                minimal 8 karakter.
              </span>
            </div>
          )}

          <p className="mt-3 text-[11px]" style={{ color: PALETTE.slate }}>
            Masuk sebagai <span className="font-semibold" style={{ color: PALETTE.ink }}>{user?.nama}</span>
            {user?.email ? ` (${user.email})` : ''}
          </p>

          <form onSubmit={handleSubmit} className="mt-4 space-y-3">
            <div>
              <label htmlFor="current-password" className="mb-1 block text-xs font-semibold" style={{ color: PALETTE.ink }}>
                Kata Sandi Saat Ini
              </label>
              <input
                id="current-password"
                type="password"
                autoComplete="current-password"
                required
                value={currentPassword}
                onChange={(event) => setCurrentPassword(event.target.value)}
                placeholder="Masukkan kata sandi saat ini"
                className={fieldClass}
                style={{ color: PALETTE.ink }}
              />
            </div>

            <div>
              <label htmlFor="new-password" className="mb-1 block text-xs font-semibold" style={{ color: PALETTE.ink }}>
                Kata Sandi Baru
              </label>
              <div className="relative">
                <input
                  id="new-password"
                  type={showPassword ? 'text' : 'password'}
                  autoComplete="new-password"
                  required
                  minLength={8}
                  value={password}
                  onChange={(event) => setPassword(event.target.value)}
                  placeholder="Minimal 8 karakter"
                  className={fieldClass}
                  style={{ color: PALETTE.ink }}
                />
                <button
                  type="button"
                  onClick={() => setShowPassword((current) => !current)}
                  aria-label={showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
                  className="absolute inset-y-0 right-0 flex items-center px-3 transition hover:opacity-70"
                  style={{ color: PALETTE.slate }}
                >
                  {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                </button>
              </div>
            </div>

            <div>
              <label htmlFor="confirm-password" className="mb-1 block text-xs font-semibold" style={{ color: PALETTE.ink }}>
                Konfirmasi Kata Sandi Baru
              </label>
              <input
                id="confirm-password"
                type={showPassword ? 'text' : 'password'}
                autoComplete="new-password"
                required
                minLength={8}
                value={confirmation}
                onChange={(event) => setConfirmation(event.target.value)}
                placeholder="Ulangi kata sandi baru"
                className={fieldClass}
                style={{ color: PALETTE.ink }}
              />
            </div>

            {error && (
              <p
                role="alert"
                className="flex items-start gap-1.5 rounded-lg border px-3 py-2 text-[11px] font-medium"
                style={{ backgroundColor: PALETTE.blush, borderColor: PALETTE.coral, color: PALETTE.coral }}
              >
                <AlertCircle className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                {error}
              </p>
            )}

            <button
              type="submit"
              disabled={loading}
              className="flex min-h-11 w-full items-center justify-center gap-2 rounded-lg text-sm font-semibold text-white transition hover:brightness-95 disabled:opacity-60"
              style={{ backgroundColor: PALETTE.blue }}
            >
              {loading ? (
                <>
                  <Loader2 className="h-4 w-4 animate-spin" />
                  Menyimpan...
                </>
              ) : (
                'Simpan Kata Sandi Baru'
              )}
            </button>
          </form>

          <button
            type="button"
            onClick={logout}
            disabled={loading}
            className="mt-3 flex min-h-10 w-full items-center justify-center gap-2 rounded-lg border border-[#E3EEFA] text-xs font-medium transition hover:bg-[#F7F9FC] disabled:opacity-50"
            style={{ color: PALETTE.slate }}
          >
            <LogOut className="h-3.5 w-3.5" />
            Keluar dari akun
          </button>
        </div>
      </div>
    </div>
  );
};
