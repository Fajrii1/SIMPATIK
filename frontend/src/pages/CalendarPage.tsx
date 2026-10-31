import React, { useEffect, useMemo, useState } from 'react';
import api from '../services/api';
import { Team, User } from '../types';

import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/react/daygrid';
import interactionPlugin from '@fullcalendar/react/interaction';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';

type CalendarEvent = {
  id: string;
  title: string;
  start: string;
  end: string;
  allDay?: boolean;
  extendedProps?: {
    activity_id?: number;
    team_id?: number;
    team_nama?: string;
    jenis?: string;
    status?: string;
    bobot?: number;
    assigned_users?: {
      user_id: number;
      nama: string;
      peran: string;
    }[];
  };
};

type TabType = 'heatmap' | 'timeline' | 'calendar' | 'analytics' | 'employees';

const MONTHS = [
  'Januari', 'Februari', 'Maret', 'April',
  'Mei', 'Juni', 'Juli', 'Agustus',
  'September', 'Oktober', 'November', 'Desember'
];

const MONTH_SHORT = [
  'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
  'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
];

const COLORS = [
  '#0284c7', '#16a34a', '#9333ea',
  '#ea580c', '#db2777', '#0891b2',
  '#ca8a04', '#4f46e5'
];

const formatDate = (date: Date) => {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
};

const getDateRange = (start: string, endExclusive: string) => {
  const result: string[] = [];
  const current = new Date(`${start}T12:00:00`);
  const end = new Date(`${endExclusive}T12:00:00`);

  while (current < end) {
    result.push(formatDate(current));
    current.setDate(current.getDate() + 1);
  }

  return result;
};

const getEventEndInclusive = (event: CalendarEvent) => {
  const end = new Date(`${event.end}T12:00:00`);
  end.setDate(end.getDate() - 1);
  return formatDate(end);
};

const getOverlapColor = (count: number) => {
  if (count === 0) return 'bg-slate-100';
  if (count === 1) return 'bg-emerald-500';
  if (count <= 4) return 'bg-amber-400';
  return 'bg-rose-500';
};

