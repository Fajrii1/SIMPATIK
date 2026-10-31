import React, { useState, useEffect } from 'react';
import api from '../services/api';
import { Shield, Loader2, Search } from 'lucide-react';
import { AuditLogItem } from '../types';

export const AuditLogsPage: React.FC = () => {
  const [logs, setLogs] = useState<AuditLogItem[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchLogs = async () => {
    setLoading(true);
    try {
      const res = await api.get('/audit-logs');
      setLogs(res.data.data);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchLogs();
  }, []);

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-xl font-bold text-slate-900">Audit Log & Rekam Jejak Sistem</h1>
        <p className="text-sm text-slate-500">
          Riwayat mutasi data kegiatan, penugasan, akun pengguna, dan pengaturan sistem
        </p>
      </div>

      <div className="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
        {loading ? (
          <div className="py-16 flex justify-center">
            <Loader2 className="w-8 h-8 animate-spin text-sky-600" />
          </div>
        ) : logs.length === 0 ? (
          <div className="py-16 text-center text-slate-500 text-sm">
            Belum ada catatan aktivitas audit.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-slate-50 text-slate-600 text-xs font-semibold border-b border-slate-200">
                <tr>
                  <th className="py-3 px-4">Waktu</th>
                  <th className="py-3 px-4">Aktor Pelaksana</th>
                  <th className="py-3 px-4">Aksi</th>
                  <th className="py-3 px-4">Objek Terkait</th>
                  <th className="py-3 px-4">Detail Perubahan</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 font-mono text-xs">
                {logs.map((log) => (
                  <tr key={log.id} className="hover:bg-slate-50/70 transition">
                    <td className="py-3 px-4 text-slate-500 whitespace-nowrap">
                      {new Date(log.created_at).toLocaleString('id-ID')}
                    </td>
                    <td className="py-3 px-4 font-sans font-semibold text-slate-800">
                      {log.user?.nama || 'Sistem'}
                    </td>
                    <td className="py-3 px-4">
                      <span
                        className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase ${
                          log.aksi === 'create'
                            ? 'bg-emerald-100 text-emerald-800'
                            : log.aksi === 'update' || log.aksi === 'update_status'
                            ? 'bg-sky-100 text-sky-800'
                            : 'bg-rose-100 text-rose-800'
                        }`}
                      >
                        {log.aksi}
                      </span>
                    </td>
                    <td className="py-3 px-4 text-slate-700">
                      {log.objek} #{log.objek_id}
                    </td>
                    <td className="py-3 px-4 text-slate-500 max-w-xs truncate">
                      {log.sesudah ? JSON.stringify(log.sesudah) : 'Dihapus/Null'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
};

