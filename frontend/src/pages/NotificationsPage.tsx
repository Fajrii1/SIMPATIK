import React, { useState, useEffect } from 'react';
import api from '../services/api';
import { Bell, CheckCheck, Loader2 } from 'lucide-react';
import { NotificationItem } from '../types';

export const NotificationsPage: React.FC = () => {
  const [notifications, setNotifications] = useState<NotificationItem[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchNotifications = async () => {
    setLoading(true);
    try {
      const res = await api.get('/notifications');
      setNotifications(res.data.data);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchNotifications();
  }, []);

  const handleMarkAsRead = async (id: number) => {
    try {
      await api.patch(`/notifications/${id}/read`);
      fetchNotifications();
    } catch (err) {
      console.error(err);
    }
  };

  const handleMarkAllAsRead = async () => {
    try {
      await api.post('/notifications/read-all');
      fetchNotifications();
    } catch (err) {
      console.error(err);
    }
  };

  return (
    <div className="space-y-6 max-w-3xl">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-xl font-bold text-slate-900">Pusat Notifikasi</h1>
          <p className="text-sm text-slate-500">Pemberitahuan perubahan penugasan, jadwal, dan kendala lapangan</p>
        </div>
        {notifications.some((n) => !n.is_read) && (
          <button
            onClick={handleMarkAllAsRead}
            className="flex items-center gap-1.5 text-xs font-semibold text-sky-600 hover:text-sky-700 bg-sky-50 px-3 py-1.5 rounded-lg border border-sky-100 transition"
          >
            <CheckCheck className="w-4 h-4" /> Tandai Semua Dibaca
          </button>
        )}
      </div>

      <div className="bg-white rounded-xl border border-slate-200 shadow-xs divide-y divide-slate-100 overflow-hidden">
        {loading ? (
          <div className="py-16 flex justify-center">
            <Loader2 className="w-8 h-8 animate-spin text-sky-600" />
          </div>
        ) : notifications.length === 0 ? (
          <div className="py-16 text-center text-slate-500 text-sm">
            Tidak ada notifikasi saat ini.
          </div>
        ) : (
          notifications.map((item) => (
            <div
              key={item.id}
              className={`p-4 flex items-start justify-between gap-4 transition ${
                item.is_read ? 'bg-white' : 'bg-sky-50/40'
              }`}
            >
              <div className="flex items-start gap-3">
                <div
                  className={`p-2 rounded-full mt-0.5 ${
                    item.is_read
                      ? 'bg-slate-100 text-slate-400'
                      : 'bg-sky-100 text-sky-700'
                  }`}
                >
                  <Bell className="w-4 h-4" />
                </div>
                <div>
                  <p className="text-sm text-slate-800 leading-snug">{item.pesan}</p>
                  <p className="text-xs text-slate-400 mt-1 font-mono">
                    {new Date(item.created_at).toLocaleString('id-ID')}
                  </p>
                </div>
              </div>

              {!item.is_read && (
                <button
                  onClick={() => handleMarkAsRead(item.id)}
                  className="text-xs text-sky-600 hover:text-sky-700 font-semibold shrink-0"
                >
                  Tandai Dibaca
                </button>
              )}
            </div>
          ))
        )}
      </div>
    </div>
  );
};

