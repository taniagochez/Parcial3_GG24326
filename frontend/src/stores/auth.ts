import { defineStore } from 'pinia'
import api from '@/services/api'
import type { LoginResponse, Role, User, VerifyResponse } from '@/types'

interface AuthState {
  token: string | null
  user: User | null
  mustChangePassword: boolean
  tempToken: string | null
  qr: string | null
}

export interface ChangePasswordPayload {
  current_password: string
  password: string
  password_confirmation: string
}

export const useAuthStore = defineStore('auth', {
  state: (): AuthState => ({
    token: localStorage.getItem('token'),
    user: JSON.parse(localStorage.getItem('user') || 'null') as User | null,
    mustChangePassword: localStorage.getItem('mcp') === '1',
    tempToken: null,
    qr: null,
  }),

  getters: {
    isAuth: (s): boolean => !!s.token,
    role: (s): Role | undefined => s.user?.role,
  },

  actions: {
    // Guarda la sesión final (token de acceso + usuario)
    guardarSesion(token: string, user: User, mustChange: boolean): void {
      this.tempToken = null
      this.qr = null
      this.token = token
      this.user = user
      this.mustChangePassword = mustChange
      localStorage.setItem('token', token)
      localStorage.setItem('user', JSON.stringify(user))
      localStorage.setItem('mcp', mustChange ? '1' : '0')
    },

    // Devuelve true si el usuario entró directo (ya había confirmado el 2FA)
    async login(email: string, password: string): Promise<boolean> {
      const { data } = await api.post<LoginResponse>('/login', { email, password })

      if (data.two_factor_omitido && data.user) {
        this.guardarSesion(data.token, data.user, !!data.must_change_password)
        return true
      }

      // Primer ingreso: token temporal + QR
      this.tempToken = data.token
      this.qr = data.requires_qr ? (data.qr ?? null) : null
      return false
    },

    async verify(code: string): Promise<void> {
      const { data } = await api.post<VerifyResponse>('/2fa/verify', { code })
      this.guardarSesion(data.token, data.user, data.must_change_password)
    },

    async changePassword(payload: ChangePasswordPayload): Promise<void> {
      await api.post('/change-password', payload)
      this.mustChangePassword = false
      localStorage.setItem('mcp', '0')
    },

    async logout(): Promise<void> {
      try {
        await api.post('/logout')
      } catch {
        /* la sesión ya había vencido */
      }
      this.clear()
    },

    clear(): void {
      this.token = null
      this.user = null
      this.tempToken = null
      this.qr = null
      this.mustChangePassword = false
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      localStorage.removeItem('mcp')
    },
  },
})