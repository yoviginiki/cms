// Build config for the flow-engine browser harness (Session C).
// npx vite build --config vite.harness.config.ts
import { defineConfig } from 'vite';
import path from 'path';

export default defineConfig({
  root: __dirname,
  resolve: {
    alias: { '@': path.resolve(__dirname, 'src') },
  },
  build: {
    outDir: path.resolve(__dirname, 'harness/dist'),
    emptyOutDir: true,
    minify: false,
    lib: {
      entry: path.resolve(__dirname, 'harness/flow-harness.ts'),
      name: 'FlowHarness',
      formats: ['iife'],
      fileName: () => 'flow-harness.js',
    },
  },
});
