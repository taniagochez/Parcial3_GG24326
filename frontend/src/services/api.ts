import axios, { AxiosError } from 'axios'
import { useAuthStore } from '@/stores/auth'
import router from '@/router'

export interface ApiError {
  message?: string
  errors?: Record<string, string[]>
}

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api',
  headers: { Accept: 'application/json' },
})

api.interceptors.request.use((cfg) => {
  const auth = useAuthStore()
  const token = auth.tempToken || auth.token
  if (token) cfg.headers.Authorization = `Bearer ${token}`
  return cfg
})

api.interceptors.response.use(
  (r) => r,
  (err: AxiosError<ApiError>) => {
    const url = err.config?.url ?? ''
    if (err.response?.status === 401 && !url.includes('/login')) {
      useAuthStore().clear()
      router.push('/login')
    }
    return Promise.reject(err)
  },
)

export function errorMsg(e: unknown, fallback = 'No se pudo conectar con el servidor'): string {
  if (axios.isAxiosError<ApiError>(e)) {
    const data = e.response?.data
    if (data?.errors) return Object.values(data.errors).flat().join(' ')
    if (data?.message) return data.message
  }
  return fallback
}

export default api