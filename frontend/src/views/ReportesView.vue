<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { Bar } from 'vue-chartjs'
import {
  Chart as ChartJS, CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend,
} from 'chart.js'
import api, { errorMsg } from '@/services/api'

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend)

const meses = [
  'Todos los meses', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
  'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
].map((title, i) => ({ title, value: i })) // 0 = todos los meses

const hoy = new Date()
const mes = ref<number>(hoy.getMonth() + 1)
const anio = ref<number>(hoy.getFullYear())
const anios = Array.from({ length: 5 }, (_, i) => hoy.getFullYear() - i)

const aprobados = ref(0)
const reprobados = ref(0)
const loading = ref(false)
const error = ref('')

async function cargar(): Promise<void> {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get<{ aprobados: number; reprobados: number }>('/grafico', {
      params: { mes: mes.value, anio: anio.value },
    })
    aprobados.value = data.aprobados
    reprobados.value = data.reprobados
  } catch (e) {
    error.value = errorMsg(e)
  } finally {
    loading.value = false
  }
}

const total = computed(() => aprobados.value + reprobados.value)
const nombreMes = computed(() => meses.find((m) => m.value === mes.value)?.title ?? '')

const chartData = computed(() => ({
  labels: ['Aprobados', 'Reprobados'],
  datasets: [
    {
      label: 'Cantidad de alumnos',
      data: [aprobados.value, reprobados.value],
      backgroundColor: ['#57CC99', '#EF5350'],
      borderRadius: 6,
      maxBarThickness: 60, // barras delgadas
      barPercentage: 0.5,
    },
  ],
}))

const chartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    title: {
      display: true,
      text: mes.value === 0
        ? `Alumnos aprobados y reprobados - Todo ${anio.value}`
        : `Alumnos aprobados y reprobados - ${nombreMes.value} ${anio.value}`,
      font: { size: 16 },
    },
  },
  scales: { y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } } },
}))

watch([mes, anio], cargar)
onMounted(cargar)
</script>

<template>
  <v-card class="page-card pa-4 mb-4" elevation="0" style="border: 2px solid #2D52A8">
    <h2 class="t6 text-secondary font-weight-bold text-center">Reporte de aprobados y reprobados</h2>
  </v-card>

  <v-card class="page-card pa-4" elevation="0">
    <v-row dense class="align-center">
      <v-col cols="12" sm="4">
        <v-select v-model="mes" :items="meses" label="Mes" hide-details />
      </v-col>
      <v-col cols="12" sm="3">
        <v-select v-model="anio" :items="anios" label="Año" hide-details />
      </v-col>
      <v-col cols="12" sm="5" class="d-flex ga-2 justify-end">
        <v-chip color="success" label class="font-weight-bold">Aprobados: {{ aprobados }}</v-chip>
        <v-chip color="error" label class="font-weight-bold">Reprobados: {{ reprobados }}</v-chip>
      </v-col>
    </v-row>

    <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mt-4">{{ error }}</v-alert>
    <v-progress-linear v-if="loading" indeterminate color="secondary" class="mt-4" />

    <div class="grafico mt-4">
      <Bar v-if="total > 0" :data="chartData" :options="chartOptions" />
      <p v-else-if="!loading" class="text-center text-medium-emphasis py-12">
        No hay notas registradas en {{ mes === 0 ? `el año ${anio}` : `${nombreMes} ${anio}` }}
      </p>
    </div>
  </v-card>
</template>

<style scoped>
.grafico { height: 380px; }
</style>