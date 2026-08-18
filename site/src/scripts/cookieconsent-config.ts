import 'vanilla-cookieconsent/dist/cookieconsent.css';
import { run, acceptedCategory } from 'vanilla-cookieconsent';

/** Must match `locales` in lib/i18n.ts and the translation keys below. */
const SUPPORTED = ['fr', 'en', 'it'];

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
    // <html lang> is the locale the page was built for. Anything unexpected
    // falls back to French, the locale the copy is authored in.
    default: SUPPORTED.includes(document.documentElement.lang) ? document.documentElement.lang : 'fr',
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
      it: {
        consentModal: {
          title: 'Gestione dei cookie',
          description:
            'Questo sito utilizza Google Analytics per analizzare il traffico. Nessun dato personale viene raccolto direttamente. Puoi accettare o rifiutare i cookie analitici.',
          acceptAllBtn: 'Accetta tutto',
          acceptNecessaryBtn: 'Rifiuta',
          showPreferencesBtn: 'Gestisci le preferenze',
        },
        preferencesModal: {
          title: 'Preferenze dei cookie',
          acceptAllBtn: 'Accetta tutto',
          acceptNecessaryBtn: 'Rifiuta tutto',
          savePreferencesBtn: 'Salva',
          sections: [
            {
              title: 'Cookie strettamente necessari',
              description: 'Questi cookie sono essenziali per il funzionamento del sito.',
              linkedCategory: 'necessary',
            },
            {
              title: 'Cookie analitici',
              description:
                'Questi cookie ci permettono di misurare il traffico del sito tramite Google Analytics per migliorare l\'esperienza utente.',
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
    // Published on window so scripts/analytics.ts can send journey events. Kept
    // here rather than there so consent stays the single gate: no gtag on the
    // window means track() is a no-op.
    (window as any).gtag = gtag;
    gtag('js', new Date());
    gtag('config', 'G-4J2Y2V33VE');
  };
}
