import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import type { Role } from '@/types'

declare module 'vue-router' {
  interface RouteMeta {
    auth?: boolean
    guest?: boolean
    title?: string
    roles?: Role[]
  }
}

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { guest: true },
    },
    {
      path: '/',
      component: () => import('@/layouts/MainLayout.vue'),
      meta: { auth: true },
      children: [
        {
          path: '',
          name: 'inicio',
          component: () => import('@/views/HomeView.vue'),
          meta: { title: 'Inicio' },
        },
        {
          path: 'docentes',
          name: 'docentes',
          component: () => import('@/views/DocentesView.vue'),
          meta: { title: 'Docentes', roles: ['admin'] },
        },
                {
          path: 'notas',
          name: 'notas',
          component: () => import('@/views/NotasView.vue'),
          meta: { title: 'Impresión de notas', roles: ['admin', 'docente'] },
        },
                {
          path: 'notas/registro',
          name: 'notas-registro',
          component: () => import('@/views/RegistroNotasView.vue'),
          meta: { title: 'Registro de notas', roles: ['admin', 'docente'] },
        },
                {
          path: 'reportes',
          name: 'reportes',
          component: () => import('@/views/ReportesView.vue'),
          meta: { title: 'Reportes', roles: ['admin', 'docente'] },
        },
      ],
    },
    
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

router.beforeEach((to) => {
  const auth = useAuthStore()
  if (to.meta.auth && !auth.isAuth) return '/login'
  if (to.meta.guest && auth.isAuth) return '/'
  if (to.meta.roles && (!auth.role || !to.meta.roles.includes(auth.role))) return '/'
})

export default router