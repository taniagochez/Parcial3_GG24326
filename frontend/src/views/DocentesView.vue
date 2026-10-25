<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue'
import api, { errorMsg } from '@/services/api'
import type { DocenteRow, Paginated } from '@/types'

type Modo = 'crear' | 'editar' | 'ver'
type FormRef = { validate: () => Promise<{ valid: boolean }>; resetValidation: () => void }

const PER_PAGE = 5

// ---- listado y filtros ----
const docentes = ref<DocenteRow[]>([])
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const loading = ref(false)
const filtros = reactive({ nombre: '', estado: null as string | null })
const estados = [
  { title: 'Habilitado', value: 'habilitado' },
  { title: 'Inhabilitado', value: 'inhabilitado' },
]

async function cargar(): Promise<void> {
  loading.value = true
  try {
    const { data } = await api.get<Paginated<DocenteRow>>('/docentes', {
      params: {
        page: page.value,
        per_page: PER_PAGE,
        nombre: filtros.nombre || undefined,
        estado: filtros.estado || undefined,
      },
    })
    docentes.value = data.data
    lastPage.value = data.last_page
    total.value = data.total
    // si se borró el último registro de la página, retrocede
    if (page.value > data.last_page && data.last_page > 0) {
      page.value = data.last_page
      await cargar()
    }
  } catch (e) {
    avisar(errorMsg(e), 'error')
  } finally {
    loading.value = false
  }
}

function filtrar(): void {
  page.value = 1
  cargar()
}

function limpiar(): void {
  filtros.nombre = ''
  filtros.estado = null
  filtrar()
}

// ---- formulario (crear / editar / ver) ----
const dialog = ref(false)
const modo = ref<Modo>('crear')
const editId = ref<number | null>(null)
const formRef = ref<FormRef | null>(null)
const guardando = ref(false)
const errorForm = ref('')
const form = reactive({ nombres: '', apellidos: '', email: '', telefono: '', especialidad: '' })

