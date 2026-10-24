<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
</script>

<template>
  <v-card class="page-card pa-6" elevation="0">
    <h2 class="t5">Bienvenido, {{ auth.user?.name }}</h2>
    <p class="sub1 mt-2">Rol: {{ auth.role }}</p>
  </v-card>
</template>