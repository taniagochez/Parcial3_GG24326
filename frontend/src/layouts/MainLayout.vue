<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import ChangePasswordDialog from '@/components/ChangePasswordDialog.vue'

interface MenuItem {
  title: string
  icon: string
  to: string
}

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const rail = ref(true)

const menu = computed<MenuItem[]>(() => {
  const items: MenuItem[] = [{ title: 'Inicio', icon: 'mdi-home', to: '/' }]
  if (auth.role === 'admin') {
    items.push({ title: 'Docentes', icon: 'mdi-account-tie', to: '/docentes' })
  }
    items.push(
    { title: 'Registro de notas', icon: 'mdi-pencil-box', to: '/notas/registro' },
    { title: 'Impresión de notas', icon: 'mdi-printer', to: '/notas' },
    { title: 'Reportes', icon: 'mdi-chart-bar', to: '/reportes' },
  )
  return items
})

async function salir(): Promise<void> {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <v-navigation-drawer permanent :rail="rail" color="sidebar" width="240">
    <v-list nav density="comfortable">
      <v-list-item prepend-icon="mdi-menu" @click="rail = !rail" />
      <v-list-item
        v-for="m in menu" :key="m.to"
        :prepend-icon="m.icon" :title="m.title" :to="m.to" rounded="lg"
      />
    </v-list>
        <template #append>
      <v-list nav density="comfortable">
        <v-list-item prepend-icon="mdi-logout" title="Cerrar sesión" rounded="lg" @click="salir" />
      </v-list>
    </template>
  </v-navigation-drawer>

  <v-app-bar flat color="surface" class="mx-4 mt-2" style="border-radius: 12px; width: auto">
    <v-app-bar-title class="t5 mr-8" style="flex: none">{{ route.meta.title }}</v-app-bar-title>
<span class="sub2 mr-4 d-none d-sm-inline">{{ auth.user?.name }}</span>
    <v-menu>
      <template #activator="{ props }">
        <v-btn v-bind="props" icon="mdi-account-circle" variant="text" color="secondary" />
      </template>
      <v-list>
        <v-list-item prepend-icon="mdi-logout" title="Cerrar sesión" @click="salir" />
      </v-list>
    </v-menu>
  </v-app-bar>

  <v-main class="bg-background">
    <v-container fluid class="pa-4">
      <router-view />
    </v-container>
  </v-main>

  <ChangePasswordDialog />
</template>