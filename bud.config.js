export default async (app) => {
  app
    .setPath('@src', 'web/app/themes/pdc-theme/src')
    .setPath('@dist', 'web/app/themes/pdc-theme/public')
    .entry('app', ['@src/js/app.js'])
    .entry('css/editor', ['@src/css/editor.css'])
    .assets(['img', 'video'])
    .use([
      '@roots/bud-tailwindcss',
    ]);
};
