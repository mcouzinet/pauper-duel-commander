export default {
  content: [
    './*.php',
    './views/**/*.twig',
    './src/**/*.{php,vue,js}',
    './inc/**/*.php',
  ],
  theme: {
    extend: {
      colors: {
        // Couleurs principales du logo
        'brand': {
          'orange': {
            DEFAULT: '#FF5722',
            light: '#FF7043',
            dark: '#E64A19',
            glow: '#FF6E40',
          },
          'black': {
            DEFAULT: '#0A0E13',
            light: '#1A1F26',
            dark: '#000000',
          },
        },
        // Couleurs des 5 manas (secondaires)
        'mana': {
          'white': '#F8F6F1',
          'blue': '#0E68AB',
          'black': '#150B00',
          'red': '#D3202A',
          'green': '#00733E',
          'colorless': '#BEB9B2',
        },
        // Interface
        'card': {
          bg: '#141821',
          border: '#FF5722',
          hover: '#1C212B',
        },
        'text': {
          primary: '#FFFFFF',
          secondary: '#B8BEC8',
          muted: '#6B7280',
          orange: '#FF5722',
        },
        'bg': {
          primary: '#0A0E13',
          secondary: '#141821',
          tertiary: '#1C212B',
        },
      },
      fontFamily: {
        'display': ['Barlow Condensed', 'Impact', 'sans-serif'],
        'heading': ['Barlow Condensed', 'Impact', 'sans-serif'],
        'body': ['Inter', 'system-ui', 'sans-serif'],
      },
      backgroundImage: {
        'gradient-orange': 'linear-gradient(135deg, #FF7043 0%, #FF5722 50%, #E64A19 100%)',
        'gradient-mana': 'linear-gradient(135deg, #0E68AB 0%, #D3202A 25%, #00733E 50%, #FF5722 75%, #F8F6F1 100%)',
        'gradient-card': 'linear-gradient(145deg, rgba(255, 87, 34, 0.06) 0%, rgba(255, 87, 34, 0.02) 100%)',
        'gradient-card-hover': 'linear-gradient(145deg, rgba(255, 87, 34, 0.12) 0%, rgba(255, 87, 34, 0.06) 100%)',
        'gradient-border': 'linear-gradient(145deg, #E64A19 0%, #FF7043 100%)',
        'gradient-strike': 'linear-gradient(90deg, transparent 0%, #FF5722 50%, transparent 100%)',
        'texture-noise': 'url("data:image/svg+xml,%3Csvg viewBox=\'0 0 400 400\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cfilter id=\'noiseFilter\'%3E%3CfeTurbulence type=\'fractalNoise\' baseFrequency=\'0.9\' numOctaves=\'3\' stitchTiles=\'stitch\'/%3E%3C/filter%3E%3Crect width=\'100%25\' height=\'100%25\' filter=\'url(%23noiseFilter)\' opacity=\'0.03\'/%3E%3C/svg%3E")',
      },
      boxShadow: {
        'card': '0 4px 24px -2px rgba(255, 87, 34, 0.08), 0 8px 16px -4px rgba(0, 0, 0, 0.3)',
        'card-hover': '0 12px 40px -4px rgba(255, 87, 34, 0.15), 0 16px 24px -8px rgba(0, 0, 0, 0.4)',
        'orange': '0 0 20px rgba(255, 87, 34, 0.15), 0 0 40px rgba(255, 87, 34, 0.08)',
        'orange-intense': '0 0 30px rgba(255, 87, 34, 0.25), 0 0 60px rgba(255, 110, 64, 0.12)',
        'inner-glow': 'inset 0 0 20px rgba(255, 87, 34, 0.05)',
        'inset-dark': 'inset 0 2px 8px rgba(0, 0, 0, 0.3)',
      },
      animation: {
        'shimmer': 'shimmer 3s ease-in-out infinite',
        'glow': 'glow 2s ease-in-out infinite alternate',
        'float': 'float 6s ease-in-out infinite',
        'fade-in': 'fadeIn 0.6s ease-out',
        'slide-up': 'slideUp 0.6s ease-out',
      },
      keyframes: {
        shimmer: {
          '0%, 100%': { backgroundPosition: '0% 50%' },
          '50%': { backgroundPosition: '100% 50%' },
        },
        glow: {
          '0%': { filter: 'brightness(1) drop-shadow(0 0 4px rgba(255, 87, 34, 0.2))' },
          '100%': { filter: 'brightness(1.1) drop-shadow(0 0 8px rgba(255, 87, 34, 0.3))' },
        },
        float: {
          '0%, 100%': { transform: 'translateY(0px)' },
          '50%': { transform: 'translateY(-10px)' },
        },
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        slideUp: {
          '0%': { opacity: '0', transform: 'translateY(20px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
      },
    },
  },
  plugins: [],
};
