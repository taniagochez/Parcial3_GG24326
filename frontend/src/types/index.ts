export type Role = 'admin' | 'docente'

export interface Docente {
  id: number
  nombres: string
  apellidos: string
  telefono: string | null
  especialidad: string | null
}

export interface User {
  id: number
  name: string
  email: string
  role: Role
  estado: 'habilitado' | 'inhabilitado'
  must_change_password: boolean
  docente?: Docente | null
}

export interface LoginResponse {
  token: string
  requires_qr: boolean
  qr?: string
  user?: User
  must_change_password?: boolean
  two_factor_omitido?: boolean
}

export interface VerifyResponse {
  token: string
  user: User
  must_change_password: boolean
}
export interface DocenteRow extends Docente {
  user_id: number
  user: { id: number; email: string; estado: 'habilitado' | 'inhabilitado' }
}

export interface Paginated<T> {
  data: T[]
  current_page: number
  last_page: number
  total: number
  from: number | null
  to: number | null
}
export interface Ciclo {
  id: number
  nombre: string
  fecha_inicio: string
  fecha_fin: string
}

export interface Grupo {
  id: number
  nombre: string
  ciclo_id: number
  materia?: { id: number; nombre: string } | null
  ciclo?: Ciclo | null
}