import React, { useEffect, useState } from 'react';
import api from '../services/api';
import { useAuth } from '../context/AuthContext';
import {
  Calendar,
  AlertTriangle,
  ClipboardList,
  Users,
  Clock,
  ArrowRight,
  TrendingUp,
  CalendarClock,
  CheckCircle2,
  Briefcase,
  MessageSquareWarning,
} from 'lucide-react';
import { Link } from 'react-router-dom';

export const DashboardPage: React.FC = () => {
  const { user } = useAuth();
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchDashboard = async () => {
      try {
        const res = await api.get('/dashboard');
        setData(res.data.data);
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchDashboard();
  }, []);

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-sky-600"></div>
      </div>
    );
  }

  const personal = data?.beban_kerja_pribadi;
  const stats = data?.statistik;
  const assignments = data?.penugasan_aktif || [];
  const teamLed = data?.tim_dipimpin;

  return (
    <div className="space-y-6 max-w-7xl mx-auto">
      {/* Header Sambutan: Responsif Flex-col ke Flex-row */}
      <div className="bg-white p-4 sm:p-6 rounded-xl border border-slate-200 shadow-xs flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h1 className="text-lg sm:text-xl font-bold text-slate-800">
            Selamat Datang, {user?.nama}
          </h1>
          <p className="text-xs sm:text-sm text-slate-500 mt-1">
            Jabatan: <span className="font-medium text-slate-700">{user?.jabatan || 'Aparatur BPS'}</span> | Peran:{' '}
            <span className="font-semibold text-sky-700 capitalize">{user?.peran}</span>
          </p>
        </div>
        <div className="flex items-center gap-2 self-start sm:self-auto">
          <span className="text-xs bg-slate-100 text-slate-600 px-3 py-1.5 rounded-lg border border-slate-200 font-medium whitespace-nowrap">
            Periode: {data?.periode?.bulan}
          </span>
        </div>
      </div>

      {/* Widget Beban Kerja Pribadi: Grid 1 Col (mobile) -> 2 Col (tablet) -> 4 Col (desktop) */}
      <div className="bg-white p-4 sm:p-6 rounded-xl border border-slate-200 shadow-xs">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
          <h2 className="text-sm sm:text-base font-bold text-slate-800 flex items-center gap-2">
            <TrendingUp className="w-5 h-5 text-sky-600 shrink-0" />
            Beban Kerja Pribadi Bulan Ini
          </h2>
          <span
            className={`text-xs px-2.5 py-1 rounded-full font-semibold self-start sm:self-auto ${
              personal?.indikator === 'aman'
                ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                : personal?.indikator === 'hampir_penuh'
                ? 'bg-amber-50 text-amber-700 border border-amber-200'
                : 'bg-rose-50 text-rose-700 border border-rose-200'
            }`}
          >
            {personal?.indikator === 'aman'
              ? 'Aman (<80%)'
              : personal?.indikator === 'hampir_penuh'
              ? 'Hampir Penuh (80-99%)'
              : 'Melebihi Batas (≥100%)'}
          </span>
        </div>

        <div className="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4 mb-4">
          <div className="bg-slate-50 p-3 sm:p-4 rounded-lg border border-slate-100">
            <p className="text-[11px] sm:text-xs text-slate-500 font-medium">Total Poin Beban</p>
            <p className="text-xl sm:text-2xl font-bold text-slate-900 mt-1">
              {personal?.poin} <span className="text-xs font-normal text-slate-500">pt</span>
            </p>
          </div>
          <div className="bg-slate-50 p-3 sm:p-4 rounded-lg border border-slate-100">
            <p className="text-[11px] sm:text-xs text-slate-500 font-medium">% Terhadap Ambang</p>
            <p className="text-xl sm:text-2xl font-bold text-slate-900 mt-1">{personal?.persentase}%</p>
          </div>
          <div className="bg-slate-50 p-3 sm:p-4 rounded-lg border border-slate-100">
            <p className="text-[11px] sm:text-xs text-slate-500 font-medium">Jumlah Penugasan</p>
            <p className="text-xl sm:text-2xl font-bold text-slate-900 mt-1">
              {personal?.jumlah_penugasan} <span className="text-xs font-normal text-slate-500">tugas</span>
            </p>
          </div>
          <div className="bg-slate-50 p-3 sm:p-4 rounded-lg border border-slate-100">
            <p className="text-[11px] sm:text-xs text-slate-500 font-medium">Puncak Tugas Bersamaan</p>
            <p className="text-xl sm:text-2xl font-bold text-slate-900 mt-1">
              {personal?.penugasan_bersamaan_maks} <span className="text-xs font-normal text-slate-500">tugas/hari</span>
            </p>
          </div>
        </div>

        {/* Progress Bar */}
        <div className="w-full bg-slate-100 rounded-full h-3 overflow-hidden">
          <div
            className={`h-full transition-all duration-500 ${
              personal?.persentase >= 100
                ? 'bg-rose-500'
                : personal?.persentase >= 80
                ? 'bg-amber-500'
                : 'bg-sky-500'
            }`}
            style={{ width: `${Math.min(personal?.persentase || 0, 100)}%` }}
          />
        </div>
      </div>

      {/* Statistik Administrator & Pimpinan */}
      {stats && (
        <div>
          <h2 className="text-sm sm:text-base font-bold text-slate-800 mb-3">Statistik Kantor BPS Palembang</h2>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4">
            <div className="bg-white p-4 sm:p-5 rounded-xl border border-slate-200 shadow-xs">
              <div className="flex items-center gap-3">
                <div className="p-2 sm:p-2.5 rounded-lg bg-sky-50 text-sky-600 shrink-0">
                  <ClipboardList className="w-5 h-5" />
                </div>
                <div className="min-w-0">
                  <p className="text-[11px] sm:text-xs text-slate-500 font-medium truncate">Total Kegiatan</p>
                  <p className="text-lg sm:text-xl font-bold text-slate-900">{stats.total_kegiatan}</p>
                </div>
              </div>
            </div>

            <div className="bg-white p-4 sm:p-5 rounded-xl border border-slate-200 shadow-xs">
              <div className="flex items-center gap-3">
                <div className="p-2 sm:p-2.5 rounded-lg bg-emerald-50 text-emerald-600 shrink-0">
                  <Clock className="w-5 h-5" />
                </div>
                <div className="min-w-0">
                  <p className="text-[11px] sm:text-xs text-slate-500 font-medium truncate">Sedang Berjalan</p>
                  <p className="text-lg sm:text-xl font-bold text-slate-900">{stats.kegiatan_berjalan}</p>
                </div>
              </div>
            </div>

            <div className="bg-white p-4 sm:p-5 rounded-xl border border-slate-200 shadow-xs">
              <div className="flex items-center gap-3">
                <div className="p-2 sm:p-2.5 rounded-lg bg-indigo-50 text-indigo-600 shrink-0">
                  <CalendarClock className="w-5 h-5" />
                </div>
                <div className="min-w-0">
                  <p className="text-[11px] sm:text-xs text-slate-500 font-medium truncate">Kegiatan Mendatang</p>
                  <p className="text-lg sm:text-xl font-bold text-slate-900">{stats.kegiatan_mendatang ?? 0}</p>
                </div>
              </div>
            </div>

            <div className="bg-white p-4 sm:p-5 rounded-xl border border-slate-200 shadow-xs">
              <div className="flex items-center gap-3">
                <div className="p-2 sm:p-2.5 rounded-lg bg-teal-50 text-teal-600 shrink-0">
                  <CheckCircle2 className="w-5 h-5" />
                </div>
                <div className="min-w-0">
                  <p className="text-[11px] sm:text-xs text-slate-500 font-medium truncate">Kegiatan Selesai</p>
                  <p className="text-lg sm:text-xl font-bold text-slate-900">{stats.kegiatan_selesai ?? 0}</p>
                </div>
              </div>
            </div>

            <div className="bg-white p-4 sm:p-5 rounded-xl border border-slate-200 shadow-xs">
              <div className="flex items-center gap-3">
                <div className="p-2 sm:p-2.5 rounded-lg bg-purple-50 text-purple-600 shrink-0">
                  <Users className="w-5 h-5" />
                </div>
                <div className="min-w-0">
                  <p className="text-[11px] sm:text-xs text-slate-500 font-medium truncate">Total Pegawai</p>
                  <p className="text-lg sm:text-xl font-bold text-slate-900">{stats.total_pegawai}</p>
                </div>
              </div>
            </div>

            <div className="bg-white p-4 sm:p-5 rounded-xl border border-slate-200 shadow-xs">
              <div className="flex items-center gap-3">
                <div className="p-2 sm:p-2.5 rounded-lg bg-cyan-50 text-cyan-600 shrink-0">
                  <Briefcase className="w-5 h-5" />
                </div>
                <div className="min-w-0">
                  <p className="text-[11px] sm:text-xs text-slate-500 font-medium truncate">Total Tim</p>
                  <p className="text-lg sm:text-xl font-bold text-slate-900">{stats.total_tim}</p>
                </div>
              </div>
            </div>

            <div className="bg-white p-4 sm:p-5 rounded-xl border border-slate-200 shadow-xs">
              <div className="flex items-center gap-3">
                <div className="p-2 sm:p-2.5 rounded-lg bg-amber-50 text-amber-600 shrink-0">
                  <AlertTriangle className="w-5 h-5" />
                </div>
                <div className="min-w-0">
                  <p className="text-[11px] sm:text-xs text-slate-500 font-medium truncate">Peringatan Beban</p>
                  <p className="text-lg sm:text-xl font-bold text-slate-900">{stats.total_peringatan_beban}</p>
                </div>
              </div>
            </div>

            <div className="bg-white p-4 sm:p-5 rounded-xl border border-slate-200 shadow-xs">
              <div className="flex items-center gap-3">
                <div className="p-2 sm:p-2.5 rounded-lg bg-rose-50 text-rose-600 shrink-0">
                  <MessageSquareWarning className="w-5 h-5" />
                </div>
                <div className="min-w-0">
                  <p className="text-[11px] sm:text-xs text-slate-500 font-medium truncate">Kendala Belum Selesai</p>
                  <p className="text-lg sm:text-xl font-bold text-slate-900">{stats.kendala_belum_selesai ?? 0}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Distribusi kegiatan per tim + peringatan beban (Admin/Pimpinan) */}
      {stats && (
        <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
          <div className="bg-white p-4 sm:p-5 rounded-xl border border-slate-200 shadow-xs">
            <h2 className="text-sm font-bold text-slate-800 mb-3">Distribusi Kegiatan per Tim</h2>
            {(() => {
              const distribution = data?.distribusi_tim || [];
              if (distribution.length === 0) {
                return <p className="py-6 text-center text-xs text-slate-500">Belum ada data tim.</p>;
              }
              const max = Math.max(...distribution.map((item: any) => item.jumlah), 1);
              return (
                <div className="space-y-2">
                  {distribution.map((item: any) => (
                    <div key={item.id}>
                      <div className="mb-0.5 flex items-center justify-between gap-2 text-xs">
                        <span className="truncate font-medium text-slate-700">{item.nama}</span>
                        <span className="shrink-0 text-slate-500">{item.jumlah} kegiatan</span>
                      </div>
                      <div className="h-2 overflow-hidden rounded-full bg-slate-100">
                        <div
                          className="h-full rounded-full bg-sky-500"
                          style={{ width: `${(item.jumlah / max) * 100}%` }}
                        />
                      </div>
                    </div>
                  ))}
                </div>
              );
            })()}
          </div>

          <div className="bg-white p-4 sm:p-5 rounded-xl border border-slate-200 shadow-xs">
            <div className="mb-3 flex items-center justify-between gap-2">
              <h2 className="text-sm font-bold text-slate-800">Pegawai Beban Tinggi</h2>
              <Link to="/workload" className="text-xs font-semibold text-sky-600 hover:text-sky-700">
                Lihat semua
              </Link>
            </div>
            {(() => {
              const warnings = data?.peringatan_terbaru || [];
              if (warnings.length === 0) {
                return <p className="py-6 text-center text-xs text-slate-500">Tidak ada peringatan beban bulan ini.</p>;
              }
              return (
                <ul className="space-y-2">
                  {warnings.map((warning: any, index: number) => (
                    <li
                      key={`${warning.pegawai_id || index}-${warning.jenis || 'warning'}`}
                      className="rounded-lg border border-amber-100 bg-amber-50/60 px-3 py-2"
                    >
                      <p className="text-xs font-semibold text-amber-900">
                        {warning.pegawai_nama || warning.user?.nama || 'Pegawai'}
                      </p>
                      <p className="mt-0.5 text-[11px] text-amber-800">{warning.pesan}</p>
                    </li>
                  ))}
                </ul>
              );
            })()}
          </div>
        </div>
      )}

      {/* Ringkasan Tim yang Dipimpin (Ketua Tim) */}
      {teamLed && (
        <div className="bg-sky-50/60 p-4 sm:p-5 rounded-xl border border-sky-100">
          <h2 className="text-xs sm:text-sm font-bold text-sky-900 uppercase tracking-wider mb-2">
            Ringkasan Tim yang Anda Pimpin ({teamLed.daftar_tim?.join(', ')})
          </h2>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div className="bg-white p-3 rounded-lg border border-sky-100">
              <p className="text-xs text-slate-500">Jumlah Anggota</p>
              <p className="text-base sm:text-lg font-bold text-slate-800">{teamLed.total_anggota} orang</p>
            </div>
            <div className="bg-white p-3 rounded-lg border border-sky-100">
              <p className="text-xs text-slate-500">Kegiatan Berjalan</p>
              <p className="text-base sm:text-lg font-bold text-slate-800">{teamLed.kegiatan_berjalan} kegiatan</p>
            </div>
            <div className="bg-white p-3 rounded-lg border border-sky-100">
              <p className="text-xs text-slate-500">Kendala Aktif</p>
              <p className="text-base sm:text-lg font-bold text-slate-800">{teamLed.kendala_tim} kendala</p>
            </div>
          </div>
        </div>
      )}

      {/* Penugasan Aktif: Tabel Responsif + Card List pada Layar Mobile (<640px) */}
      <div className="bg-white p-4 sm:p-6 rounded-xl border border-slate-200 shadow-xs">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-sm sm:text-base font-bold text-slate-800 flex items-center gap-2">
            <Calendar className="w-5 h-5 text-sky-600 shrink-0" />
            Penugasan Aktif Anda
          </h2>
          <Link
            to="/activities"
            className="text-xs font-semibold text-sky-600 hover:text-sky-700 flex items-center gap-1 min-h-[44px] min-w-[44px]"
          >
            Lihat Semua <ArrowRight className="w-3.5 h-3.5" />
          </Link>
        </div>

        {assignments.length === 0 ? (
          <div className="py-8 text-center text-slate-500 text-sm">
            Tidak ada penugasan aktif untuk saat ini.
          </div>
        ) : (
          <>
            {/* Tampilan Desktop & Tablet: Tabel */}
            <div className="hidden sm:block overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead className="bg-slate-50 text-slate-600 text-xs font-semibold border-b border-slate-200">
                  <tr>
                    <th className="py-3 px-4">Nama Kegiatan</th>
                    <th className="py-3 px-4">Tim</th>
                    <th className="py-3 px-4">Peran</th>
                    <th className="py-3 px-4">Periode</th>
                    <th className="py-3 px-4">Beban</th>
                    <th className="py-3 px-4">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {assignments.map((item: any) => (
                    <tr key={item.id} className="hover:bg-slate-50/80 transition">
                      <td className="py-3 px-4 font-medium text-slate-900">{item.kegiatan}</td>
                      <td className="py-3 px-4 text-slate-600">{item.tim}</td>
                      <td className="py-3 px-4 text-slate-600">{item.peran}</td>
                      <td className="py-3 px-4 text-slate-500 text-xs whitespace-nowrap">
                        {item.mulai} s.d. {item.selesai}
                      </td>
                      <td className="py-3 px-4 font-semibold text-slate-700 whitespace-nowrap">{item.poin} pt</td>
                      <td className="py-3 px-4">
                        <span className="text-xs px-2 py-0.5 rounded-full font-medium bg-sky-50 text-sky-700 border border-sky-100 whitespace-nowrap">
                          {item.status_kegiatan}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Tampilan Mobile (<640px): Card List Stack */}
            <div className="sm:hidden space-y-3">
              {assignments.map((item: any) => (
                <div key={item.id} className="p-3.5 rounded-lg border border-slate-200 bg-slate-50/50 space-y-2">
                  <div className="flex justify-between items-start gap-2">
                    <p className="font-semibold text-slate-900 text-sm">{item.kegiatan}</p>
                    <span className="text-[11px] px-2 py-0.5 rounded-full font-medium bg-sky-50 text-sky-700 border border-sky-100 shrink-0">
                      {item.status_kegiatan}
                    </span>
                  </div>
                  <div className="text-xs text-slate-600 space-y-1">
                    <p><span className="text-slate-400">Tim:</span> {item.tim} | <span className="text-slate-400">Peran:</span> {item.peran}</p>
                    <p><span className="text-slate-400">Periode:</span> {item.mulai} s.d. {item.selesai}</p>
                  </div>
                  <div className="pt-2 border-t border-slate-200/60 flex justify-between items-center text-xs">
                    <span className="text-slate-500 font-medium">Beban Tugas:</span>
                    <span className="font-bold text-sky-700">{item.poin} poin</span>
                  </div>
                </div>
              ))}
            </div>
          </>
        )}
      </div>
    </div>
  );
};
