import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import path from 'path';

export default defineConfig({
  plugins: [react(), tailwindcss()],
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: './src/test/setup.ts',
    include: ['src/**/*.test.{ts,tsx}'],
    exclude: ['src/**/__tests__/**'],
  },
  // base MUST match where files are actually served: the Laravel blade serves
  // /admin-assets/* from public/admin-assets. It was '/admin/' — every lazy
  // chunk + modulepreload 404'd (console noise, wasted requests) because
  // nothing serves /admin/assets/*. Router basename ('/admin') is separate.
  base: '/admin-assets/',
  root: __dirname,
  build: {
    outDir: path.resolve(__dirname, '../../public/admin-assets'),
    // keep previous builds' hashed chunks: open admin tabs lazy-load chunks
    // AFTER a redeploy — emptying the dir 404'd every stale session
    // (clean old chunks periodically: find assets -mtime +30 -delete)
    emptyOutDir: false,
    manifest: true,
    rollupOptions: {
      input: path.resolve(__dirname, 'index.html'),
    },
  },
  server: {
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
      },
    },
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'src'),
      '@flipbook': path.resolve(__dirname, '../js/flipbook/src'),
    },
  },
});
