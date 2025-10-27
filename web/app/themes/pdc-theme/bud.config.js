export default async (app) => {
  app
    .entry('app', ['js/app.js'])
    .setPath('@dist', 'public')
    .assets(['img'])
    .use([
      '@roots/bud-tailwindcss',
    ]);
};
