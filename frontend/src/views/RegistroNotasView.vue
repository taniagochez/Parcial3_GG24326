<script setup lang="ts">
import { ref, reactive, watch, onMounted } from 'vue'
import api, { errorMsg } from '@/services/api'
import type { Ciclo, Grupo, Paginated } from '@/types'

interface FilaNota {
  estudiante_id: number
  carnet: string
  nombre: string
  nota1: number | null
  nota2: number | null
  nota3: number | null
  promedio: number | null
  estado: 'aprobado' | 'reprobado' | null
}

const PER_PAGE = 5

// ---- selectores (ciclo y grupo salen de /mis-grupos) ----
const todosGrupos = ref<Grupo[]>([])
const ciclos = ref<Ciclo[]>([])
const grupos = ref<Grupo[]>([])
const cicloId = ref<number | null>(null)
const grupoId = ref<number | null>(null)

function lista<T>(payload: unknown): T[] {
  if (Array.isArray(payload)) return payload as T[]
  return ((payload as { data?: T[] })?.data ?? []) as T[]
}

async function cargarCiclos(): Promise<void> {
  try {
    const { data } = await api.get('/mis-grupos')
    todosGrupos.value = lista<Grupo>(data)
    const mapa = new Map<number, Ciclo>()
    for (const g of todosGrupos.value) if (g.ciclo) mapa.set(g.ciclo.id, g.ciclo)
    ciclos.value = [...mapa.values()]
  } catch (e) {
    avisar(errorMsg(e), 'error')
  }
}

watch(cicloId, () => {
  grupoId.value = null
  filas.value = []
  grupos.value = cicloId.value ? todosGrupos.value.filter((g) => g.ciclo_id === cicloId.value) : []
})

// ---- listado paginado ----
const filas = ref<FilaNota[]>([])
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const loading = ref(false)
const buscar = ref('')

async function cargar(): Promise<void> {
  if (!grupoId.value) return
  loading.value = true
  try {
    const { data } = await api.get<Paginated<FilaNota>>(`/grupos/${grupoId.value}/notas`, {
      params: { page: page.value, per_page: PER_PAGE, buscar: buscar.value || undefined },
    })
    filas.value = data.data
    lastPage.value = data.last_page
    total.value = data.total
  } catch (e) {
    avisar(errorMsg(e), 'error')
  } finally {
    loading.value = false
  }
}

watch(grupoId, () => {
  page.value = 1
  buscar.value = ''
  filas.value = []
  cargar()
})

function filtrar(): void {
  page.value = 1
  cargar()
}

// ---- edición de notas ----
type FormRef = { validate: () => Promise<{ valid: boolean }>; resetValidation: () => void }

const dialog = ref(false)
const formRef = ref<FormRef | null>(null)
const guardando = ref(false)
const errorForm = ref('')
const actual = ref<FilaNota | null>(null)
const form = reactive({ nota1: '' as string | number, nota2: '' as string | number, nota3: '' as string | number })

const notaRegla = (v: string | number) => {
  if (v === '' || v === null) return 'Campo obligatorio'
  const n = Number(v)
  if (Number.isNaN(n)) return 'Debe ser un número'
  return (n >= 0 && n <= 10) || 'La nota debe estar entre 0 y 10'
}

function abrir(f: FilaNota): void {
  actual.value = f
  errorForm.value = ''
  form.nota1 = f.nota1 ?? ''
  form.nota2 = f.nota2 ?? ''
  form.nota3 = f.nota3 ?? ''
  dialog.value = true
  formRef.value?.resetValidation()
}

async function guardar(): Promise<void> {
  errorForm.value = ''
  const r = await formRef.value?.validate()
  if (!r?.valid || !actual.value || !grupoId.value) return
  guardando.value = true
  try {
    await api.put(`/grupos/${grupoId.value}/notas/${actual.value.estudiante_id}`, {
      nota1: Number(form.nota1),
      nota2: Number(form.nota2),
      nota3: Number(form.nota3),
    })
    avisar('Nota guardada', 'success')
    dialog.value = false
    await cargar()
  } catch (e) {
    errorForm.value = errorMsg(e)
  } finally {
    guardando.value = false
  }
}

// ---- notificaciones ----
const snack = reactive({ show: false, texto: '', color: 'success' })
function avisar(texto: string, color: 'success' | 'error' | 'warning'): void {
  Object.assign(snack, { show: true, texto, color })
}

onMounted(cargarCiclos)
</script>

