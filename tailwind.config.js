/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './resources/views/**/*.blade.php',
    './public/assets/js/**/*.js',
  ],
  // app.css sudah melakukan reset dan mengatur tipografi dasar; preflight
  // Tailwind akan menimpanya dan membuat heading serta tombol kehilangan gaya.
  corePlugins: {
    preflight: false,
  },
  theme: {
    extend: {
      // Token warna mengacu ke variabel di public/assets/css/app.css, supaya
      // utility Tailwind dan CSS design system tidak pernah berbeda nilai.
      colors: {
        primary: {
          DEFAULT: 'var(--primary)',
          dark: 'var(--primary-dark)',
          soft: 'var(--primary-soft)',
        },
        sidebar: 'var(--sidebar-from)',
        lwbp: 'var(--lwbp)',
        wbp: 'var(--wbp)',
      },
      fontFamily: {
        sans: ['Plus Jakarta Sans', 'system-ui', 'Segoe UI', 'sans-serif'],
        mono: ['JetBrains Mono', 'ui-monospace', 'SF Mono', 'monospace'],
      },
      width: {
        sidebar: '262px',
      },
    },
  },
  plugins: [],
};
