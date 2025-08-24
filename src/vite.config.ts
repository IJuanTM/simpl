import {defineConfig} from 'vite';

export default defineConfig({
  server: {
    origin: 'http://localhost:5173',
    cors: {
      origin: 'http://simpl.local',
      credentials: true
    },
    hmr: {
      host: 'simpl.local',
      protocol: 'ws',
    }
  },
  build: {
    emptyOutDir: true,
    minify: 'terser',
    sourcemap: true,
    rollupOptions: {
      input: {
        main: 'ts/main.ts'
      },
      output: {
        entryFileNames: 'js/main.min.js',
        chunkFileNames: 'js/[name].js',
        assetFileNames: (assetInfo) => {
          const name = assetInfo.names?.[0];

          if (name?.endsWith(".css")) return "css/[name].min[extname]";

          return "assets/[name]-[hash][extname]";
        }
      }
    }
  }
});
