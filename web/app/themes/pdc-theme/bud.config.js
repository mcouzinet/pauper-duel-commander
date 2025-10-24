export default async (app) => {
  app
    .entry('app', ['js/app.js'])
    .setPath('@dist', 'public')
    .use([
      '@roots/bud-tailwindcss',
    ]);
};
