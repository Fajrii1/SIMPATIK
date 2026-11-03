import React, { useState, useEffect } from 'react';
import api from '../services/api';
import { Settings as SettingsIcon, Save, Loader2, Check, AlertTriangle } from 'lucide-react';
import { getApiErrorMessage } from '../services/errors';

export const SettingsPage: React.FC = () => {
  const [settings, setSettings] = useState<any>({
    ambang_beban_bulanan: 100,
    maks_penugasan_bersamaan: 3,
    maks_kegiatan_besar_bersamaan: 3,
    pegawai_boleh_isi_kendala: false,
  });
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [success, setSuccess] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    const fetchSettings = async () => {
      try {
        const res = await api.get('/settings');
        // Backend returns settings
        if (res.data.data) {
          setSettings(res.data.data);
        }
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchSettings();
  }, []);

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setSuccess(false);
    setError('');
    try {
      await api.put('/settings', settings);
      setSuccess(true);
      setTimeout(() => setSuccess(false), 3000);
    } catch (err: unknown) {
      setError(getApiErrorMessage(err, 'Gagal menyimpan pengaturan aplikasi.'));
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="py-24 flex justify-center">
        <Loader2 className="w-8 h-8 animate-spin text-sky-600" />
      </div>
    );
  }

  return (
    <div className="space-y-6 max-w-2xl">
      <div>
        <h1 className="text-xl font-bold text-slate-900">Pengaturan Sistem Penjadwalan</h1>
        <p className="text-sm text-slate-500">
          Konfigurasi ambang batas perhitungan beban kerja, aturan benturan jadwal, dan kebijakan operasional BPS
        </p>
      </div>

      <form onSubmit={handleSave} className="bg-white p-6 rounded-xl border border-slate-200 shadow-xs space-y-5">
        {success && (
          <div className="p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-emerald-700 text-xs font-semibold flex items-center gap-2">
            <Check className="w-4 h-4" /> Pengaturan berhasil disimpan dan langsung diterapkan ke perhitungan sistem.
          </div>
        )}

        {error && (
          <div role="alert" className="p-3 bg-rose-50 border border-rose-200 rounded-lg text-rose-700 text-xs font-semibold flex items-start gap-2">
            <AlertTriangle className="w-4 h-4 shrink-0" />
            <span>{error}</span>
          </div>
        )}

        <div>
          <label className="block text-xs font-bold text-slate-700 mb-1">
            Ambang Beban Bulanan Standar (Poin)
          </label>
          <p className="text-[11px] text-slate-400 mb-2">
            Nilai acuan beban kerja 100% per pegawai per bulan. Beban di atas nilai ini akan diberi indikator 'melebihi'.
          </p>
          <input
            type="number"
            min="10"
            max="1000"
            required
            value={settings.ambang_beban_bulanan}
            onChange={(e) =>
              setSettings({ ...settings, ambang_beban_bulanan: parseFloat(e.target.value) || 100 })
            }
            className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600"
          />
        </div>

        <div>
          <label className="block text-xs font-bold text-slate-700 mb-1">
            Batas Maksimal Penugasan Bersamaan (Harian)
          </label>
          <p className="text-[11px] text-slate-400 mb-2">
            Jumlah maksimum penugasan yang boleh dijalankan seorang pegawai pada hari yang sama sebelum memicu peringatan benturan.
          </p>
          <input
            type="number"
            min="1"
            max="10"
            required
            value={settings.maks_penugasan_bersamaan}
            onChange={(e) =>
              setSettings({ ...settings, maks_penugasan_bersamaan: parseInt(e.target.value) || 3 })
            }
            className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600"
          />
        </div>

        <div>
          <label className="block text-xs font-bold text-slate-700 mb-1">
            Batas Maksimal Kegiatan Besar Bersamaan
          </label>
          <p className="text-[11px] text-slate-400 mb-2">
            Batas maksimum kegiatan dengan kategori besar yang boleh dipegang pegawai pada tanggal yang beririsan.
          </p>
          <input
            type="number"
            min="1"
            max="5"
            required
            value={settings.maks_kegiatan_besar_bersamaan}
            onChange={(e) =>
              setSettings({ ...settings, maks_kegiatan_besar_bersamaan: parseInt(e.target.value) || 3 })
            }
            className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600"
          />
        </div>

        <div className="pt-2 border-t border-slate-100 flex items-center justify-between">
          <div>
            <p className="text-xs font-bold text-slate-700">Izin Pegawai Mengisi Kendala Mingguan</p>
            <p className="text-[11px] text-slate-400">
              Jika aktif, seluruh staf dapat melaporkan kendala. Jika dinonaktifkan, hanya Ketua Tim & Admin yang dapat melapor.
            </p>
          </div>
          <input
            type="checkbox"
            checked={settings.pegawai_boleh_isi_kendala}
            onChange={(e) =>
              setSettings({ ...settings, pegawai_boleh_isi_kendala: e.target.checked })
            }
            className="w-5 h-5 text-sky-600 rounded border-slate-300 focus:ring-sky-500"
          />
        </div>

        <div className="pt-4 border-t border-slate-100 flex justify-end">
          <button
            type="submit"
            disabled={saving}
            className="flex items-center gap-2 bg-sky-600 hover:bg-sky-700 text-white px-5 py-2.5 rounded-lg text-sm font-semibold shadow-xs disabled:opacity-50 transition"
          >
            {saving ? <Loader2 className="w-4 h-4 animate-spin" /> : <Save className="w-4 h-4" />}
            {saving ? 'Menyimpan...' : 'Simpan Pengaturan'}
          </button>
        </div>
      </form>
    </div>
  );
};