<template>
  <v-card class="page-card pa-4 mb-4" elevation="0" style="border: 2px solid #2D52A8">
    <h2 class="t6 text-secondary font-weight-bold text-center">Registro de notas</h2>
  </v-card>

  <v-card class="page-card pa-4" elevation="0">
    <v-row dense class="align-center">
      <v-col cols="12" sm="4">
        <v-select v-model="cicloId" :items="ciclos" item-title="nombre" item-value="id" label="Ciclo" hide-details />
      </v-col>
      <v-col cols="12" sm="4">
        <v-select
          v-model="grupoId" :items="grupos" item-title="nombre" item-value="id"
          label="Grupo" :disabled="!cicloId" hide-details
        />
      </v-col>
      <v-col cols="12" sm="4">
        <v-text-field
          v-model="buscar" label="Nombre o carnet" clearable hide-details
          :disabled="!grupoId" @keyup.enter="filtrar" @click:clear="filtrar"
        />
      </v-col>
    </v-row>

    <v-progress-linear v-if="loading" indeterminate color="secondary" class="mt-4" />

    <v-table class="mt-4" density="comfortable">
      <thead>
        <tr class="bg-secondary">
          <th class="text-white">Carnet</th>
          <th class="text-white">Estudiante</th>
          <th class="text-white text-center">Nota 1</th>
          <th class="text-white text-center">Nota 2</th>
          <th class="text-white text-center">Nota 3</th>
          <th class="text-white text-center">Promedio</th>
          <th class="text-white text-center">Estado</th>
          <th class="text-white text-center">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="f in filas" :key="f.estudiante_id">
          <td>{{ f.carnet }}</td>
          <td>{{ f.nombre }}</td>
          <td class="text-center">{{ f.nota1 ?? '—' }}</td>
          <td class="text-center">{{ f.nota2 ?? '—' }}</td>
          <td class="text-center">{{ f.nota3 ?? '—' }}</td>
          <td class="text-center font-weight-bold">{{ f.promedio ?? '—' }}</td>
          <td class="text-center">
            <v-chip
              v-if="f.estado" :color="f.estado === 'aprobado' ? 'success' : 'error'"
              size="small" label class="font-weight-bold"
            >
              {{ f.estado === 'aprobado' ? 'Aprobado' : 'Reprobado' }}
            </v-chip>
            <v-chip v-else size="small" label>Sin nota</v-chip>
          </td>
          <td class="text-center">
            <v-btn icon="mdi-pencil" size="small" variant="text" color="secondary" title="Registrar / editar nota" @click="abrir(f)" />
          </td>
        </tr>
        <tr v-if="!loading && filas.length === 0">
          <td colspan="8" class="text-center text-medium-emphasis py-6">
            {{ grupoId ? 'No se encontraron estudiantes' : 'Selecciona un ciclo y un grupo' }}
          </td>
        </tr>
      </tbody>
    </v-table>

    <div class="d-flex align-center justify-space-between flex-wrap mt-4">
      <span class="sub2 text-medium-emphasis">{{ total }} registro(s) · {{ PER_PAGE }} por página</span>
      <v-pagination
        v-model="page" :length="lastPage" :total-visible="5"
        density="comfortable" color="secondary" rounded="circle"
        @update:model-value="cargar"
      />
    </div>
  </v-card>

  <!-- Formulario de notas -->
  <v-dialog v-model="dialog" max-width="480" persistent>
    <v-card rounded="lg">
      <v-toolbar color="secondary" density="compact" title="Registrar notas" />
      <v-card-text class="pa-6">
        <p class="mb-1 font-weight-bold">{{ actual?.nombre }}</p>
        <p class="sub2 text-medium-emphasis mb-4">Carnet: {{ actual?.carnet }}</p>
        <v-form ref="formRef" @submit.prevent="guardar">
          <v-row dense>
            <v-col cols="4">
              <v-text-field v-model="form.nota1" label="Nota 1" type="number" min="0" max="10" step="0.1" :rules="[notaRegla]" />
            </v-col>
            <v-col cols="4">
              <v-text-field v-model="form.nota2" label="Nota 2" type="number" min="0" max="10" step="0.1" :rules="[notaRegla]" />
            </v-col>
            <v-col cols="4">
              <v-text-field v-model="form.nota3" label="Nota 3" type="number" min="0" max="10" step="0.1" :rules="[notaRegla]" />
            </v-col>
          </v-row>
          <p class="sub2 text-medium-emphasis">El promedio y el estado (aprobado desde 6.0) se calculan automáticamente.</p>
          <v-alert v-if="errorForm" type="error" variant="tonal" density="compact" class="mt-2">{{ errorForm }}</v-alert>
        </v-form>
      </v-card-text>
      <v-card-actions class="bg-secondary justify-center pa-3">
        <v-btn variant="outlined" color="white" :loading="guardando" @click="guardar">Guardar</v-btn>
        <v-btn variant="outlined" color="white" @click="dialog = false">Cancelar</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-snackbar v-model="snack.show" :color="snack.color" timeout="4000" location="top right">
    <span :class="snack.color === 'warning' ? 'text-black' : ''">{{ snack.texto }}</span>
  </v-snackbar>
</template>