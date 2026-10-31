import axios from 'axios';

const api = axios.create({
  baseURL: '/api',
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
  withCredentials: true,
});

// Interceptor untuk menangani CSRF cookie Sanctum jika diperlukan
export const getCsrfCookie = async () => {
  await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
};

// Response interceptor untuk redirect jika 401 unauthenticated
api.interceptors.response.use(
  (response) => response,
  (error) => {
    // Jangan lakukan hard redirect otomatis jika sedang berada di rute login
    // atau jika sedang mengecek endpoint /api/me saat inisialisasi awal
    const isLoginPath = window.location.pathname === '/login';
    const isMeRequest = error.config?.url?.includes('/me');

    if (error.response && error.response.status === 401 && !isLoginPath && !isMeRequest) {
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

export default api;

