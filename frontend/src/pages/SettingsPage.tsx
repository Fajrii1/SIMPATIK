import React, { useEffect, useState } from 'react';
import api from '../services/api';
import { Save, Loader2, Check, AlertTriangle, Plus, X } from 'lucide-react';
import { getApiErrorMessage } from '../services/errors';
import type { SettingsValues } from '../types';

interface SettingsForm {
  jam_kerja_per_hari: number;
  hari_libur: string[];
  maks_penugasan_bersamaan: number;
  maks_kegiatan_besar_bersamaan: number;
  pegawai_boleh_isi_kendala: boolean;
}

const DEFAULT_SETTINGS: SettingsForm = {
  jam_kerja_per_hari: 7,
  hari_libur: [],
  maks_penugasan_bersamaan: 3,
  maks_kegiatan_besar_bersamaan: 3,
  pegawai_boleh_isi_kendala: false,
};

const normalizeSettings = (raw: Record<string, unknown> = {}): SettingsForm => {
  const readNumber = (key: string, fallback: number): number => {
    const parsed = Number(raw[key]);
    return Number.isFinite(parsed) ? parsed : fallback;
  };

  const flag = raw.pegawai_boleh_isi_kendala;
  const libur = raw.hari_libur;

  return {
    jam_kerja_per_hari: readNumber('jam_kerja_per_hari', DEFAULT_SETTINGS.jam_kerja_per_hari),
    hari_libur: Array.isArray(libur) ? libur.filter((item): item is string => typeof item === 'string') : [],
    maks_penugasan_bersamaan: readNumber('maks_penugasan_bersamaan', DEFAULT_SETTINGS.maks_penugasan_bersamaan),
    maks_kegiatan_besar_bersamaan: readNumber('maks_kegiatan_besar_bersamaan', DEFAULT_SETTINGS.maks_kegiatan_besar_bersamaan),
    pegawai_boleh_isi_kendala: flag === true || flag === 'true' || flag === 1 || flag === '1',
  };
};

