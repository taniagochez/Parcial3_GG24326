<script setup lang="ts">
import { ref, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { errorMsg } from '@/services/api'

const auth = useAuthStore()
const router = useRouter()

type Step = 'credentials' | 'code' | 'forgot' | 'reset'
const step = ref<Step>('credentials')
const form = ref<{ validate: () => Promise<{ valid: boolean }> } | null>(null)
const email = ref('')
const password = ref('')
const showPass = ref(false)
const code = ref('')
const loading = ref(false)
const error = ref('')
const info = ref('')
const exito = ref('')
const formForgot = ref<{ validate: () => Promise<{ valid: boolean }> } | null>(null)
const formReset = ref<{ validate: () => Promise<{ valid: boolean }> } | null>(null)
const nuevaPassword = ref('')
const confirmarPassword = ref('')
const showNueva = ref(false)
const espera = ref(0) // segundos para poder reenviar el código
let timer: ReturnType<typeof setInterval> | null = null

function iniciarEspera(): void {
  espera.value = 60
  if (timer) clearInterval(timer)
  timer = setInterval(() => {
    espera.value--
    if (espera.value <= 0 && timer) clearInterval(timer)
  }, 1000)
}
onBeforeUnmount(() => timer && clearInterval(timer))

const emailRules = [
  (v: string) => !!v || 'Ingresa tu correo electrónico',
  (v: string) => /.+@.+\..+/.test(v) || 'El correo no es válido',
]
const passRules = [(v: string) => !!v || 'Ingresa tu contraseña']

async function submitCredentials(): Promise<void> {
  error.value = ''
  exito.value = ''
  const result = await form.value?.validate()
  if (!result?.valid) return
  loading.value = true
  try {
    const entroDirecto = await auth.login(email.value, password.value)
    if (entroDirecto) {
      router.push('/')    // ya confirmó el 2FA antes: no se pide código
    } else {
      step.value = 'code' // primer ingreso: código enviado al correo
      iniciarEspera()
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

async function reenviar(): Promise<void> {
  error.value = ''
  info.value = ''
  try {
    await auth.resendCode()
    info.value = 'Te enviamos un código nuevo'
    code.value = ''
    iniciarEspera()
  } catch (e) {
    error.value = errorMsg(e)
  }
}

// ---- recuperar contraseña ----
const passNuevaRules = [
  (v: string) => !!v || 'Ingresa la nueva contraseña',
  (v: string) => v.length >= 8 || 'Debe tener al menos 8 caracteres',
]
const passConfirmRules = [
  (v: string) => !!v || 'Confirma la nueva contraseña',
  (v: string) => v === nuevaPassword.value || 'Las contraseñas no coinciden',
]

function irRecuperar(): void {
  error.value = ''
  info.value = ''
  exito.value = ''
  step.value = 'forgot'
}

function volverLogin(): void {
  code.value = ''
  nuevaPassword.value = ''
  confirmarPassword.value = ''
  error.value = ''
  info.value = ''
  step.value = 'credentials'
}

async function enviarRecuperacion(): Promise<void> {
  error.value = ''
  const r = await formForgot.value?.validate()
  if (!r?.valid) return
  loading.value = true
  try {
    await auth.solicitarRecuperacion(email.value)
    step.value = 'reset'
    iniciarEspera()
  } catch (e) {
    error.value = errorMsg(e)
  } finally {
    loading.value = false
  }
}

async function reenviarRecuperacion(): Promise<void> {
  error.value = ''
  info.value = ''
  try {
    await auth.solicitarRecuperacion(email.value)
    info.value = 'Te enviamos un código nuevo'
    code.value = ''
    iniciarEspera()
  } catch (e) {
    error.value = errorMsg(e)
  }
}

async function guardarNuevaPassword(): Promise<void> {
  error.value = ''
  if (code.value.length !== 6) {
    error.value = 'Ingresa los 6 dígitos del código'
    return
  }
  const r = await formReset.value?.validate()
  if (!r?.valid) return
  loading.value = true
  try {
    await auth.restablecerPassword({
      email: email.value,
      code: code.value,
      password: nuevaPassword.value,
      password_confirmation: confirmarPassword.value,
    })
    password.value = ''
    volverLogin()
    exito.value = 'Contraseña actualizada. Ya puedes iniciar sesión.'
  } catch (e) {
    error.value = errorMsg(e)
  } finally {
    loading.value = false
  }
}

function back(): void {
  auth.tempToken = null
  auth.correo = null
  info.value = ''
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
                Sistema de Gestión de Notas ULS
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
                <v-alert v-if="exito" type="success" variant="tonal" density="compact" class="mb-4">
                  {{ exito }}
                </v-alert>
                <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-4">
                  {{ error }}
                </v-alert>
                <v-btn type="submit" color="secondary" block size="large" :loading="loading">
                  Ingresar
                </v-btn>
              </v-form>

              <a class="sub2 d-inline-block mt-4 text-secondary" href="#" @click.prevent="irRecuperar">Recupera tu contraseña</a>
              <p class="sub2 mt-6 text-medium-emphasis">
                Gestión y administración de Constancias o Notas ULS.
              </p>
            </template>

            <!-- PASO 2: código de 6 dígitos enviado al correo (solo el primer ingreso) -->
            <template v-else-if="step === 'code'">
              <v-sheet color="#E1E5F2" rounded="lg" class="pa-6 mt-4">
                <v-icon icon="mdi-email-fast-outline" size="48" color="secondary" />
                <p class="sub1 text-secondary font-weight-bold my-3">
                  Enviamos un código de 6 dígitos a {{ auth.correo || 'tu correo' }}
                </p>
                <p class="sub2 text-medium-emphasis mb-4">Ingrésalo para iniciar sesión. Vence en 10 minutos.</p>

                <v-otp-input v-model="code" length="6" type="number" @finish="submitCode" />

                <v-alert v-if="info" type="success" variant="tonal" density="compact" class="my-3">{{ info }}</v-alert>
                <v-alert v-if="error" type="error" variant="tonal" density="compact" class="my-3">
                  {{ error }}
                </v-alert>
                <v-btn color="secondary" :loading="loading" class="mt-2" @click="submitCode">Ingresar</v-btn>
                <div class="mt-3">
                  <v-btn variant="text" size="small" color="secondary" :disabled="espera > 0" @click="reenviar">
                    {{ espera > 0 ? `Reenviar código en ${espera} s` : 'Reenviar código' }}
                  </v-btn>
                </div>
              </v-sheet>
              <v-btn variant="text" class="mt-4" @click="back">Volver</v-btn>
            </template>

            <!-- RECUPERAR 1: correo de la cuenta -->
            <template v-else-if="step === 'forgot'">
              <h1 class="t6 text-secondary font-weight-bold mt-4">Recupera tu contraseña</h1>
              <p class="sub2 text-medium-emphasis mt-2 mb-6">
                Escribe el correo de tu cuenta y te enviaremos un código de 6 dígitos para confirmarla.
              </p>
              <v-form ref="formForgot" @submit.prevent="enviarRecuperacion">
                <v-text-field
                  v-model="email" label="Correo electrónico" prepend-inner-icon="mdi-account"
                  :rules="emailRules" autocomplete="username"
                />
                <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-4">{{ error }}</v-alert>
                <v-btn type="submit" color="secondary" block size="large" :loading="loading">Enviar código</v-btn>
              </v-form>
              <v-btn variant="text" class="mt-4" @click="volverLogin">Volver</v-btn>
            </template>

            <!-- RECUPERAR 2: código + nueva contraseña -->
            <template v-else>
              <v-sheet color="#E1E5F2" rounded="lg" class="pa-6 mt-4">
                <v-icon icon="mdi-email-fast-outline" size="48" color="secondary" />
                <p class="sub1 text-secondary font-weight-bold my-3">
                  Si la cuenta existe, enviamos un código de 6 dígitos a {{ email }}
                </p>
                <p class="sub2 text-medium-emphasis mb-4">Ingrésalo y elige tu nueva contraseña. Vence en 10 minutos.</p>

                <v-otp-input v-model="code" length="6" type="number" />

                <v-form ref="formReset" class="mt-4" @submit.prevent="guardarNuevaPassword">
                  <v-text-field
                    v-model="nuevaPassword" label="Nueva contraseña" prepend-inner-icon="mdi-lock"
                    :type="showNueva ? 'text' : 'password'"
                    :append-inner-icon="showNueva ? 'mdi-eye-off' : 'mdi-eye'"
                    @click:append-inner="showNueva = !showNueva"
                    :rules="passNuevaRules" autocomplete="new-password"
                  />
                  <v-text-field
                    v-model="confirmarPassword" label="Confirmar contraseña" prepend-inner-icon="mdi-lock-check"
                    :type="showNueva ? 'text' : 'password'"
                    :rules="passConfirmRules" autocomplete="new-password"
                  />
                  <v-alert v-if="info" type="success" variant="tonal" density="compact" class="mb-3">{{ info }}</v-alert>
                  <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-3">{{ error }}</v-alert>
                  <v-btn type="submit" color="secondary" block :loading="loading">Guardar contraseña</v-btn>
                </v-form>

                <div class="mt-3">
                  <v-btn variant="text" size="small" color="secondary" :disabled="espera > 0" @click="reenviarRecuperacion">
                    {{ espera > 0 ? `Reenviar código en ${espera} s` : 'Reenviar código' }}
                  </v-btn>
                </div>
              </v-sheet>
              <v-btn variant="text" class="mt-4" @click="volverLogin">Volver</v-btn>
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