const requerido = (v: string) => !!v?.trim() || 'Campo obligatorio'
const correo = (v: string) => /.+@.+\..+/.test(v) || 'El correo no es válido'
const telefono = (v: string) => !v || /^[0-9+\-\s]{8,15}$/.test(v) || 'Teléfono no válido (8 a 15 dígitos)'
const soloLetras = (v: string) => /^[\p{L}\s'.-]+$/u.test(v) || 'Solo se permiten letras'

function abrir(m: Modo, d?: DocenteRow): void {
  modo.value = m
  errorForm.value = ''
  editId.value = d?.id ?? null
  form.nombres = d?.nombres ?? ''
  form.apellidos = d?.apellidos ?? ''
  form.email = d?.user.email ?? ''
  form.telefono = d?.telefono ?? ''
  form.especialidad = d?.especialidad ?? ''
  dialog.value = true
  formRef.value?.resetValidation()
}

async function guardar(): Promise<void> {
  errorForm.value = ''
  const r = await formRef.value?.validate()
  if (!r?.valid) return
  guardando.value = true
  try {
    if (modo.value === 'crear') {
      const { data } = await api.post<{ correo_enviado: boolean }>('/docentes', form)
      avisar(
        data.correo_enviado
          ? 'Docente registrado. Se enviaron las credenciales por correo.'
          : 'Docente registrado, pero no se pudo enviar el correo. Usa "Reenviar credenciales".',
        data.correo_enviado ? 'success' : 'warning',
      )
      page.value = 1
    } else {
      await api.put(`/docentes/${editId.value}`, form)
      avisar('Docente actualizado', 'success')
    }
    dialog.value = false
    await cargar()
  } catch (e) {
    errorForm.value = errorMsg(e)
  } finally {
    guardando.value = false
  }
}

// ---- confirmaciones (eliminar, estado, reenviar) ----
const confirmar = reactive({
  show: false, titulo: '', texto: '', color: 'secondary', boton: 'Aceptar', cargando: false,
  accion: null as null | (() => Promise<void>),
})

function pedir(titulo: string, texto: string, boton: string, color: string, accion: () => Promise<void>): void {
  Object.assign(confirmar, { show: true, titulo, texto, boton, color, accion })
}

async function ejecutar(): Promise<void> {
  if (!confirmar.accion) return
  confirmar.cargando = true
  try {
    await confirmar.accion()
  } finally {
    confirmar.cargando = false
    confirmar.show = false
  }
}

function toggleEstado(d: DocenteRow): void {
  const inhabilitar = d.user.estado === 'habilitado'
  pedir(
    inhabilitar ? 'Inhabilitar docente' : 'Habilitar docente',
    `¿Deseas ${inhabilitar ? 'inhabilitar' : 'habilitar'} a ${d.nombres} ${d.apellidos}?`,
    inhabilitar ? 'Inhabilitar' : 'Habilitar',
    inhabilitar ? 'error' : 'success',
    async () => {
      try {
        await api.patch(`/docentes/${d.id}/estado`)
        avisar('Estado actualizado', 'success')
        await cargar()
      } catch (e) {
        avisar(errorMsg(e), 'error')
      }
    },
  )
}

function eliminar(d: DocenteRow): void {
  pedir('Eliminar docente', `¿Eliminar definitivamente a ${d.nombres} ${d.apellidos}?`, 'Eliminar', 'error', async () => {
    try {
      await api.delete(`/docentes/${d.id}`)
      avisar('Docente eliminado', 'success')
      await cargar()
    } catch (e) {
      avisar(errorMsg(e), 'error')
    }
  })
}

function reenviar(d: DocenteRow): void {
  pedir(
    'Reenviar credenciales',
    `Se generará una contraseña temporal nueva y se enviará a ${d.user.email}.`,
    'Enviar', 'secondary',
    async () => {
      try {
        await api.post(`/docentes/${d.id}/reenviar`)
        avisar('Correo enviado', 'success')
      } catch (e) {
        avisar(errorMsg(e), 'error')
      }
    },
  )
}

// ---- asignar grupo a docente ----
interface GrupoAsignable {
  id: number
  nombre: string
  ciclo_id: number
  materia_id: number
  docente_id: number | null
  ciclo?: { id: number; nombre: string } | null
  materia?: { id: number; nombre: string } | null
  docente?: { id: number; nombres: string; apellidos: string } | null
}

const dialogAsignar = ref(false)
const asignarFormRef = ref<FormRef | null>(null)
const asignando = ref(false)
const errorAsignar = ref('')
const todosDocentes = ref<DocenteRow[]>([])
const todosGrupos = ref<GrupoAsignable[]>([])
const asignar = reactive({
  docenteId: null as number | null,
  cicloId: null as number | null,
  grupoId: null as number | null,
})

const modoAsignar = ref<'asignar' | 'quitar'>('asignar')

const docentesAsignar = computed(() =>
  modoAsignar.value === 'asignar'
    ? todosDocentes.value.filter((d) => d.user.estado === 'habilitado')
    : todosDocentes.value,
)

// Grupos disponibles según el modo: al quitar, solo los que ya tiene el docente
const gruposBase = computed(() =>
  modoAsignar.value === 'quitar'
    ? todosGrupos.value.filter((g) => g.docente_id === asignar.docenteId)
    : todosGrupos.value,
)

const ciclosAsignar = computed(() => {
  const mapa = new Map<number, string>()
  for (const g of gruposBase.value) if (g.ciclo) mapa.set(g.ciclo.id, g.ciclo.nombre)
  return [...mapa].map(([id, nombre]) => ({ id, nombre }))
})

const gruposAsignar = computed(() => {
  const delCiclo = gruposBase.value.filter((g) => g.ciclo_id === asignar.cicloId)
  const items = delCiclo.map((g) => ({
    id: g.id,
    titulo: `${g.nombre}${g.materia ? ' · ' + g.materia.nombre : ''}`,
    subtitulo: g.docente ? `Actual: ${g.docente.nombres} ${g.docente.apellidos}` : 'Sin docente',
  }))
  if (modoAsignar.value === 'quitar' && items.length > 0) {
    items.unshift({
      id: 0,
      titulo: 'Todos los grupos del ciclo',
      subtitulo: `${items.length} grupo(s) se quitarán`,
    })
  }
  return items
})

watch(modoAsignar, () => {
  Object.assign(asignar, { docenteId: null, cicloId: null, grupoId: null })
  errorAsignar.value = ''
  asignarFormRef.value?.resetValidation()
})
watch(
  () => asignar.docenteId,
  () => {
    asignar.cicloId = null
    asignar.grupoId = null
  },
)
watch(
  () => asignar.cicloId,
  () => {
    asignar.grupoId = null
  },
)

async function abrirAsignar(): Promise<void> {
  errorAsignar.value = ''
  modoAsignar.value = 'asignar'
  Object.assign(asignar, { docenteId: null, cicloId: null, grupoId: null })
  dialogAsignar.value = true
  asignarFormRef.value?.resetValidation()
  try {
    const [d, g] = await Promise.all([
      api.get<Paginated<DocenteRow>>('/docentes', { params: { per_page: 200 } }),
      api.get<Paginated<GrupoAsignable>>('/grupos', { params: { per_page: 200 } }),
    ])
    todosDocentes.value = d.data.data
    todosGrupos.value = g.data.data
  } catch (e) {
    errorAsignar.value = errorMsg(e)
  }
}

async function guardarAsignacion(): Promise<void> {
  errorAsignar.value = ''
  const r = await asignarFormRef.value?.validate()
  if (!r?.valid) return
  const doc = todosDocentes.value.find((d) => d.id === asignar.docenteId)
  asignando.value = true
  try {
    if (modoAsignar.value === 'quitar') {
      const { data } = await api.post<{ quitados: number }>(`/docentes/${asignar.docenteId}/quitar-grupos`, {
        ciclo_id: asignar.cicloId,
        grupo_id: asignar.grupoId || null,
      })
      avisar(`Se quitaron ${data.quitados} grupo(s) a ${doc?.nombres} ${doc?.apellidos}`, 'success')
    } else {
      const grupo = todosGrupos.value.find((g) => g.id === asignar.grupoId)
      if (!grupo) return
      await api.put(`/grupos/${grupo.id}`, {
        nombre: grupo.nombre,
        ciclo_id: grupo.ciclo_id,
        materia_id: grupo.materia_id,
        docente_id: asignar.docenteId,
      })
      avisar(`Grupo "${grupo.nombre}" asignado a ${doc?.nombres} ${doc?.apellidos}`, 'success')
    }
    dialogAsignar.value = false
  } catch (e) {
    errorAsignar.value = errorMsg(e)
  } finally {
    asignando.value = false
  }
}

// ---- notificaciones ----
const snack = reactive({ show: false, texto: '', color: 'success' })
function avisar(texto: string, color: 'success' | 'error' | 'warning'): void {
  Object.assign(snack, { show: true, texto, color })
}

onMounted(cargar)
</script>

<template>
  <!-- Encabezado -->
  <v-card class="page-card pa-4 mb-4" elevation="0" style="border: 2px solid #2D52A8">
    <div class="d-flex align-center flex-wrap ga-3">
      <h2 class="t6 text-secondary font-weight-bold flex-grow-1 text-center">Listado de docentes</h2>
    </div>
  </v-card>

  <v-card class="page-card pa-4" elevation="0">
    <div class="d-flex justify-end mb-4">
      <v-btn color="secondary" prepend-icon="mdi-plus" @click="abrir('crear')">Registrar nuevo</v-btn>
    </div>

    <!-- Filtros -->
    <v-row dense class="align-center">
      <v-col cols="12" sm="5">
        <v-text-field
          v-model="filtros.nombre" label="Nombre o correo" clearable hide-details
          @keyup.enter="filtrar"
        />
      </v-col>
      <v-col cols="12" sm="4">
        <v-select v-model="filtros.estado" :items="estados" label="Estado" clearable hide-details />
      </v-col>
      <v-col cols="12" sm="3" class="d-flex ga-2 justify-end">
        <v-btn color="secondary" @click="filtrar">Filtrar</v-btn>
        <v-btn color="secondary" variant="outlined" prepend-icon="mdi-account-switch" @click="abrirAsignar">Asignar</v-btn>
      </v-col>
    </v-row>

    <!-- Tabla -->
    <v-progress-linear v-if="loading" indeterminate color="secondary" class="mt-4" />
    <v-table class="mt-4" density="comfortable">
      <thead>
        <tr class="bg-secondary">
          <th class="text-white">Nombre</th>
          <th class="text-white">Correo</th>
          <th class="text-white">Teléfono</th>
          <th class="text-white">Especialidad</th>
          <th class="text-white">Estado</th>
          <th class="text-white text-center">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="d in docentes" :key="d.id">
          <td>{{ d.nombres }} {{ d.apellidos }}</td>
          <td>{{ d.user.email }}</td>
          <td>{{ d.telefono || '—' }}</td>
          <td>{{ d.especialidad || '—' }}</td>
          <td>
            <v-chip
              :color="d.user.estado === 'habilitado' ? 'success' : 'error'"
              size="small" label class="font-weight-bold"
            >
              {{ d.user.estado === 'habilitado' ? 'Habilitado' : 'Inhabilitado' }}
            </v-chip>
          </td>
          <td class="text-center text-no-wrap">
            <v-btn icon="mdi-eye" size="small" variant="text" color="secondary" title="Ver" @click="abrir('ver', d)" />
            <v-btn icon="mdi-pencil" size="small" variant="text" color="secondary" title="Editar" @click="abrir('editar', d)" />
            <v-btn icon="mdi-email-fast" size="small" variant="text" color="secondary" title="Reenviar credenciales" @click="reenviar(d)" />
            <v-btn
              :icon="d.user.estado === 'habilitado' ? 'mdi-cancel' : 'mdi-check-circle'"
              size="small" variant="text" color="secondary"
              :title="d.user.estado === 'habilitado' ? 'Inhabilitar' : 'Habilitar'"
              @click="toggleEstado(d)"
            />
            <v-btn icon="mdi-delete" size="small" variant="text" color="error" title="Eliminar" @click="eliminar(d)" />
          </td>
        </tr>
        <tr v-if="!loading && docentes.length === 0">
          <td colspan="6" class="text-center text-medium-emphasis py-6">No se encontraron docentes</td>
        </tr>
      </tbody>
    </v-table>

    <!-- Paginación -->
    <div class="d-flex align-center justify-end flex-wrap ga-4 mt-4">
      <div class="d-flex align-center ga-4">
        <span class="sub2 text-medium-emphasis">{{ total }} registro(s) · {{ PER_PAGE }} por página</span>
        <v-btn color="grey" size="small" prepend-icon="mdi-filter-remove" @click="limpiar">Limpiar</v-btn>
      </div>
      <v-pagination
        v-model="page" :length="lastPage" :total-visible="5"
        density="comfortable" color="secondary" rounded="circle"
        @update:model-value="cargar"
      />
    </div>
  </v-card>

  <!-- Formulario crear / editar / ver -->
  <v-dialog v-model="dialog" max-width="560" persistent>
    <v-card rounded="lg">
      <v-toolbar color="secondary" density="compact"
        :title="modo === 'crear' ? 'Registrar docente' : modo === 'editar' ? 'Editar docente' : 'Detalle del docente'" />
      <v-card-text class="pa-6">
        <v-form ref="formRef" @submit.prevent="guardar">
          <v-row dense>
            <v-col cols="12" sm="6">
              <v-text-field v-model="form.nombres" label="Nombres" :readonly="modo === 'ver'" :rules="[requerido, soloLetras]" />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field v-model="form.apellidos" label="Apellidos" :readonly="modo === 'ver'" :rules="[requerido, soloLetras]" />
            </v-col>
            <v-col cols="12">
              <v-text-field v-model="form.email" label="Correo electrónico" :readonly="modo === 'ver'" :rules="[requerido, correo]" />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field v-model="form.telefono" label="Teléfono" :readonly="modo === 'ver'" :rules="[telefono]" />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field v-model="form.especialidad" label="Especialidad" :readonly="modo === 'ver'" />
            </v-col>
          </v-row>
          <p v-if="modo === 'crear'" class="sub2 text-medium-emphasis">
            Se generará una contraseña temporal y se enviará por correo al docente.
          </p>
          <v-alert v-if="errorForm" type="error" variant="tonal" density="compact" class="mt-2">{{ errorForm }}</v-alert>
        </v-form>
      </v-card-text>
      <v-card-actions class="bg-secondary justify-center pa-3">
        <v-btn v-if="modo !== 'ver'" variant="outlined" color="white" :loading="guardando" @click="guardar">Guardar</v-btn>
        <v-btn variant="outlined" color="white" @click="dialog = false">{{ modo === 'ver' ? 'Cerrar' : 'Cancelar' }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- Asignar / quitar grupos a docente -->
  <v-dialog v-model="dialogAsignar" max-width="520" persistent>
    <v-card rounded="lg">
      <v-toolbar
        color="secondary" density="compact"
        :title="modoAsignar === 'asignar' ? 'Asignar grupo a docente' : 'Quitar grupos a docente'"
      />
      <v-card-text class="pa-6">
        <v-btn-toggle v-model="modoAsignar" mandatory divided color="secondary" density="comfortable" class="mb-4">
          <v-btn value="asignar" prepend-icon="mdi-account-plus">Asignar</v-btn>
          <v-btn value="quitar" prepend-icon="mdi-account-minus">Quitar</v-btn>
        </v-btn-toggle>

        <v-form ref="asignarFormRef" @submit.prevent="guardarAsignacion">
          <v-select
            v-model="asignar.docenteId" :items="docentesAsignar" item-value="id"
            :item-title="(d: DocenteRow) => `${d.nombres} ${d.apellidos}`"
            label="Docente" :rules="[(v: number | null) => !!v || 'Selecciona un docente']"
          />
          <v-select
            v-model="asignar.cicloId" :items="ciclosAsignar" item-title="nombre" item-value="id"
            label="Ciclo" :disabled="!asignar.docenteId"
            :no-data-text="modoAsignar === 'quitar' ? 'Este docente no tiene grupos asignados' : 'Sin ciclos'"
            :rules="[(v: number | null) => !!v || 'Selecciona un ciclo']"
          />
          <v-select
            v-model="asignar.grupoId" :items="gruposAsignar" item-value="id" item-title="titulo"
            :item-props="(g: { titulo: string; subtitulo: string }) => ({ title: g.titulo, subtitle: g.subtitulo })"
            label="Grupo" :disabled="!asignar.cicloId"
            :rules="[(v: number | null) => v !== null || 'Selecciona un grupo']"
          />
          <p class="sub2 text-medium-emphasis">
            {{
              modoAsignar === 'asignar'
                ? 'Todos los estudiantes y notas del grupo pasarán al docente seleccionado.'
                : 'Los grupos quedarán sin docente. Los estudiantes y sus notas no se borran; podrás asignarlos a otro docente después.'
            }}
          </p>
          <v-alert v-if="errorAsignar" type="error" variant="tonal" density="compact" class="mt-2">{{ errorAsignar }}</v-alert>
        </v-form>
      </v-card-text>
      <v-card-actions class="bg-secondary justify-center pa-3">
        <v-btn variant="outlined" color="white" :loading="asignando" @click="guardarAsignacion">
          {{ modoAsignar === 'asignar' ? 'Guardar' : 'Quitar' }}
        </v-btn>
        <v-btn variant="outlined" color="white" @click="dialogAsignar = false">Cancelar</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- Confirmación -->
  <v-dialog v-model="confirmar.show" max-width="420">
    <v-card rounded="lg" class="pa-2">
      <v-card-title class="t6">{{ confirmar.titulo }}</v-card-title>
      <v-card-text>{{ confirmar.texto }}</v-card-text>
      <v-card-actions class="justify-end">
        <v-btn variant="text" @click="confirmar.show = false">Cancelar</v-btn>
        <v-btn :color="confirmar.color" :loading="confirmar.cargando" @click="ejecutar">{{ confirmar.boton }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-snackbar v-model="snack.show" :color="snack.color" timeout="4000" location="top right">
    <span :class="snack.color === 'warning' ? 'text-black' : ''">{{ snack.texto }}</span>
  </v-snackbar>
</template>