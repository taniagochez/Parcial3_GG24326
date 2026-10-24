<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { errorMsg } from '@/services/api'

const auth = useAuthStore()
const router = useRouter()

type Step = 'credentials' | 'code'
const step = ref<Step>('credentials')
const form = ref<{ validate: () => Promise<{ valid: boolean }> } | null>(null)
const email = ref('')
const password = ref('')
const showPass = ref(false)
const code = ref('')
const loading = ref(false)
const error = ref('')

const emailRules = [
  (v: string) => !!v || 'Ingresa tu correo electrónico',
  (v: string) => /.+@.+\..+/.test(v) || 'El correo no es válido',
]
const passRules = [(v: string) => !!v || 'Ingresa tu contraseña']

async function submitCredentials(): Promise<void> {
  error.value = ''
  const result = await form.value?.validate()
  if (!result?.valid) return
  loading.value = true
  try {
    const entroDirecto = await auth.login(email.value, password.value)
    if (entroDirecto) {
      router.push('/')    // ya confirmó el 2FA antes: no se pide código
    } else {
      step.value = 'code' // primer ingreso: QR + 6 casillas
    }
  } catch (e) {
    error.value = errorMsg(e)
  } finally {
    loading.value = false
  }
}

async function submitCode(): Promise<void> {
  error.value = ''
  if (code.value.length !== 6) {
    error.value = 'Ingresa los 6 dígitos del código'
    return
  }
  loading.value = true
  try {
    await auth.verify(code.value)
    router.push('/')
  } catch (e) {
    error.value = errorMsg(e)
    code.value = ''
  } finally {
    loading.value = false
  }
}

function back(): void {
  auth.tempToken = null
  auth.qr = null
  code.value = ''
  error.value = ''
  step.value = 'credentials'
}
</script>

<template>
  <v-main class="login-bg">
    <v-container class="fill-height" fluid>
      <v-row justify="center" align="center">
        <v-col cols="12" sm="8" md="5" lg="4" xl="3">
          <v-card class="pa-8 text-center" rounded="xl" elevation="8">
            <v-icon icon="mdi-autorenew" size="72" color="secondary" />

            <!-- PASO 1: correo y contraseña -->
            <template v-if="step === 'credentials'">
              <h1 class="t6 text-secondary font-weight-bold mt-4">
                Sistema de Gestión de Constancias o Notas ULS
              </h1>
              <p class="sub2 text-medium-emphasis mt-2 mb-6">
                Ingrese su correo electrónico y contraseña para acceder a su cuenta
              </p>

              <v-form ref="form" @submit.prevent="submitCredentials">
                <v-text-field
                  v-model="email"
                  label="Correo electrónico"
                  prepend-inner-icon="mdi-account"
                  :rules="emailRules"
                  autocomplete="username"
                />
                <v-text-field
                  v-model="password"
                  label="Contraseña"
                  prepend-inner-icon="mdi-lock"
                  :type="showPass ? 'text' : 'password'"
                  :append-inner-icon="showPass ? 'mdi-eye-off' : 'mdi-eye'"
                  @click:append-inner="showPass = !showPass"
                  :rules="passRules"
                  autocomplete="current-password"
                />
                <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-4">
                  {{ error }}
                </v-alert>
                <v-btn type="submit" color="secondary" block size="large" :loading="loading">
                  Ingresar
                </v-btn>
              </v-form>

              <a class="sub2 d-inline-block mt-4 text-secondary" href="#">Recupera tu contraseña</a>
              <p class="sub2 mt-6 text-medium-emphasis">
                Gestión y administración de Constancias o Notas ULS.
              </p>
            </template>

            <!-- PASO 2: código 2FA (con QR la primera vez) -->
            <template v-else>
              <v-sheet color="#E1E5F2" rounded="lg" class="pa-6 mt-4">
                <template v-if="auth.qr">
                  <img :src="auth.qr" alt="Código QR" width="180" height="180" />
                  <p class="sub2 text-secondary font-weight-bold my-3">
                    Escanea el código QR en tu APP. Una vez configurado ingresa el código generado para iniciar sesión
                  </p>
                </template>
                <p v-else class="sub1 text-secondary font-weight-bold mb-4">
                  Ingresa el código generado en tu app para tu inicio de sesión
                </p>

                <v-otp-input v-model="code" length="6" type="number" @finish="submitCode" />

                <v-alert v-if="error" type="error" variant="tonal" density="compact" class="my-3">
                  {{ error }}
                </v-alert>
                <v-btn color="secondary" :loading="loading" class="mt-2" @click="submitCode">Ingresar</v-btn>
              </v-sheet>
              <v-btn variant="text" class="mt-4" @click="back">Volver</v-btn>
            </template>
          </v-card>
        </v-col>
      </v-row>
    </v-container>
  </v-main>
</template>

<style scoped>
.login-bg {
  min-height: 100vh;
  background: radial-gradient(circle at 20% 20%, #0f4c5c 0%, #07151f 55%, #02060a 100%);
}
</style>