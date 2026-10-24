<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { errorMsg } from '@/services/api'

const auth = useAuthStore()
const router = useRouter()

const form = ref<{ validate: () => Promise<{ valid: boolean }> } | null>(null)
const current = ref('')
const nueva = ref('')
const confirmar = ref('')
const show = ref<boolean[]>([false, false, false])
const loading = ref(false)
const error = ref('')

const required = (v: string) => !!v || 'Campo obligatorio'
const minLen = (v: string) => (!!v && v.length >= 8) || 'Mínimo 8 caracteres'
const different = (v: string) => v !== current.value || 'Debe ser distinta a la actual'
const same = (v: string) => v === nueva.value || 'Las contraseñas no coinciden'

async function guardar(): Promise<void> {
  error.value = ''
  const result = await form.value?.validate()
  if (!result?.valid) return
  loading.value = true
  try {
    await auth.changePassword({
      current_password: current.value,
      password: nueva.value,
      password_confirmation: confirmar.value,
    })
  } catch (e) {
    error.value = errorMsg(e)
  } finally {
    loading.value = false
  }
}

async function cerrar(): Promise<void> {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <v-dialog :model-value="auth.mustChangePassword" persistent max-width="720">
    <v-card rounded="lg">
      <v-toolbar color="secondary" density="compact" title="Cambio de contraseña" />
      <v-card-text class="pa-6">
        <v-row>
          <v-col cols="12" md="5" class="d-flex align-center justify-center">
            <v-icon icon="mdi-shield-lock" size="120" color="secondary" />
          </v-col>
          <v-col cols="12" md="7">
            <p class="sub2 mb-4">
              Por seguridad, debes cambiar tu contraseña temporal antes de continuar usando el sistema.
            </p>
            <v-form ref="form" @submit.prevent="guardar">
              <v-text-field
                v-model="current" label="Contraseña actual" :rules="[required]"
                :type="show[0] ? 'text' : 'password'"
                :append-inner-icon="show[0] ? 'mdi-eye-off' : 'mdi-eye'"
                @click:append-inner="show[0] = !show[0]"
              />
              <v-text-field
                v-model="nueva" label="Nueva contraseña" :rules="[required, minLen, different]"
                :type="show[1] ? 'text' : 'password'"
                :append-inner-icon="show[1] ? 'mdi-eye-off' : 'mdi-eye'"
                @click:append-inner="show[1] = !show[1]"
              />
              <v-text-field
                v-model="confirmar" label="Confirma nueva contraseña" :rules="[required, same]"
                :type="show[2] ? 'text' : 'password'"
                :append-inner-icon="show[2] ? 'mdi-eye-off' : 'mdi-eye'"
                @click:append-inner="show[2] = !show[2]"
              />
              <v-alert v-if="error" type="error" variant="tonal" density="compact">{{ error }}</v-alert>
            </v-form>
          </v-col>
        </v-row>
      </v-card-text>
      <v-card-actions class="bg-secondary justify-center pa-3">
        <v-btn variant="outlined" color="white" :loading="loading" @click="guardar">Guardar</v-btn>
        <v-btn variant="outlined" color="white" @click="cerrar">Cerrar</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>