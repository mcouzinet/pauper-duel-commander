export default {
  content: [
    './*.php',
    './views/**/*.twig',
    './src/**/*.{php,vue,js}',
  ],
  theme: {
    extend: {
      colors: {
        'magic-gold': '#f59e0b',
        'magic-purple': '#8b5cf6',
        'card-foreground': '#1f2937',
        'muted-foreground': '#6b7280',
      },
      backgroundImage: {
        'gradient-gold': 'linear-gradient(to right, #f59e0b, #d97706)',
        'gradient-card': 'linear-gradient(135deg, rgba(139, 92, 246, 0.05) 0%, rgba(245, 158, 11, 0.05) 100%)',
      },
      boxShadow: {
        'card': '0 4px 6px -1px rgba(139, 92, 246, 0.1), 0 2px 4px -1px rgba(139, 92, 246, 0.06)',
        'magic': '0 10px 25px -5px rgba(139, 92, 246, 0.3), 0 10px 10px -5px rgba(245, 158, 11, 0.2)',
      },
    },
  },
  plugins: [],
};
