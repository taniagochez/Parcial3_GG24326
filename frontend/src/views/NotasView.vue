<script setup lang="ts">
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import VuePdfEmbed from 'vue-pdf-embed'
import api from '@/services/api'
import type { Ciclo, Grupo } from '@/types'

const router = useRouter()

const ciclos = ref<Ciclo[]>([])
const grupos = ref<Grupo[]>([])
const cicloId = ref<number | null>(null)
const grupoId = ref<number | null>(null)

const pdfUrl = ref<string | null>(null)
const cargando = ref(false)
const error = ref('')

// Acepta tanto un arreglo como una respuesta paginada { data: [...] }
function lista<T>(payload: unknown): T[] {
  if (Array.isArray(payload)) return payload as T[]
  return ((payload as { data?: T[] })?.data ?? []) as T[]
}

const todosGrupos = ref<Grupo[]>([])

async function cargarCiclos(): Promise<void> {
  try {
    const { data } = await api.get('/mis-grupos')
    todosGrupos.value = lista<Grupo>(data)
    const mapa = new Map<number, Ciclo>()
    for (const g of todosGrupos.value) {
      if (g.ciclo) mapa.set(g.ciclo.id, g.ciclo)
    }
    ciclos.value = [...mapa.values()]
  } catch {
    error.value = 'No se pudieron cargar los ciclos'
  }
}

function cargarGrupos(): void {
  grupoId.value = null
  limpiarPdf()
  grupos.value = cicloId.value
    ? todosGrupos.value.filter((g) => g.ciclo_id === cicloId.value)
    : []
}

function limpiarPdf(): void {
  if (pdfUrl.value) URL.revokeObjectURL(pdfUrl.value)
  pdfUrl.value = null
}

async function cargarPdf(): Promise<void> {
  limpiarPdf()
  error.value = ''
  if (!grupoId.value) return
  cargando.value = true
  try {
    const { data } = await api.get(`/grupos/${grupoId.value}/notas/pdf`, {
      responseType: 'blob',
      headers: { Accept: 'application/json' },
    })
    pdfUrl.value = URL.createObjectURL(new Blob([data], { type: 'application/pdf' }))
  } catch (e: any) {
    const status = e?.response?.status
    error.value =
      status === 403 ? 'Este grupo no te pertenece'
      : status === 404 ? 'El grupo no existe'
      : 'No se pudo generar el PDF'
  } finally {
    cargando.value = false
  }
}

// Imprime el PDF cargado usando un iframe oculto
function imprimir(): void {
  if (!pdfUrl.value) return
  const iframe = document.createElement('iframe')
  iframe.style.display = 'none'
  iframe.src = pdfUrl.value
  iframe.onload = () => {
    iframe.contentWindow?.focus()
    iframe.contentWindow?.print()
    setTimeout(() => document.body.removeChild(iframe), 60000)
  }
  document.body.appendChild(iframe)
}

const descargando = ref(false)

async function descargar(formato: 'xlsx' | 'csv'): Promise<void> {
  if (!grupoId.value) return
  descargando.value = true
  error.value = ''
  try {
    const { data } = await api.get(`/grupos/${grupoId.value}/notas/excel`, {
      params: formato === 'csv' ? { formato: 'csv' } : undefined,
      responseType: 'blob',
      headers: { Accept: 'application/json' },
    })
    const url = URL.createObjectURL(data as Blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `notas-grupo-${grupoId.value}.${formato}`
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(url)
  } catch {
    error.value = 'No se pudo descargar el archivo'
  } finally {
    descargando.value = false
  }
}

watch(cicloId, cargarGrupos)
watch(grupoId, cargarPdf)

onMounted(cargarCiclos)
onBeforeUnmount(limpiarPdf)
</script>

<template>
  <!-- Encabezado -->
  <v-card class="page-card pa-4 mb-4" elevation="0" style="border: 2px solid #2D52A8">
    <h2 class="t6 text-secondary font-weight-bold text-center">Escritura - Impresión</h2>
  </v-card>

  <v-card class="page-card pa-4" elevation="0">
    <!-- Selectores -->
    <v-row dense class="align-center">
      <v-col cols="12" sm="4">
        <v-select
          v-model="cicloId" :items="ciclos" item-title="nombre" item-value="id"
          label="Ciclo" hide-details
        />
      </v-col>
      <v-col cols="12" sm="4">
        <v-select
          v-model="grupoId" :items="grupos" item-title="nombre" item-value="id"
          label="Grupo" :disabled="!cicloId" hide-details
        />
      </v-col>
            <v-col cols="12" sm="4" class="d-flex ga-2 justify-end flex-wrap">
        <v-btn color="error" prepend-icon="mdi-printer" :disabled="!pdfUrl" @click="imprimir">
          Imprimir
        </v-btn>
        <v-btn
          color="success" prepend-icon="mdi-file-excel" :disabled="!grupoId"
          :loading="descargando" @click="descargar('xlsx')"
        >
          Excel
        </v-btn>
        <v-btn
          color="secondary" prepend-icon="mdi-file-delimited" :disabled="!grupoId"
          :loading="descargando" @click="descargar('csv')"
        >
          CSV
        </v-btn>
        <v-btn color="grey" @click="router.push('/')">Regresar</v-btn>
      </v-col>
    </v-row>

    <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mt-4">{{ error }}</v-alert>

    <!-- Visor PDF -->
    <v-progress-linear v-if="cargando" indeterminate color="secondary" class="mt-4" />
    <div class="visor mt-4">
      <VuePdfEmbed v-if="pdfUrl" :source="pdfUrl" />
      <p v-else-if="!cargando" class="text-center text-medium-emphasis py-12">
        Selecciona un ciclo y un grupo para ver el cuadro de notas
      </p>
    </div>
  </v-card>
</template>

<style scoped>
.visor {
  min-height: 60vh;
  max-height: 75vh;
  overflow: auto;
  background: #f4f7fd;
  border-radius: 8px;
}
</style>