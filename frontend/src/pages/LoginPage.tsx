import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { Mail, Eye, EyeOff, ArrowRight, AlertCircle, Loader2 } from 'lucide-react';

export const LoginPage: React.FC = () => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [rememberMe, setRememberMe] = useState(true);
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const { login } = useAuth();
  const navigate = useNavigate();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setLoading(true);

    try {
      await login(email, password);
      navigate('/');
    } catch (err: any) {
      if (err.response?.data?.message) {
        setError(err.response.data.message);
      } else {
        setError('Email atau kata sandi salah');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen w-full flex flex-col lg:flex-row bg-[#f8fafc] font-sans">
      {/* Kolom Kiri: Form Login */}
      <div className="w-full lg:w-[48%] min-h-screen flex flex-col justify-between p-8 sm:p-12 lg:p-16 relative bg-white lg:bg-[#fafbfc]">
        {/* Subtle grid pattern background */}
        <div
          className="absolute inset-0 pointer-events-none opacity-[0.35]"
          style={{
            backgroundImage: `radial-gradient(#94a3b8 1px, transparent 1px)`,
            backgroundSize: '24px 24px',
          }}
        />

        {/* Header / Brand Logo */}
        <div className="relative z-10">
          <div className="flex items-center gap-3">
            <div>
              <img 
                src="/LogoBPS.png"
                alt="Logo BPS" 
                className="w-16 h-14 object-contain flex-shrink-0" 
              />
            </div>
            <div>
              <h1 className="font-extrabold text-lg text-slate-900 tracking-wider leading-none">
                SIMPATIK
              </h1>
              <p className="text-[11px] font-medium text-slate-500 mt-1 leading-snug">
                Sistem Penjadwalan & Monitoring Kegiatan Statistik<br />
                <span className="text-slate-400">Badan Pusat Statistik Kota Palembang</span>
              </p>
            </div>
          </div>
        </div>

        {/* Main Content Form */}
        <div className="relative z-10 my-auto py-8 max-w-md w-full mx-auto">
          <div className="mb-8">
            <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">
              Selamat datang
            </span>
            <h2 className="text-2xl sm:text-3xl font-extrabold text-[#0f2e5a] mt-1 tracking-tight">
              Rekan BPS Kota Palembang!
            </h2>
            <p className="text-xs sm:text-sm text-slate-500 mt-2 leading-relaxed">
              Masuk untuk mengelola jadwal penugasan kegiatan dan monitoring beban kerja secara terpadu.
            </p>
          </div>

          <form onSubmit={handleSubmit} className="space-y-4">
            {/* Input Email */}
            <div>
              <label className="block text-xs font-bold text-slate-700 mb-1.5">
                Email <span className="text-rose-500">*</span>
              </label>
              <div className="relative">
                <input
                  type="email"
                  required
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="budi.santoso@bps.go.id"
                  className="w-full text-sm bg-white border border-slate-300 rounded-xl px-4 py-3 text-slate-900 placeholder:text-slate-400 focus:outline-hidden focus:ring-2 focus:ring-[#164e9a] focus:border-[#164e9a] transition shadow-2xs"
                />
                <div className="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-slate-400">
                  <Mail className="w-4 h-4 text-slate-400" />
                </div>
              </div>
              <p className="text-[11px] text-slate-400 mt-1.5">
                Gunakan akun email resmi @bps.go.id atau akun terdaftar
              </p>
            </div>

            {/* Input Kata Sandi */}
            <div>
              <label className="block text-xs font-bold text-slate-700 mb-1.5">
                Kata Sandi <span className="text-rose-500">*</span>
              </label>
              <div className="relative">
                <input
                  type={showPassword ? 'text' : 'password'}
                  required
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="••••••••"
                  className={`w-full text-sm bg-white border rounded-xl px-4 py-3 text-slate-900 placeholder:text-slate-400 focus:outline-hidden focus:ring-2 transition shadow-2xs pr-11 ${
                    error
                      ? 'border-rose-400 focus:ring-rose-500 focus:border-rose-500'
                      : 'border-slate-300 focus:ring-[#164e9a] focus:border-[#164e9a]'
                  }`}
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 transition"
                  tabIndex={-1}
                >
                  {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                </button>
              </div>

              {/* Error Alert Message */}
              {error && (
                <div className="flex items-center gap-1.5 mt-2 text-rose-600 text-xs font-semibold">
                  <AlertCircle className="w-4 h-4 shrink-0" />
                  <span>{error}</span>
                </div>
              )}
            </div>

            {/* Remember Me & Lupa Password */}
            <div className="flex items-center justify-between pt-1">
              <label className="flex items-center gap-2 cursor-pointer select-none">
                <input
                  type="checkbox"
                  checked={rememberMe}
                  onChange={(e) => setRememberMe(e.target.checked)}
                  className="w-4 h-4 text-[#164e9a] rounded border-slate-300 focus:ring-[#164e9a]"
                />
                <span className="text-xs font-medium text-slate-600">Ingat saya</span>
              </label>

              <button
                type="button"
                onClick={() => alert('Silakan hubungi administrator SIMPATIK BPS Kota Palembang untuk reset kata sandi akun Anda.')}
                className="text-xs font-semibold text-slate-500 hover:text-[#164e9a] transition"
              >
                Lupa kata sandi?
              </button>
            </div>

            {/* Tombol Submit */}
            <div className="pt-2">
              <button
                type="submit"
                disabled={loading}
                className="w-full py-3.5 px-4 bg-[#164e9a] hover:bg-[#123e7b] text-white font-bold rounded-xl shadow-md shadow-[#164e9a]/20 flex items-center justify-center gap-2 text-sm disabled:opacity-60 transition active:scale-[0.99]"
              >
                {loading ? (
                  <>
                    <Loader2 className="w-4 h-4 animate-spin" />
                    <span>Memverifikasi...</span>
                  </>
                ) : (
                  <>
                    <span>Masuk</span>
                    <ArrowRight className="w-4 h-4" />
                  </>
                )}
              </button>
            </div>
          </form>

          {/* Hubungi Admin */}
          <div className="mt-8 text-center text-xs text-slate-500">
            Belum punya akun?{' '}
            <span
              onClick={() => alert('Pendaftaran akun dikelola langsung oleh Bagian Umum / Admin Sistem BPS Kota Palembang.')}
              className="font-bold text-slate-800 hover:text-[#164e9a] cursor-pointer transition underline underline-offset-2"
            >
              Hubungi admin aplikasi
            </span>
            .
          </div>
        </div>

        {/* Footer Kiri */}
        <div className="relative z-10 pt-4 border-t border-slate-200/60 flex items-center justify-between text-[11px] text-slate-400">
          <span>Aplikasi Penjadwalan Statistik • BPS Kota Palembang</span>
          <span className="font-mono">v1.0.0</span>
        </div>
      </div>

      {/* Kolom Kanan: Ilustrasi & Visual Hero Banner */}
      <div className="hidden lg:flex lg:w-[52%] bg-gradient-to-br from-[#103b7b] via-[#124d9c] to-[#0c2f64] text-white flex-col justify-between p-12 lg:p-16 relative overflow-hidden">
        {/* Dekorasi Cahaya & Aksen Lingkaran di Latar */}
        <div className="absolute top-0 right-0 w-96 h-96 bg-sky-400/10 rounded-full blur-3xl pointer-events-none" />
        <div className="absolute -bottom-20 -left-20 w-96 h-96 bg-blue-600/20 rounded-full blur-3xl pointer-events-none" />

        {/* Top Header Kanan: Status Tag & Aksen Indikator */}
        <div className="flex items-center justify-between relative z-10">
          <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/10 backdrop-blur-md border border-white/15 text-xs text-slate-100 font-medium shadow-xs">
            <span className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse" />
            <span>Sistem Manajemen Penjadwalan & Beban Kerja</span>
          </div>

          <div className="w-3 h-3 rounded-full bg-emerald-400 ring-4 ring-emerald-400/20" />
        </div>

        {/* Ilustrasi Modern Tengah (Kombinasi Tim Pegawai BPS yang Bekerja di Meja) */}
        <div className="relative z-10 my-auto flex flex-col items-center justify-center text-center py-6">
          <div className="relative w-80 h-64 flex items-center justify-center">
            {/* Soft Glow Disc */}
            <div className="absolute w-60 h-60 rounded-full bg-white/5 border border-white/10 backdrop-blur-xs flex items-center justify-center">
              {/* Balon Bicara Dialog */}
              <div className="absolute -top-3 w-10 h-7 bg-white rounded-full flex items-center justify-center shadow-lg gap-0.5">
                <span className="w-1.5 h-1.5 rounded-full bg-slate-300" />
                <span className="w-1.5 h-1.5 rounded-full bg-slate-300" />
                <span className="w-1.5 h-1.5 rounded-full bg-slate-300" />
              </div>
            </div>

            {/* Ilustrasi Vektor SVG Mewakili Dua Pegawai BPS (Laki-laki & Wanita Berhijab) Berdiskusi di Meja Kerja */}
            <svg viewBox="0 0 320 220" className="w-full h-full relative z-10 drop-shadow-xl" fill="none">
              {/* Meja Kerja */}
              <path d="M40 160h240c4 0 6 3 4 6l-8 12c-1 2-3 2-5 2H49c-2 0-4-0-5-2l-8-12c-2-3 0-6 4-6z" fill="#ffffff" />
              {/* Kaki Meja */}
              <rect x="70" y="180" width="8" height="32" rx="3" fill="#cbd5e1" />
              <rect x="242" y="180" width="8" height="32" rx="3" fill="#cbd5e1" />

              {/* Pegawai Kiri (Pria / Statistisi Jas Biru BPS) */}
              <g transform="translate(68, 65)">
                {/* Kepala & Rambut */}
                <circle cx="32" cy="22" r="16" fill="#fbcfe8" />
                <path d="M16 20c0-10 7-18 16-18s16 8 16 18c0 2-2 3-4 2-3-2-7-3-12-3s-9 1-12 3c-2 1-4 0-4-2z" fill="#1e293b" />
                {/* Kacamata */}
                <circle cx="27" cy="23" r="5" stroke="#1e293b" strokeWidth="2" fill="none" />
                <circle cx="37" cy="23" r="5" stroke="#1e293b" strokeWidth="2" fill="none" />
                <line x1="32" y1="23" x2="32" y2="23" stroke="#1e293b" strokeWidth="2" />
                {/* Badan Jas Biru Gelap */}
                <path d="M10 50c0-12 10-22 22-22s22 10 22 22v45H10V50z" fill="#0f172a" />
                {/* Kemeja Biru Muda & ID Card BPS */}
                <path d="M26 30l6 14 6-14" fill="#38bdf8" />
                <rect x="29" y="48" width="6" height="9" rx="1.5" fill="#f8fafc" />
                <line x1="32" y1="44" x2="32" y2="48" stroke="#38bdf8" strokeWidth="1.5" />
              </g>

              {/* Laptop BPS di Meja */}
              <g transform="translate(132, 126)">
                <rect x="0" y="8" width="50" height="26" rx="2" fill="#0284c7" />
                <rect x="3" y="11" width="44" height="20" rx="1" fill="#38bdf8" />
                <path d="M-6 34h62c2 0 3 1 2 2l-2 3H4l-2-3c-1-1 0-2 2-2z" fill="#e2e8f0" />
              </g>

              {/* Lembar Dokumen / Laporan Statistik */}
              <g transform="translate(190, 128)">
                <rect x="0" y="0" width="22" height="28" rx="2" fill="#ffffff" stroke="#e2e8f0" strokeWidth="1.5" />
                <line x1="4" y1="6" x2="18" y2="6" stroke="#0ea5e9" strokeWidth="2" strokeLinecap="round" />
                <line x1="4" y1="12" x2="16" y2="12" stroke="#94a3b8" strokeWidth="1.5" strokeLinecap="round" />
                <line x1="4" y1="17" x2="14" y2="17" stroke="#94a3b8" strokeWidth="1.5" strokeLinecap="round" />
                <path d="M12 21l3 3 5-5" stroke="#10b981" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
              </g>

              {/* Pegawai Kanan (Wanita Berhijab Hijau Toska BPS) */}
              <g transform="translate(196, 68)">
                {/* Hijab Hijau BPS */}
                <ellipse cx="32" cy="24" rx="18" ry="22" fill="#059669" />
                {/* Wajah */}
                <ellipse cx="32" cy="24" rx="11" ry="13" fill="#fde68a" />
                {/* Badan Hijab Menutup Dada */}
                <path d="M10 50c0-10 10-18 22-18s22 8 22 18v42H10V50z" fill="#047857" />
              </g>
            </svg>
          </div>

          {/* Slogan Banner di Bawah Ilustrasi */}
          <div className="mt-4 max-w-sm">
            <h3 className="text-xl font-bold tracking-tight text-white leading-snug">
              Transparan, Akuntabel, & Terintegrasi
            </h3>
            <p className="text-xs text-sky-100/80 mt-2 leading-relaxed">
              Mewujudkan penjadwalan terpadu, pemantauan beban kerja proporsional, serta eskalasi kendala statistik yang responsif bagi BPS Kota Palembang.
            </p>
          </div>
        </div>

        {/* Footer Kanan: Slider Indicator Bar */}
        <div className="relative z-10 flex items-center gap-3">
          <div className="flex items-center gap-2">
            <span className="w-8 h-2 rounded-full bg-amber-500 shadow-sm" />
            <span className="w-2 h-2 rounded-full bg-white/30" />
            <span className="w-2 h-2 rounded-full bg-white/30" />
          </div>
          <span className="text-[11px] text-white/50 font-mono">
            BPS Kota Palembang © 2026
          </span>
        </div>
      </div>
    </div>
  );
};
