import 'vanilla-cookieconsent/dist/cookieconsent.css';
import { run, acceptedCategory } from 'vanilla-cookieconsent';

run({
  hideFromBots: false,
  guiOptions: {
    consentModal: {
      layout: 'box inline',
      position: 'bottom right',
    },
    preferencesModal: {
      layout: 'box',
    },
  },

  categories: {
    necessary: {
      enabled: true,
      readOnly: true,
    },
    analytics: {},
  },

  language: {
    default: document.documentElement.lang === 'en' ? 'en' : 'fr',
    translations: {
      fr: {
        consentModal: {
          title: 'Gestion des cookies',
          description:
            'Ce site utilise Google Analytics pour analyser le trafic. Aucune donnée personnelle n\'est collectée directement. Vous pouvez accepter ou refuser les cookies analytiques.',
          acceptAllBtn: 'Tout accepter',
          acceptNecessaryBtn: 'Refuser',
          showPreferencesBtn: 'Gérer les préférences',
        },
        preferencesModal: {
          title: 'Préférences des cookies',
          acceptAllBtn: 'Tout accepter',
          acceptNecessaryBtn: 'Tout refuser',
          savePreferencesBtn: 'Enregistrer',
          sections: [
            {
              title: 'Cookies strictement nécessaires',
              description: 'Ces cookies sont essentiels au fonctionnement du site.',
              linkedCategory: 'necessary',
            },
            {
              title: 'Cookies analytiques',
              description:
                'Ces cookies nous permettent de mesurer le trafic du site via Google Analytics afin d\'améliorer l\'expérience utilisateur.',
              linkedCategory: 'analytics',
            },
          ],
        },
      },
      en: {
        consentModal: {
          title: 'Cookie Management',
          description:
            'This site uses Google Analytics to analyze traffic. No personal data is collected directly. You can accept or decline analytics cookies.',
          acceptAllBtn: 'Accept all',
          acceptNecessaryBtn: 'Decline',
          showPreferencesBtn: 'Manage preferences',
        },
        preferencesModal: {
          title: 'Cookie Preferences',
          acceptAllBtn: 'Accept all',
          acceptNecessaryBtn: 'Decline all',
          savePreferencesBtn: 'Save',
          sections: [
            {
              title: 'Strictly necessary cookies',
              description: 'These cookies are essential for the website to function.',
              linkedCategory: 'necessary',
            },
            {
              title: 'Analytics cookies',
              description:
                'These cookies allow us to measure site traffic via Google Analytics to improve the user experience.',
              linkedCategory: 'analytics',
            },
          ],
        },
      },
    },
  },

  onConsent: () => {
    if (acceptedCategory('analytics')) {
      loadGA4();
    }
  },

  onChange: () => {
    if (acceptedCategory('analytics')) {
      loadGA4();
    } else {
      // Delete GA cookies
      document.cookie.split(';').forEach((c) => {
        const name = c.trim().split('=')[0];
        if (name.startsWith('_ga')) {
          document.cookie = `${name}=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/;domain=.${window.location.hostname}`;
          document.cookie = `${name}=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/`;
        }
      });
    }
  },
});

function loadGA4() {
  if (document.querySelector('script[src*="googletagmanager"]')) return;

  const script = document.createElement('script');
  script.async = true;
  script.src = 'https://www.googletagmanager.com/gtag/js?id=G-4J2Y2V33VE';
  document.head.appendChild(script);

  script.onload = () => {
    (window as any).dataLayer = (window as any).dataLayer || [];
    function gtag(...args: any[]) { (window as any).dataLayer.push(args); }
    gtag('js', new Date());
    gtag('config', 'G-4J2Y2V33VE');
  };
}
