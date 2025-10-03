import {defineConfig} from 'vite';

export default defineConfig({
  publicDir: false,
  build: {
    outDir: 'public',
    emptyOutDir: false,
    minify: 'terser',
    sourcemap: true,
    rollupOptions: {
      input: {
        main: 'ts/main.ts'
      },
      output: {
        entryFileNames: 'js/main.min.js',
        chunkFileNames: 'js/[name].js',
        assetFileNames: assetInfo => {
          if (assetInfo.names?.[0]?.endsWith('.css')) return 'css/libs.min.css';
          else return 'assets/[name][extname]';
        }
      }
    }
  }
});
