import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],

  // 店舗管理アプリ専用の設定
  base: './',

  build: {
    // 既存のdistとは別のフォルダに出力する
    outDir: 'dist-stores',

    // 専用フォルダ内の古い生成ファイルだけを削除する
    emptyOutDir: true,
    rollupOptions: {
    input: './stores.html',
    },
  },
});