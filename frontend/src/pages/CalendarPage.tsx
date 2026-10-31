import React, { useEffect, useState } from 'react';
import api from '../services/api';
import { useAuth } from '../context/AuthContext';
import {
  Calendar as CalendarIcon,
  ChevronLeft,
  ChevronRight,
  Filter,
  Loader2,
  List,
  Grid,
} from 'lucide-react';
import { Team, User } from '../types';

export const CalendarPage: React.FC = () => {
  const [currentDate, setCurrentDate] = useState(() => new Date());
  const [teams, setTeams] = useState<Team[]>([]);
  const [users, setUsers] = useState<User[]>([]);
  const [selectedTeam, setSelectedTeam] = useState<string>('');
  const [selectedUser, setSelectedUser] = useState<string>('');
  const [selectedStatus, setSelectedStatus] = useState<string>('');
  const [events, setEvents] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [selectedEvent, setSelectedEvent] = useState<any | null>(null);
  const [viewMode, setViewMode] = useState<'grid' | 'list'>('grid');

  const year = currentDate.getFullYear();
  const month = currentDate.getMonth();

  // Helper aman zona waktu lokal tanpa pergeseran ISO UTC
  const formatLocalDate = (d: Date) => {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  };

  const fetchCalendar = async () => {
    setLoading(true);
    const start = formatLocalDate(new Date(year, month, 1));
    const end = formatLocalDate(new Date(year, month + 1, 0));

    try {
      const res = await api.get('/calendar', {
        params: {
          start,
          end,
          team_id: selectedTeam || undefined,
          user_id: selectedUser || undefined,
          status: selectedStatus || undefined,
        },
      });
      setEvents(res.data.data || []);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const fetchFilterData = async () => {
    try {
      const [tRes, uRes] = await Promise.all([
        api.get('/teams'),
        api.get('/users?per_page=100'),
      ]);
      setTeams(tRes.data.data || []);
      setUsers(uRes.data.data || []);
    } catch (err) {
      console.error(err);
    }
  };

  useEffect(() => {
    fetchFilterData();
  }, []);

  useEffect(() => {
    fetchCalendar();
  }, [currentDate, selectedTeam, selectedUser, selectedStatus]);

  const prevMonth = () => {
    setCurrentDate(new Date(year, month - 1, 1));
  };

  const nextMonth = () => {
    setCurrentDate(new Date(year, month + 1, 1));
  };

  const today = () => {
    setCurrentDate(new Date());
  };

  const firstDayIndex = new Date(year, month, 1).getDay();
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const daysInPrevMonth = new Date(year, month, 0).getDate();

  const monthNames = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
  ];

  const daysOfWeek = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

  const getEventsForDay = (day: number) => {
    const dStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    return events.filter((ev) => {
      const start = ev.start;
      const end = ev.end;
      return dStr >= start && dStr <= end;
    });
  };

  return (
    <div className="space-y-6 max-w-7xl mx-auto">
      {/* Calendar Header Controls */}
      <div className="bg-white p-4 sm:p-5 rounded-xl border border-slate-200 shadow-xs flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div className="flex flex-wrap items-center gap-3 sm:gap-4 w-full md:w-auto">
          <h1 className="text-lg sm:text-xl font-bold text-slate-900 flex items-center gap-2">
            <CalendarIcon className="w-5 h-5 text-sky-600 shrink-0" />
            {monthNames[month]} {year}
          </h1>

          <div className="flex items-center gap-1 border border-slate-200 rounded-lg p-0.5 bg-slate-50">
            <button
              onClick={prevMonth}
              aria-label="Bulan sebelumnya"
              className="p-1.5 hover:bg-white rounded-md text-slate-600 transition min-h-[36px] min-w-[36px] flex items-center justify-center"
            >
              <ChevronLeft className="w-4 h-4" />
            </button>
            <button
              onClick={today}
              className="px-2.5 py-1 text-xs font-semibold hover:bg-white rounded-md text-slate-700 transition min-h-[36px] flex items-center"
            >
              Hari Ini
            </button>
            <button
              onClick={nextMonth}
              aria-label="Bulan berikutnya"
              className="p-1.5 hover:bg-white rounded-md text-slate-600 transition min-h-[36px] min-w-[36px] flex items-center justify-center"
            >
              <ChevronRight className="w-4 h-4" />
            </button>
          </div>
        </div>

        {/* Mode Switch & Filter Bar */}
        <div className="flex flex-wrap items-center gap-2 w-full md:w-auto">
          {/* Switch Grid/List View for Mobile */}
          <div className="flex border border-slate-200 rounded-lg p-0.5 bg-slate-50 md:hidden">
            <button
              onClick={() => setViewMode('grid')}
              className={`p-1.5 rounded-md min-h-[36px] min-w-[36px] flex items-center justify-center ${
                viewMode === 'grid' ? 'bg-white shadow-xs text-sky-700 font-bold' : 'text-slate-500'
              }`}
            >
              <Grid className="w-4 h-4" />
            </button>
            <button
              onClick={() => setViewMode('list')}
              className={`p-1.5 rounded-md min-h-[36px] min-w-[36px] flex items-center justify-center ${
                viewMode === 'list' ? 'bg-white shadow-xs text-sky-700 font-bold' : 'text-slate-500'
              }`}
            >
              <List className="w-4 h-4" />
            </button>
          </div>

          <select
            value={selectedTeam}
            onChange={(e) => setSelectedTeam(e.target.value)}
            className="text-xs sm:text-sm border border-slate-200 rounded-lg px-2.5 py-2 bg-white focus:ring-2 focus:ring-sky-600 flex-1 md:flex-none min-h-[40px]"
          >
            <option value="">Semua Tim Kerja</option>
            {teams.map((t) => (
              <option key={t.id} value={t.id}>{t.nama}</option>
            ))}
          </select>

          <select
            value={selectedUser}
            onChange={(e) => setSelectedUser(e.target.value)}
            className="text-xs sm:text-sm border border-slate-200 rounded-lg px-2.5 py-2 bg-white focus:ring-2 focus:ring-sky-600 flex-1 md:flex-none min-h-[40px]"
          >
            <option value="">Semua Pegawai</option>
            {users.map((u) => (
              <option key={u.id} value={u.id}>{u.nama}</option>
            ))}
          </select>

          <select
            value={selectedStatus}
            onChange={(e) => setSelectedStatus(e.target.value)}
            className="text-xs sm:text-sm border border-slate-200 rounded-lg px-2.5 py-2 bg-white focus:ring-2 focus:ring-sky-600 flex-1 md:flex-none min-h-[40px]"
          >
            <option value="">Semua Status</option>
            <option value="Belum Mulai">Belum Mulai</option>
            <option value="Berjalan">Berjalan</option>
            <option value="Selesai">Selesai</option>
            <option value="Terlambat">Terlambat</option>
          </select>
        </div>
      </div>

      {/* Tampilan Alternatif List / Agenda untuk Layar Sempit jika dipilih */}
      {viewMode === 'list' ? (
        <div className="bg-white rounded-xl border border-slate-200 shadow-xs p-4 space-y-3">
          <h2 className="text-sm font-bold text-slate-800">Daftar Agenda Kegiatan Bulan Ini ({events.length})</h2>
          {events.length === 0 ? (
            <p className="text-xs text-slate-500 py-6 text-center">Tidak ada kegiatan di bulan ini.</p>
          ) : (
            <div className="divide-y divide-slate-100">
              {events.map((ev) => (
                <div
                  key={ev.id}
                  onClick={() => setSelectedEvent(ev)}
                  className="py-3 flex flex-col gap-1 cursor-pointer hover:bg-slate-50 rounded-lg px-2 transition"
                >
                  <div className="flex justify-between items-start">
                    <span className="font-semibold text-sm text-slate-900">{ev.title}</span>
                    <span className="text-[10px] px-2 py-0.5 rounded-full font-medium bg-sky-50 text-sky-700 border border-sky-100">
                      {ev.extendedProps?.status || 'Berjalan'}
                    </span>
                  </div>
                  <div className="text-xs text-slate-500 flex justify-between">
                    <span>{ev.extendedProps?.tim || '-'}</span>
                    <span className="font-mono">{ev.start} s.d. {ev.end}</span>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      ) : (
        /* Interactive Grid Calendar */
        <div className="bg-white rounded-xl border border-slate-200 shadow-xs overflow-hidden">
          {/* Days of week header */}
          <div className="grid grid-cols-7 border-b border-slate-200 bg-slate-50 text-center py-2.5 text-xs font-bold text-slate-600">
            {daysOfWeek.map((day, idx) => (
              <div key={idx} className={idx === 0 ? 'text-rose-500' : ''}>
                {day}
              </div>
            ))}
          </div>

          {/* Cells */}
          {loading ? (
            <div className="py-24 flex justify-center">
              <Loader2 className="w-8 h-8 animate-spin text-sky-600" />
            </div>
          ) : (
            <div className="grid grid-cols-7 auto-rows-fr divide-x divide-y divide-slate-100 min-h-[500px] sm:min-h-[580px]">
              {/* Blank previous month days */}
              {Array.from({ length: firstDayIndex }).map((_, i) => {
                const prevDay = daysInPrevMonth - firstDayIndex + i + 1;
                return (
                  <div key={`prev-${i}`} className="p-1 sm:p-2 bg-slate-50/50 text-slate-300 text-xs">
                    {prevDay}
                  </div>
                );
              })}

              {/* Current month days */}
              {Array.from({ length: daysInMonth }).map((_, i) => {
                const day = i + 1;
                const dayEvents = getEventsForDay(day);
                const isToday =
                  new Date().getDate() === day &&
                  new Date().getMonth() === month &&
                  new Date().getFullYear() === year;

                return (
                  <div
                    key={`day-${day}`}
                    className={`p-1 sm:p-2 transition-colors min-h-[75px] sm:min-h-[95px] flex flex-col justify-between ${
                      isToday ? 'bg-sky-50/40' : 'hover:bg-slate-50/50'
                    }`}
                  >
                    <div className="flex justify-between items-start">
                      <span
                        className={`text-[11px] sm:text-xs font-bold inline-flex items-center justify-center w-5 h-5 sm:w-6 sm:h-6 rounded-full ${
                          isToday
                            ? 'bg-sky-600 text-white'
                            : 'text-slate-700'
                        }`}
                      >
                        {day}
                      </span>
                      {dayEvents.length > 2 && (
                        <span className="text-[9px] sm:text-[10px] text-slate-400 font-medium">
                          +{dayEvents.length - 2}
                        </span>
                      )}
                    </div>

                    <div className="space-y-1 mt-1 flex-1">
                      {dayEvents.slice(0, 2).map((ev) => (
                        <button
                          key={ev.id}
                          onClick={() => setSelectedEvent(ev)}
                          className="w-full text-left p-1 rounded text-[10px] sm:text-[11px] leading-tight font-medium truncate block bg-sky-100/70 text-sky-900 hover:bg-sky-200/70 transition border border-sky-200/50 min-h-[24px]"
                          title={ev.title}
                        >
                          {ev.title}
                        </button>
                      ))}
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      )}

      {/* Modal Detail Event Kalender */}
      {selectedEvent && (
        <div className="fixed inset-0 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-xl shadow-xl border border-slate-200 max-w-md w-full p-5 sm:p-6 max-h-[90vh] overflow-y-auto">
            <h2 className="text-base font-bold text-slate-900 mb-2">{selectedEvent.title}</h2>
            <div className="space-y-3 text-xs text-slate-600 mt-4">
              <div className="flex justify-between py-1.5 border-b border-slate-100">
                <span className="font-semibold text-slate-500">Tim Kerja:</span>
                <span className="text-slate-800 text-right">{selectedEvent.extendedProps?.tim || '-'}</span>
              </div>
              <div className="flex justify-between py-1.5 border-b border-slate-100">
                <span className="font-semibold text-slate-500">Jenis Kegiatan:</span>
                <span className="text-slate-800">{selectedEvent.extendedProps?.jenis || 'Survei'}</span>
              </div>
              <div className="flex justify-between py-1.5 border-b border-slate-100">
                <span className="font-semibold text-slate-500">Periode:</span>
                <span className="text-slate-800 font-mono">{selectedEvent.start} s.d. {selectedEvent.end}</span>
              </div>
              <div className="flex justify-between py-1.5 border-b border-slate-100">
                <span className="font-semibold text-slate-500">Bobot Kesulitan:</span>
                <span className="font-bold text-slate-800">{selectedEvent.extendedProps?.bobot || 1.0}</span>
              </div>
              <div className="flex justify-between py-1.5 border-b border-slate-100">
                <span className="font-semibold text-slate-500">Status:</span>
                <span className="font-semibold text-sky-700">{selectedEvent.extendedProps?.status || 'Berjalan'}</span>
              </div>
            </div>

            <div className="mt-6 flex justify-end">
              <button
                onClick={() => setSelectedEvent(null)}
                className="px-4 py-2 text-sm bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium rounded-lg transition min-h-[44px]"
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
