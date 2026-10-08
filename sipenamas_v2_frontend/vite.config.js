import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    react(),
    tailwindcss(),
  ],
  server: {
    host: true,
    port: 5174,
    strictPort: false, // will auto-increment to 5175 if 5174 is busy
    open: false,
    proxy: {
      '/api/v1': {
        target: 'https://apisipenamas.ukwms.ac.id',
        changeOrigin: true,
        secure: false,
      },
    },
  },
})