export const CalendarPage: React.FC = () => {
  const [year, setYear] = useState(new Date().getFullYear());
  const [activeTab, setActiveTab] = useState<TabType>('heatmap');

  const [events, setEvents] = useState<CalendarEvent[]>([]);
  const [teams, setTeams] = useState<Team[]>([]);
  const [users, setUsers] = useState<User[]>([]);

  const [loading, setLoading] = useState(true);
  const [showModal, setShowModal] = useState(false);
  const [selectedEvent, setSelectedEvent] = useState<CalendarEvent | null>(null);
  const [saving, setSaving] = useState(false);

  const [form, setForm] = useState({
    nama: '',
    team_id: '',
    jenis: 'Survei',
    mulai: '',
    selesai: '',
    pic_id: '',
    anggota_ids: [] as string[],
    keterangan: ''
  });

  const [picSearch, setPicSearch] = useState('');
  const [memberSearch, setMemberSearch] = useState('');

  // Mengambil data kalender satu tahun penuh.
  const fetchData = async () => {
    setLoading(true);

    try {
      const start = `${year}-01-01`;
      const end = `${year}-12-31`;

      const [calendarRes, teamRes, userRes] = await Promise.all([
        api.get('/calendar', { params: { start, end } }),
        api.get('/teams?per_page=100'),
        api.get('/users?per_page=100')
      ]);

      // CalendarController mengembalikan { events, meta }.
      setEvents(calendarRes.data.events || []);
      setTeams(teamRes.data.data || []);
      setUsers(userRes.data.data || []);
    } catch (error) {
      console.error('Gagal mengambil data kalender:', error);
      alert('Gagal mengambil data kalender.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, [year]);

  // Menghitung jumlah kegiatan pada setiap tanggal.
  const dailyCounts = useMemo(() => {
    const counts: Record<string, number> = {};

    events.forEach(event => {
      const days = getDateRange(event.start, event.end);

      days.forEach(date => {
        counts[date] = (counts[date] || 0) + 1;
      });
    });

    return counts;
  }, [events]);

  // Menghitung ringkasan dashboard.
  // Menghitung ringkasan dashboard.
const analytics = useMemo(() => {
  const activeMonths = new Set<number>();
  const overlapDays = new Set<string>();

  // Menyiapkan data kegiatan per bulan berdasarkan fungsi.
  const monthlyCounts: Record<string, string | number>[] = MONTHS.map(
    (_, index) => {
      const row: Record<string, string | number> = {
        month: MONTH_SHORT[index]
      };

      teams.forEach(team => {
        row[team.nama] = 0;
      });

      row['Lainnya'] = 0;

      return row;
    }
  );

  // Menyiapkan ringkasan setiap fungsi.
  const teamSummary = teams.map(team => ({
    id: team.id,
    nama: team.nama,
    jumlah: 0,
    activeDays: new Set<string>(),
    activities: [] as string[]
  }));

  events.forEach(event => {
    const teamId = event.extendedProps?.team_id;
    const teamName = event.extendedProps?.team_nama || 'Lainnya';

    const team = teamSummary.find(item => item.id === teamId);
    const days = getDateRange(event.start, event.end);

    // Menghitung bulan aktif dan hari yang memiliki jadwal bersamaan.
    days.forEach(date => {
      const monthIndex = Number(date.slice(5, 7)) - 1;

      activeMonths.add(monthIndex);

      if ((dailyCounts[date] || 0) > 1) {
        overlapDays.add(date);
      }
    });

    // Menghitung jumlah kegiatan dan hari aktif setiap fungsi.
    if (team) {
      team.jumlah += 1;
      team.activities.push(event.title);

      days.forEach(day => {
        team.activeDays.add(day);
      });
    }

    // Menghitung kegiatan pada setiap bulan yang bersinggungan
    // dengan rentang tanggal kegiatan.
    const activeEventMonths = new Set(
      days.map(date => Number(date.slice(5, 7)) - 1)
    );

    activeEventMonths.forEach(monthIndex => {
      const currentCount = Number(
        monthlyCounts[monthIndex][teamName] || 0
      );

      monthlyCounts[monthIndex][teamName] = currentCount + 1;
    });
  });

  return {
    total: events.length,
    activeMonths: activeMonths.size,
    overlapDays: overlapDays.size,
    monthlyCounts,
    teamSummary
  };
}, [events, teams, dailyCounts]);

  // Rekap karyawan berdasarkan assignment.
  const employeeSummary = useMemo(() => {
    return users.map(user => {
      const userEvents = events.filter(event =>
        event.extendedProps?.assigned_users?.some(
          assigned => assigned.user_id === user.id
        )
      );

      const busyDays = new Set<string>();

      userEvents.forEach(event => {
        getDateRange(event.start, event.end).forEach(day => {
          busyDays.add(day);
        });
      });

      const picCount = userEvents.filter(event =>
        event.extendedProps?.assigned_users?.some(
          assigned =>
            assigned.user_id === user.id &&
            assigned.peran.toLowerCase().includes('pic')
        )
      ).length;

      return {
        id: user.id,
        nama: user.nama,
        picCount,
        busyDays: busyDays.size,
        activities: userEvents.map(event => event.title)
      };
    }).filter(user => user.activities.length > 0);
  }, [users, events]);

  const openDetail = (event: CalendarEvent) => {
    setSelectedEvent(event);
  };

  // Membuat kegiatan dan penugasannya.
  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!form.team_id || !form.pic_id) {
      alert('Fungsi dan Penanggung Jawab wajib dipilih.');
      return;
    }

    if (form.selesai < form.mulai) {
      alert('Tanggal selesai tidak boleh sebelum tanggal mulai.');
      return;
    }

    setSaving(true);

    try {
      // 1. Simpan data utama kegiatan.
      const activityResponse = await api.post('/activities', {
        nama: form.nama,
        team_id: Number(form.team_id),
        jenis: form.jenis,
        mulai: form.mulai,
        selesai: form.selesai,
        bobot: 1,
        kegiatan_besar: false,
        keterangan: form.keterangan
      });

      const activityId = activityResponse.data.data?.id;

      if (!activityId) {
        throw new Error('Kegiatan berhasil dikirim, tetapi ID kegiatan tidak ditemukan.');
      }

      // 2. Simpan PIC dan organik sebagai assignment.
      const assignedUsers = [
        { id: Number(form.pic_id), peran: 'PIC' },
        ...form.anggota_ids
          .filter(id => id !== form.pic_id)
          .map(id => ({ id: Number(id), peran: 'Organik' }))
      ];

      // Backend SIMPATIK mewajibkan volume dan satuan.
      // Nilai awal di sini 1 Hari; bisa disesuaikan dengan aturan bisnis.
      for (const assigned of assignedUsers) {
        await api.post(`/activities/${activityId}/assignments`, {
          user_id: assigned.id,
          peran: assigned.peran,
          volume: 1,
          satuan: 'Hari',
          mulai: form.mulai,
          selesai: form.selesai
        });
      }

      alert('Kegiatan berhasil ditambahkan.');
      setShowModal(false);

      setForm({
        nama: '',
        team_id: '',
        jenis: 'Survei',
        mulai: '',
        selesai: '',
        pic_id: '',
        anggota_ids: [],
        keterangan: ''
      });

      setPicSearch('');
      setMemberSearch('');

      await fetchData();
    } catch (error: any) {
      console.error(error);

      const message =
        error.response?.data?.message ||
        error.message ||
        'Gagal menyimpan kegiatan.';

      alert(
        `${message}\n\nJika kegiatan sudah terbuat tetapi penugasan gagal, buka menu Kegiatan untuk melengkapi penugasannya.`
      );

      await fetchData();
    } finally {
      setSaving(false);
    }
  };

  const toggleMember = (id: string) => {
    setForm(current => ({
      ...current,
      anggota_ids: current.anggota_ids.includes(id)
        ? current.anggota_ids.filter(item => item !== id)
        : [...current.anggota_ids, id]
    }));
  };

  const tabs: { id: TabType; label: string }[] = [
    { id: 'heatmap', label: 'Heatmap' },
    { id: 'timeline', label: 'Timeline' },
    { id: 'calendar', label: 'Kalender Bulanan' },
    { id: 'analytics', label: 'Ringkasan & Overlap' },
    { id: 'employees', label: 'Rekap Karyawan' }
  ];

  return (
    <div className="max-w-7xl mx-auto space-y-5">

      {/* HEADER */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <h1 className="text-xl font-bold text-slate-900">
            Kalender Kegiatan
          </h1>
          <p className="text-sm text-slate-500">
            Monitoring jadwal dan kepadatan kegiatan tahunan.
          </p>
        </div>

        <div className="flex gap-2">
          <select
            value={year}
            onChange={e => setYear(Number(e.target.value))}
            className="border rounded-lg px-3 py-2 text-sm bg-white"
          >
            {Array.from({ length: 7 }, (_, i) => new Date().getFullYear() - 3 + i)
              .map(item => (
                <option key={item} value={item}>{item}</option>
              ))}
          </select>

          <button
            onClick={() => setShowModal(true)}
            className="px-4 py-2 bg-sky-600 text-white rounded-lg text-sm font-semibold hover:bg-sky-700"
          >
            + Tambah Kegiatan
          </button>
        </div>
      </div>

      {/* TAB NAVIGATION */}
      <div className="flex gap-2 overflow-x-auto border-b border-slate-200 pb-2">
        {tabs.map(tab => (
          <button
            key={tab.id}
            onClick={() => setActiveTab(tab.id)}
            className={`px-4 py-2 rounded-lg text-sm whitespace-nowrap font-medium ${
              activeTab === tab.id
                ? 'bg-sky-600 text-white'
                : 'bg-white border text-slate-600 hover:bg-slate-50'
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {loading ? (
        <div className="bg-white border rounded-xl p-16 text-center text-slate-500">
          Memuat data kegiatan...
        </div>
      ) : (
        <>
          {/* TAB 1: HEATMAP */}
          {activeTab === 'heatmap' && (
            <div className="bg-white border rounded-xl p-4 overflow-x-auto">
              <div className="flex justify-between items-center mb-4">
                <div>
                  <h2 className="font-bold text-slate-900">
                    Peta Kepadatan Kegiatan
                  </h2>
                  <p className="text-xs text-slate-500">
                    Jumlah kegiatan yang aktif pada setiap tanggal.
                  </p>
                </div>
              </div>

              <div className="min-w-[850px]">
                <div className="grid grid-cols-[110px_repeat(31,minmax(20px,1fr))] gap-1 mb-2">
                  <div className="text-xs font-semibold text-slate-500">
                    Bulan / Tanggal
                  </div>

                  {Array.from({ length: 31 }, (_, i) => (
                    <div
                      key={i}
                      className="text-[10px] text-center text-slate-500"
                    >
                      {i + 1}
                    </div>
                  ))}
                </div>

                {MONTHS.map((month, monthIndex) => (
                  <div
                    key={month}
                    className="grid grid-cols-[110px_repeat(31,minmax(20px,1fr))] gap-1 mb-1"
                  >
                    <div className="text-xs font-medium text-slate-700 flex items-center">
                      {month}
                    </div>

                    {Array.from({ length: 31 }, (_, dayIndex) => {
                      const day = dayIndex + 1;
                      const date = `${year}-${String(monthIndex + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

                      const validDate =
                        day <= new Date(year, monthIndex + 1, 0).getDate();

                      const count = validDate ? dailyCounts[date] || 0 : 0;

                      return (
                        <div
                          key={day}
                          title={
                            validDate
                              ? `${day} ${month}: ${count} kegiatan`
                              : 'Tanggal tidak tersedia'
                          }
                          className={`h-6 rounded-sm ${
                            validDate
                              ? getOverlapColor(count)
                              : 'bg-transparent'
                          }`}
                        />
                      );
                    })}
                  </div>
                ))}
              </div>

              <div className="flex flex-wrap gap-4 mt-5 text-xs text-slate-600">
                <span className="flex items-center gap-2">
                  <span className="w-3 h-3 bg-slate-100 rounded" />
                  0 kegiatan
                </span>
                <span className="flex items-center gap-2">
                  <span className="w-3 h-3 bg-emerald-500 rounded" />
                  1 kegiatan
                </span>
                <span className="flex items-center gap-2">
                  <span className="w-3 h-3 bg-amber-400 rounded" />
                  2–4 kegiatan
                </span>
                <span className="flex items-center gap-2">
                  <span className="w-3 h-3 bg-rose-500 rounded" />
                  5+ kegiatan
                </span>
              </div>
            </div>
          )}

          {/* TAB 2: TIMELINE */}
          {activeTab === 'timeline' && (
            <div className="bg-white border rounded-xl p-4 overflow-x-auto">
              <h2 className="font-bold text-slate-900 mb-1">
                Timeline Kegiatan Tahunan
              </h2>
              <p className="text-xs text-slate-500 mb-5">
                Jadwal kegiatan berdasarkan fungsi/tim kerja.
              </p>

              <div className="min-w-[950px]">
                <div className="grid grid-cols-[220px_repeat(12,minmax(0,1fr))] border-b pb-2 mb-3">
                  <div className="text-xs font-semibold">Fungsi / Kegiatan</div>
                  {MONTH_SHORT.map(month => (
                    <div key={month} className="text-xs text-center font-semibold">
                      {month}
                    </div>
                  ))}
                </div>

                {teams.map(team => {
                  const teamEvents = events.filter(
                    event => event.extendedProps?.team_id === team.id
                  );

                  return (
                    <div key={team.id} className="mb-5">
                      <div className="bg-slate-100 px-3 py-2 text-xs font-bold text-slate-800 rounded">
                        {team.nama} ({teamEvents.length} kegiatan)
                      </div>

                      {teamEvents.map(event => {
const start = new Date(`${event.start}T12:00:00`);
const end = new Date(`${getEventEndInclusive(event)}T12:00:00`);

const yearStart = new Date(year, 0, 1);
const yearEnd = new Date(year + 1, 0, 1);

const totalDays =
  (yearEnd.getTime() - yearStart.getTime()) / (1000 * 60 * 60 * 24);

const visibleStart = new Date(
  Math.max(start.getTime(), yearStart.getTime())
);

const visibleEnd = new Date(
  Math.min(end.getTime(), new Date(year, 11, 31).getTime())
);

const startOffset =
  (visibleStart.getTime() - yearStart.getTime()) /
  (1000 * 60 * 60 * 24);

const endOffset =
  (visibleEnd.getTime() - yearStart.getTime()) /
  (1000 * 60 * 60 * 24) + 1;

const left = (startOffset / totalDays) * 100;
const width = ((endOffset - startOffset) / totalDays) * 100;

                        return (
                          <div
                            key={event.id}
                            className="grid grid-cols-[220px_1fr] items-center gap-2 py-2 border-b border-slate-100"
                          >
                            <button
                              onClick={() => openDetail(event)}
                              className="text-left text-xs text-slate-700 hover:text-sky-700 truncate"
                              title={event.title}
                            >
                              {event.title}
                            </button>

                            <div className="relative h-7 bg-slate-50 rounded">
                              <div
                                className="absolute top-1 h-5 rounded px-2 text-[10px] text-white flex items-center overflow-hidden whitespace-nowrap cursor-pointer"
                                style={{
                                  left: `${left}%`,
                                  width: `${width}%`,
                                  backgroundColor: COLORS[team.id % COLORS.length]
                                }}
                                onClick={() => openDetail(event)}
                                title={`${event.title}: ${event.start} s.d. ${getEventEndInclusive(event)}`}
                              >
                                {event.title}
                              </div>
                            </div>
                          </div>
                        );
                      })}

                      {teamEvents.length === 0 && (
                        <p className="text-xs text-slate-400 py-3">
                          Belum ada kegiatan.
                        </p>
                      )}
                    </div>
                  );
                })}
              </div>
            </div>
          )}

          {/* TAB 3: FULLCALENDAR */}
          {activeTab === 'calendar' && (
            <div className="bg-white border rounded-xl p-4">
              <style>{`
                .fc .fc-button-primary {
                  background: #0284c7;
                  border-color: #0284c7;
                }
                .fc .fc-button-primary:hover {
                  background: #0369a1;
                  border-color: #0369a1;
                }
                .fc .fc-event {
                  cursor: pointer;
                  border: none;
                  padding: 2px 4px;
                }
                .fc .fc-daygrid-event {
                  white-space: normal;
                }
              `}</style>

              <FullCalendar
                plugins={[dayGridPlugin, interactionPlugin]}
                initialView="dayGridMonth"
                initialDate={`${year}-01-01`}
                headerToolbar={{
                  left: 'prev,next today',
                  center: 'title',
                  right: 'dayGridMonth'
                }}
                locale="id"
                events={events.map(event => ({
                  ...event,
                  // FullCalendar memakai end eksklusif untuk all-day events.
                  end: event.end,
                  backgroundColor: COLORS[
                    (event.extendedProps?.team_id || 0) % COLORS.length
                  ]
                }))}
                eventClick={info => {
                  const event = events.find(
                    item => item.id === info.event.id
                  );

                  if (event) openDetail(event);
                }}
                height="auto"
              />
            </div>
          )}

          {/* TAB 4: ANALYTICS */}
          {activeTab === 'analytics' && (
            <div className="space-y-5">
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div className="bg-white border rounded-xl p-5">
                  <p className="text-xs text-slate-500">Total Kegiatan</p>
                  <h3 className="text-3xl font-bold text-slate-900 mt-2">
                    {analytics.total}
                  </h3>
                </div>

                <div className="bg-white border rounded-xl p-5">
                  <p className="text-xs text-slate-500">Bulan Aktif</p>
                  <h3 className="text-3xl font-bold text-emerald-600 mt-2">
                    {analytics.activeMonths}
                    <span className="text-sm text-slate-400 ml-1">/ 12</span>
                  </h3>
                </div>

                <div className="bg-white border rounded-xl p-5">
                  <p className="text-xs text-slate-500">
                    Total Hari dengan Overlap
                  </p>
                  <h3 className="text-3xl font-bold text-rose-600 mt-2">
                    {analytics.overlapDays}
                  </h3>
                </div>
              </div>

              <div className="bg-white border rounded-xl p-5">
                <h2 className="font-bold text-slate-900 mb-4">
                  Kegiatan per Bulan
                </h2>

                <div className="h-[350px]">
                  <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={analytics.monthlyCounts}>
                      <CartesianGrid strokeDasharray="3 3" />
                      <XAxis dataKey="month" />
                      <YAxis allowDecimals={false} />
                      <Tooltip />
                      <Legend />

                      {[...teams.map(team => team.nama), 'Lainnya'].map((teamName, index) => (
                        <Bar
                          key={teamName}
                          dataKey={teamName}
                          stackId="a"
                          fill={COLORS[index % COLORS.length]}
                        />
                      ))}
                    </BarChart>
                  </ResponsiveContainer>
                </div>
              </div>

              <div className="bg-white border rounded-xl p-5 overflow-x-auto">
                <h2 className="font-bold text-slate-900 mb-4">
                  Beban per Fungsi
                </h2>

                <table className="w-full text-sm">
                  <thead>
                    <tr className="text-left border-b text-slate-500">
                      <th className="py-3">Nama Fungsi</th>
                      <th>Jumlah Kegiatan</th>
                      <th>Total Hari Aktif</th>
                      <th>Rincian Kegiatan</th>
                    </tr>
                  </thead>

                  <tbody>
                    {analytics.teamSummary.map(team => (
                      <tr key={team.id} className="border-b last:border-0">
                        <td className="py-3 font-semibold">{team.nama}</td>
                        <td>{team.jumlah}</td>
                        <td>{team.activeDays.size}</td>
                        <td>
                          <div className="flex flex-wrap gap-1">
                            {team.activities.map((activity, index) => (
                              <span
                                key={`${activity}-${index}`}
                                className="px-2 py-1 rounded bg-sky-50 text-sky-700 text-xs"
                              >
                                {activity}
                              </span>
                            ))}
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {/* TAB 5: EMPLOYEE SUMMARY */}
          {activeTab === 'employees' && (
            <div className="bg-white border rounded-xl p-4 overflow-x-auto">
              <h2 className="font-bold text-slate-900 mb-1">
                Rekap Beban Karyawan
              </h2>
              <p className="text-xs text-slate-500 mb-4">
                Ringkasan keterlibatan pegawai dalam kegiatan tahun {year}.
              </p>

              <table className="w-full text-sm min-w-[850px]">
                <thead>
                  <tr className="text-left text-slate-500 border-b">
                    <th className="py-3">No</th>
                    <th>Nama Pegawai</th>
                    <th>Jumlah sebagai PIC</th>
                    <th>Total Hari Sibuk</th>
                    <th>Daftar Kegiatan</th>
                  </tr>
                </thead>

                <tbody>
                  {employeeSummary.map((employee, index) => (
                    <tr key={employee.id} className="border-b last:border-0">
                      <td className="py-3">{index + 1}</td>
                      <td className="font-semibold">{employee.nama}</td>
                      <td>{employee.picCount}</td>
                      <td>{employee.busyDays} hari</td>
                      <td>
                        <div className="flex flex-wrap gap-1">
                          {employee.activities.slice(0, 3).map((activity, i) => (
                            <span
                              key={`${activity}-${i}`}
                              className="px-2 py-1 rounded-full bg-sky-50 text-sky-700 text-xs"
                            >
                              {activity}
                            </span>
                          ))}

                          {employee.activities.length > 3 && (
                            <span className="px-2 py-1 rounded-full bg-slate-100 text-slate-600 text-xs">
                              +{employee.activities.length - 3} lainnya
                            </span>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}

                  {employeeSummary.length === 0 && (
                    <tr>
                      <td colSpan={5} className="text-center py-10 text-slate-400">
                        Belum ada data penugasan pegawai.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          )}
        </>
      )}

      {/* MODAL TAMBAH KEGIATAN */}
      {showModal && (
        <div className="fixed inset-0 z-50 bg-slate-900/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-5">
            <div className="flex justify-between items-center mb-5">
              <h2 className="font-bold text-lg">Tambah Kegiatan</h2>
              <button
                onClick={() => setShowModal(false)}
                className="text-slate-400 hover:text-slate-700 text-xl"
              >
                ×
              </button>
            </div>

            <form onSubmit={handleSave} className="space-y-4">

              <div>
                <label className="block text-sm font-medium mb-1">
                  Fungsi / Divisi
                </label>
                <select
                  required
                  value={form.team_id}
                  onChange={e => setForm({ ...form, team_id: e.target.value })}
                  className="w-full border rounded-lg px-3 py-2"
                >
                  <option value="">Pilih Fungsi</option>
                  {teams.map(team => (
                    <option key={team.id} value={team.id}>
                      {team.nama}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium mb-1">
                  Nama Kegiatan
                </label>
                <input
                  required
                  value={form.nama}
                  onChange={e => setForm({ ...form, nama: e.target.value })}
                  placeholder="Masukkan nama kegiatan"
                  className="w-full border rounded-lg px-3 py-2"
                />
              </div>

              <div>
                <label className="block text-sm font-medium mb-1">
                  Tahapan
                </label>
                <select
                  value={form.jenis}
                  onChange={e => setForm({ ...form, jenis: e.target.value })}
                  className="w-full border rounded-lg px-3 py-2"
                >
                  <option>Survei</option>
                  <option>Sensus</option>
                  <option>Pengolahan Data</option>
                  <option>Diseminasi</option>
                  <option>Pembinaan</option>
                  <option>Rapat dan Koordinasi</option>
                  <option>Lainnya</option>
                </select>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label className="block text-sm font-medium mb-1">
                    Tanggal Mulai
                  </label>
                  <input
                    required
                    type="date"
                    value={form.mulai}
                    onChange={e => setForm({ ...form, mulai: e.target.value })}
                    className="w-full border rounded-lg px-3 py-2"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium mb-1">
                    Tanggal Selesai
                  </label>
                  <input
                    required
                    type="date"
                    min={form.mulai || undefined}
                    value={form.selesai}
                    onChange={e => setForm({ ...form, selesai: e.target.value })}
                    className="w-full border rounded-lg px-3 py-2"
                  />
                </div>
              </div>

              {/* PIC dengan pencarian */}
              <div>
                <label className="block text-sm font-medium mb-1">
                  Penanggung Jawab (PIC)
                </label>
                <input
                  value={picSearch}
                  onChange={e => setPicSearch(e.target.value)}
                  placeholder="Cari nama pegawai..."
                  className="w-full border rounded-lg px-3 py-2 mb-2"
                />

                <select
                  required
                  value={form.pic_id}
                  onChange={e => setForm({ ...form, pic_id: e.target.value })}
                  className="w-full border rounded-lg px-3 py-2"
                >
                  <option value="">Pilih PIC</option>
                  {users
                    .filter(user =>
                      user.nama.toLowerCase().includes(picSearch.toLowerCase())
                    )
                    .map(user => (
                      <option key={user.id} value={user.id}>
                        {user.nama} - {user.jabatan || user.peran}
                      </option>
                    ))}
                </select>
              </div>

              {/* Organik multi-select */}
              <div>
                <label className="block text-sm font-medium mb-1">
                  Organik Terlibat
                </label>

                <input
                  value={memberSearch}
                  onChange={e => setMemberSearch(e.target.value)}
                  placeholder="Cari nama pegawai..."
                  className="w-full border rounded-lg px-3 py-2 mb-2"
                />

                <div className="border rounded-lg max-h-40 overflow-y-auto p-2 space-y-2">
                  {users
                    .filter(user =>
                      user.nama.toLowerCase().includes(memberSearch.toLowerCase())
                    )
                    .filter(user => String(user.id) !== form.pic_id)
                    .map(user => (
                      <label
                        key={user.id}
                        className="flex items-center gap-2 text-sm"
                      >
                        <input
                          type="checkbox"
                          checked={form.anggota_ids.includes(String(user.id))}
                          onChange={() => toggleMember(String(user.id))}
                        />
                        {user.nama}
                      </label>
                    ))}
                </div>

                <div className="flex flex-wrap gap-1 mt-2">
                  {form.anggota_ids.map(id => {
                    const user = users.find(item => String(item.id) === id);

                    return user ? (
                      <button
                        type="button"
                        key={id}
                        onClick={() => toggleMember(id)}
                        className="bg-sky-50 text-sky-700 px-2 py-1 rounded-full text-xs"
                      >
                        {user.nama} ×
                      </button>
                    ) : null;
                  })}
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium mb-1">
                  Keterangan
                </label>
                <textarea
                  rows={3}
                  value={form.keterangan}
                  onChange={e => setForm({ ...form, keterangan: e.target.value })}
                  placeholder="Tambahkan keterangan kegiatan..."
                  className="w-full border rounded-lg px-3 py-2"
                />
              </div>

              <div className="flex justify-end gap-2 pt-4 border-t">
                <button
                  type="button"
                  onClick={() => setShowModal(false)}
                  className="px-4 py-2 rounded-lg border text-slate-600"
                >
                  Batal
                </button>

                <button
                  type="submit"
                  disabled={saving}
                  className="px-4 py-2 rounded-lg bg-sky-600 text-white font-semibold disabled:opacity-50"
                >
                  {saving ? 'Menyimpan...' : 'Simpan Kegiatan'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODAL DETAIL KEGIATAN */}
      {selectedEvent && (
        <div className="fixed inset-0 z-[60] bg-slate-900/50 flex items-center justify-center p-4">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-lg p-5">
            <div className="flex justify-between items-start gap-3">
              <h2 className="font-bold text-lg">{selectedEvent.title}</h2>
              <button
                onClick={() => setSelectedEvent(null)}
                className="text-slate-400 text-xl"
              >
                ×
              </button>
            </div>

            <div className="space-y-3 mt-5 text-sm">
              <div className="flex justify-between gap-3">
                <span className="text-slate-500">Fungsi</span>
                <strong>{selectedEvent.extendedProps?.team_nama || '-'}</strong>
              </div>

              <div className="flex justify-between gap-3">
                <span className="text-slate-500">Tahapan</span>
                <strong>{selectedEvent.extendedProps?.jenis || '-'}</strong>
              </div>

              <div className="flex justify-between gap-3">
                <span className="text-slate-500">Tanggal</span>
                <strong>
                  {selectedEvent.start} s.d. {getEventEndInclusive(selectedEvent)}
                </strong>
              </div>

              <div className="flex justify-between gap-3">
                <span className="text-slate-500">Status</span>
                <strong>{selectedEvent.extendedProps?.status || '-'}</strong>
              </div>

              <div className="border-t pt-3">
                <p className="font-semibold mb-2">Pegawai Terlibat</p>

                {selectedEvent.extendedProps?.assigned_users?.length ? (
                  selectedEvent.extendedProps.assigned_users.map((user, index) => (
                    <div key={`${user.user_id}-${index}`} className="flex justify-between py-1">
                      <span>{user.nama}</span>
                      <span className="text-sky-700 text-xs font-semibold">
                        {user.peran}
                      </span>
                    </div>
                  ))
                ) : (
                  <p className="text-slate-400">Belum ada penugasan.</p>
                )}
              </div>
            </div>

            <button
              onClick={() => setSelectedEvent(null)}
              className="mt-5 w-full bg-slate-100 hover:bg-slate-200 rounded-lg py-2 text-sm font-medium"
            >
              Tutup
            </button>
          </div>
        </div>
      )}
    </div>
  );
};
