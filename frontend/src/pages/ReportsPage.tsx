import React, { useState, useEffect } from 'react';
import api from '../services/api';
import { FileSpreadsheet, Download, FileText, Loader2 } from 'lucide-react';
import { Team } from '../types';

export const ReportsPage: React.FC = () => {
  const [month, setMonth] = useState<string>(() => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
  });
  const [year, setYear] = useState<string>(() => String(new Date().getFullYear()));
  const [teams, setTeams] = useState<Team[]>([]);
  const [selectedTeam, setSelectedTeam] = useState<string>('');

  useEffect(() => {
    const fetchTeams = async () => {
      try {
        const res = await api.get('/teams');
        setTeams(res.data.data || []);
      } catch (err) {
        console.error(err);
      }
    };
    fetchTeams();
  }, []);

  const handleDownloadWorkload = (format: 'csv' | 'xlsx' | 'pdf') => {
    const url = `/api/reports/workload?format=${format}&month=${month}${selectedTeam ? `&team_id=${selectedTeam}` : ''}`;
    window.open(url, '_blank');
  };

  const handleDownloadActivities = (format: 'csv' | 'xlsx' | 'pdf') => {
    const url = `/api/reports/activities?format=${format}&year=${year}${selectedTeam ? `&team_id=${selectedTeam}` : ''}`;
    window.open(url, '_blank');
  };

  return (
    <div className="space-y-6 max-w-5xl mx-auto">
      <div>
        <h1 className="text-lg sm:text-xl font-bold text-slate-900">Laporan & Ekspor Data Statistik</h1>
        <p className="text-xs sm:text-sm text-slate-500">
          Unduh dokumen rekapitulasi beban kerja dan rekap kegiatan statistik dalam format resmi Excel (.xlsx), CSV, atau PDF
        </p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
        {/* Card 1: Laporan Beban Kerja */}
        <div className="bg-white p-5 sm:p-6 rounded-xl border border-slate-200 shadow-xs flex flex-col justify-between space-y-5">
          <div>
            <div className="w-10 h-10 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center mb-3">
              <FileSpreadsheet className="w-5 h-5" />
            </div>
            <h2 className="text-base font-bold text-slate-900">Rekapitulasi Beban Kerja Pegawai</h2>
            <p className="text-xs text-slate-500 mt-1 leading-relaxed">
              Memuat daftar nama, NIP, tim, total poin bulan bersangkutan, persentase ambang batas, dan status beban kerja.
            </p>

            <div className="space-y-3 mt-4">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">Periode Bulan</label>
                <input
                  type="month"
                  value={month}
                  onChange={(e) => setMonth(e.target.value)}
                  className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 bg-slate-50 focus:ring-2 focus:ring-sky-600 min-h-[40px]"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">Filter Tim Kerja (Opsional)</label>
                <select
                  value={selectedTeam}
                  onChange={(e) => setSelectedTeam(e.target.value)}
                  className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 bg-slate-50 focus:ring-2 focus:ring-sky-600 min-h-[40px]"
                >
                  <option value="">Semua Tim Kerja</option>
                  {teams.map((t) => (
                    <option key={t.id} value={t.id}>{t.nama}</option>
                  ))}
                </select>
              </div>
            </div>
          </div>

          <div className="flex flex-wrap gap-2 pt-3 border-t border-slate-100">
            <button
              onClick={() => handleDownloadWorkload('xlsx')}
              className="flex-1 min-w-[120px] flex items-center justify-center gap-1.5 py-2.5 px-3 text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition min-h-[44px]"
            >
              <Download className="w-4 h-4" /> Unduh XLSX
            </button>
            <button
              onClick={() => handleDownloadWorkload('csv')}
              className="flex-1 min-w-[90px] flex items-center justify-center gap-1.5 py-2.5 px-3 text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg transition min-h-[44px]"
            >
              CSV
            </button>
            <button
              onClick={() => handleDownloadWorkload('pdf')}
              className="flex-1 min-w-[100px] flex items-center justify-center gap-1.5 py-2.5 px-3 text-xs font-semibold bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 rounded-lg transition min-h-[44px]"
            >
              <FileText className="w-4 h-4" /> Cetak PDF
            </button>
          </div>
        </div>

        {/* Card 2: Laporan Daftar Kegiatan */}
        <div className="bg-white p-5 sm:p-6 rounded-xl border border-slate-200 shadow-xs flex flex-col justify-between space-y-5">
          <div>
            <div className="w-10 h-10 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center mb-3">
              <FileSpreadsheet className="w-5 h-5" />
            </div>
            <h2 className="text-base font-bold text-slate-900">Rekapitulasi Kegiatan Statistik</h2>
            <p className="text-xs text-slate-500 mt-1 leading-relaxed">
              Daftar kegiatan tahunan, tim penanggung jawab, jadwal pelaksanaan, bobot, jumlah petugas, dan status penyelesaian.
            </p>

            <div className="space-y-3 mt-4">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">Tahun Anggaran</label>
                <input
                  type="number"
                  min="2020"
                  max="2030"
                  value={year}
                  onChange={(e) => setYear(e.target.value)}
                  className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 bg-slate-50 focus:ring-2 focus:ring-sky-600 min-h-[40px]"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">Filter Tim Kerja (Opsional)</label>
                <select
                  value={selectedTeam}
                  onChange={(e) => setSelectedTeam(e.target.value)}
                  className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 bg-slate-50 focus:ring-2 focus:ring-sky-600 min-h-[40px]"
                >
                  <option value="">Semua Tim Kerja</option>
                  {teams.map((t) => (
                    <option key={t.id} value={t.id}>{t.nama}</option>
                  ))}
                </select>
              </div>
            </div>
          </div>

          <div className="flex flex-wrap gap-2 pt-3 border-t border-slate-100">
            <button
              onClick={() => handleDownloadActivities('xlsx')}
              className="flex-1 min-w-[120px] flex items-center justify-center gap-1.5 py-2.5 px-3 text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition min-h-[44px]"
            >
              <Download className="w-4 h-4" /> Unduh XLSX
            </button>
            <button
              onClick={() => handleDownloadActivities('csv')}
              className="flex-1 min-w-[90px] flex items-center justify-center gap-1.5 py-2.5 px-3 text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg transition min-h-[44px]"
            >
              CSV
            </button>
            <button
              onClick={() => handleDownloadActivities('pdf')}
              className="flex-1 min-w-[100px] flex items-center justify-center gap-1.5 py-2.5 px-3 text-xs font-semibold bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 rounded-lg transition min-h-[44px]"
            >
              <FileText className="w-4 h-4" /> Cetak PDF
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};
