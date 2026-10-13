import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],

  // 生成されたJS・CSSを相対パスで参照する
  base: './',

  // ビルド結果をdistに出力する
  build: {
    outDir: 'dist',
    emptyOutDir: true,
  },
});