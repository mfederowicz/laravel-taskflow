import tailwindcss from '@tailwindcss/vite'

// https://nuxt.com/docs/api/configuration/nuxt-config
// @ts-ignore
export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },
  css: ['~/assets/css/main.css'],
  app: {
    head: {
      titleTemplate: '%s - TaskFlow',
    },
  },
  vite: {
    plugins: [tailwindcss()],
    server: {
      allowedHosts: (process.env.ALLOWED_HOSTS ?? 'localhost,127.0.0.1')
        .split(',')
        .map((host) => host.trim())
        .filter(Boolean),
    },
  },
})
