/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,ts,tsx}'],
  theme: {
    extend: {
      colors: {
        primary: {
          DEFAULT: '#5B8FF9',
          deep: '#3B6FE0',
          purple: '#7A6FF0',
        },
        ink: {
          DEFAULT: '#1F2D3D',
          sub: '#5E6D82',
          mute: '#9099A8',
        },
        page: '#EEF2FB',
      },
      fontFamily: {
        sans: ['"PingFang SC"', '"Microsoft YaHei"', '"Helvetica Neue"', 'Arial', 'sans-serif'],
      },
      boxShadow: {
        card: '0 2px 12px rgba(59, 111, 224, 0.08)',
        'card-hover': '0 8px 24px rgba(59, 111, 224, 0.16)',
      },
    },
  },
  plugins: [require('tailwindcss-animate')],
}
