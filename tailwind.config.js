/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'class',
  content: [
    './*.php',
    './auth/**/*.php',
    './includes/**/*.php',
    './laporan/**/*.php',
    './master/**/*.php',
    './notulen/**/*.php',
    './rekap/**/*.php',
    './assets/js/**/*.js',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
      },
      colors: {
        brand: {
          purple: '#6558f5',
          hover: '#5244e8',
          purpleLight: '#efeffb',
          purpleSoft: '#e4e3f8',
          navy: '#181b2c',
          mint: '#68cfa3',
          mintSoft: '#e6f7f0',
          coral: '#e0536c',
          coralSoft: '#fce8ec',
          bgOuter: '#dcd9f5',
          bgInner: '#f7f7fb',
        }
      },
      boxShadow: {
        'shell': '0 30px 70px -15px rgba(45, 40, 105, 0.18), 0 0 1px 1px rgba(255,255,255,0.6)',
        'card': '0 10px 30px -8px rgba(35, 32, 85, 0.05)',
        'card-hover': '0 18px 38px -6px rgba(99, 91, 255, 0.13)',
        'pop': '0 18px 40px -10px rgba(28, 25, 68, 0.14)',
        'feature': '0 16px 35px -8px rgba(35, 32, 85, 0.08)',
      }
    }
  },
  plugins: [],
};