export const SettingsPage: React.FC = () => {
  const [settings, setSettings] = useState<SettingsForm>(DEFAULT_SETTINGS);
  const [holidayInput, setHolidayInput] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [success, setSuccess] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    const fetchSettings = async () => {
      try {
        const res = await api.get('/settings');
        if (res.data.settings && typeof res.data.settings === 'object') {
          setSettings(normalizeSettings(res.data.settings as Record<string, unknown>));
        }
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchSettings();
  }, []);

  const addHoliday = () => {
    const value = holidayInput.trim();
    if (!value || settings.hari_libur.includes(value)) {
      return;
    }

    setSettings((current) => ({
      ...current,
      hari_libur: [...current.hari_libur, value].sort(),
    }));
    setHolidayInput('');
  };

  const removeHoliday = (tanggal: string) => {
    setSettings((current) => ({
      ...current,
      hari_libur: current.hari_libur.filter((item) => item !== tanggal),
    }));
  };

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setSuccess(false);
    setError('');
    try {
      const payload = {
        jam_kerja_per_hari: settings.jam_kerja_per_hari,
        hari_libur: settings.hari_libur,
        maks_penugasan_bersamaan: settings.maks_penugasan_bersamaan,
        maks_kegiatan_besar_bersamaan: settings.maks_kegiatan_besar_bersamaan,
        pegawai_boleh_isi_kendala: settings.pegawai_boleh_isi_kendala,
      };

      const res = await api.put('/settings', payload);
      if (res.data.settings && typeof res.data.settings === 'object') {
        setSettings(normalizeSettings(res.data.settings as Record<string, unknown>));
      }
      setSuccess(true);
      window.setTimeout(() => setSuccess(false), 3000);
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
          Konfigurasi kapasitas jam kerja, hari libur, aturan benturan jadwal, dan kebijakan operasional BPS
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
          <label htmlFor="setting-jam" className="block text-xs font-bold text-slate-700 mb-1">
            Jam Kerja per Hari
          </label>
          <p className="text-[11px] text-slate-400 mb-2">
            Kapasitas kerja satu pegawai per hari kerja. Kapasitas bulanan = jumlah hari kerja bulan tersebut dikali jam kerja per hari.
          </p>
          <input
            id="setting-jam"
            type="number"
            min="1"
            max="24"
            step="0.25"
            required
            value={settings.jam_kerja_per_hari}
            onChange={(e) =>
              setSettings({ ...settings, jam_kerja_per_hari: parseFloat(e.target.value) || DEFAULT_SETTINGS.jam_kerja_per_hari })
            }
            className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600 min-h-[40px]"
          />
        </div>

        <div>
          <label htmlFor="setting-libur" className="block text-xs font-bold text-slate-700 mb-1">
            Hari Libur
          </label>
          <p className="text-[11px] text-slate-400 mb-2">
            Hari libur nasional atau cuti bersama yang tidak dihitung sebagai hari kerja (Senin-Jumat).
          </p>
          <div className="flex flex-col sm:flex-row gap-2">
            <input
              id="setting-libur"
              type="date"
              value={holidayInput}
              onChange={(e) => setHolidayInput(e.target.value)}
              className="flex-1 text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600 min-h-[40px]"
            />
            <button
              type="button"
              onClick={addHoliday}
              disabled={!holidayInput}
              className="inline-flex items-center justify-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-40 min-h-[40px]"
            >
              <Plus className="w-3.5 h-3.5" />
              Tambah
            </button>
          </div>

          {settings.hari_libur.length === 0 ? (
            <p className="mt-2 text-[11px] text-slate-400">Belum ada hari libur yang dikonfigurasi.</p>
          ) : (
            <div className="mt-2 flex flex-wrap gap-1.5">
              {settings.hari_libur.map((tanggal) => (
                <span
                  key={tanggal}
                  className="inline-flex items-center gap-1 rounded-full bg-sky-50 text-sky-800 border border-sky-200 px-2.5 py-1 text-xs font-medium"
                >
                  {tanggal}
                  <button
                    type="button"
                    onClick={() => removeHoliday(tanggal)}
                    aria-label={`Hapus hari libur ${tanggal}`}
                    className="rounded-full hover:bg-sky-100 p-0.5"
                  >
                    <X className="w-3.5 h-3.5" />
                  </button>
                </span>
              ))}
            </div>
          )}
        </div>

        <div>
          <label htmlFor="setting-maks" className="block text-xs font-bold text-slate-700 mb-1">
            Batas Maksimal Penugasan Bersamaan (Harian)
          </label>
          <p className="text-[11px] text-slate-400 mb-2">
            Jumlah maksimum penugasan yang boleh dijalankan seorang pegawai pada hari yang sama sebelum memicu peringatan benturan.
          </p>
          <input
            id="setting-maks"
            type="number"
            min="1"
            max="50"
            required
            value={settings.maks_penugasan_bersamaan}
            onChange={(e) =>
              setSettings({ ...settings, maks_penugasan_bersamaan: parseInt(e.target.value) || 3 })
            }
            className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600 min-h-[40px]"
          />
        </div>

        <div>
          <label htmlFor="setting-maks-besar" className="block text-xs font-bold text-slate-700 mb-1">
            Batas Maksimal Kegiatan Besar Bersamaan
          </label>
          <p className="text-[11px] text-slate-400 mb-2">
            Batas maksimum kegiatan berkategori besar yang boleh berjalan bersamaan pada tanggal yang beririsan.
          </p>
          <input
            id="setting-maks-besar"
            type="number"
            min="1"
            max="50"
            required
            value={settings.maks_kegiatan_besar_bersamaan}
            onChange={(e) =>
              setSettings({ ...settings, maks_kegiatan_besar_bersamaan: parseInt(e.target.value) || 3 })
            }
            className="w-full text-sm border border-slate-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-sky-600 min-h-[40px]"
          />
        </div>

        <div className="pt-2 border-t border-slate-100 flex items-center justify-between gap-3">
          <div>
            <p className="text-xs font-bold text-slate-700">Izin Pegawai Mengisi Kendala Bulanan</p>
            <p className="text-[11px] text-slate-400">
              Jika aktif, seluruh staf dapat melaporkan kendala. Jika dinonaktifkan, hanya Ketua Tim dan Admin yang dapat melapor.
            </p>
          </div>
          <input
            type="checkbox"
            checked={settings.pegawai_boleh_isi_kendala}
            onChange={(e) =>
              setSettings({ ...settings, pegawai_boleh_isi_kendala: e.target.checked })
            }
            className="w-5 h-5 text-sky-600 rounded border-slate-300 focus:ring-sky-500 shrink-0"
          />
        </div>

        <div className="pt-4 border-t border-slate-100 flex justify-end">
          <button
            type="submit"
            disabled={saving}
            className="flex items-center gap-2 bg-sky-600 hover:bg-sky-700 text-white px-5 py-2.5 rounded-lg text-sm font-semibold shadow-xs disabled:opacity-50 transition min-h-[44px]"
          >
            {saving ? <Loader2 className="w-4 h-4 animate-spin" /> : <Save className="w-4 h-4" />}
            {saving ? 'Menyimpan...' : 'Simpan Pengaturan'}
          </button>
        </div>
      </form>
    </div>
  );
};
