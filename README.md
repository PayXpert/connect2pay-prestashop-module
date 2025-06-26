# PayXpert PrestaShop Module

Module de paiement PayXpert pour PrestaShop.

## 📦 Fonctionnalités

- Choix du mode de capture (automatique ou manuelle)
- Envoi d'une notification email avant expiration de l'autorisation
- Modes d'affichage du paiement : redirection ou iFrame (seamless)
- Option PayByLink
- Paiement en plusieurs fois (instalment)
- Customisation des méthodes de paiement
- Système de notifications personnalisables :
  - Activation/désactivation
  - Adresse email de réception
  - Langue des notifications


## 🔧 Installation

### (Méthode 1) Depuis le back-office PrestaShop :

- Allez dans Modules > Module Manager
- Cliquez sur Téléverser un module
- Glissez le fichier ZIP du module ou sélectionnez le depuis votre ordinateur
- Suivez les instructions pour finaliser l’installation


### (Méthode 2) Manuellement :

- Décompressez l’archive ZIP du module
- Copiez le dossier du module dans le répertoire /modules/ de votre installation PrestaShop
- Allez ensuite dans Modules > Module Manager, recherchez "PayXpert" puis cliquez sur Installer


## 🔁 Mise à jour du module

Pour mettre à jour le module PayXpert :

1. (optionnel) Désinstallez l’ancienne version via **Modules > Gestionnaire de modules**  
   > 🛈 Si vous souhaitez conserver la configuration, **ne cochez pas** l’option de suppression des données
2. Installez la nouvelle version du module :
   - Soit en téléversant la nouvelle archive ZIP via le gestionnaire de modules
   - Soit en remplaçant manuellement le dossier dans `/modules/`, aller ensuite dans Modules > Module Manager, recherchez "PayXpert" et cliquer sur Mettre à jour


## 🔧 Configuration

Après installation, allez dans :

**Modules > Gestionnaire de modules > PayXpert > Configurer**, puis renseignez :

- Clé publique API
- Clé privé API

Si vos clés sont reconnues, vous débloquerez l'accès aux configurations avancées du module.


## 🛠 Dépendances

- PrestaShop >= 1.7.5.2
- PHP >= 7.2
- Un compte PayXpert valide


## 📜 Licence

Ce module est distribué sous la licence MIT. Voir le fichier [LICENSE](./LICENSE) pour plus d’informations.


## ✉️ Support

Un formulaire de contact au support est disponible dans la page de configuration du module.
En cas de problème d'installation, veuillez contacter assistance@payxpert.com

-----------------

# PayXpert PrestaShop Module

PayXpert payment module for PrestaShop.

## 📦 Features

- Choice of capture mode (automatic or manual)
- Email notification before authorization expiration
- Payment display modes: redirection or iFrame (seamless)
- PayByLink option
- Instalment payments
- Customization of payment methods
- Customizable notification system:
  - Enable/disable
  - Notification email address
  - Notification language


## 🔧 Installation

### (Method 1) From the PrestaShop back office:

- Go to Modules > Module Manager  
- Click on Upload a module  
- Drag and drop the module ZIP file or select it from your computer  
- Follow the instructions to complete the installation  


### (Method 2) Manually:

- Unzip the module archive  
- Copy the module folder into the `/modules/` directory of your PrestaShop installation  
- Then go to Modules > Module Manager, search for "PayXpert" and click Install  


## 🔁 Module Update

To update the PayXpert module:

1. (optional) Uninstall the old version via **Modules > Module Manager**  
   > 🛈 If you want to keep the configuration, **do not check** the data deletion option
2. Install the new version of the module:
   - Either by uploading the new ZIP archive via the module manager
   - Or by manually replacing the folder in `/modules/`, then go to Modules > Module Manager, search for "PayXpert" and click Update


## 🔧 Configuration

After installation, go to:  

**Modules > Module Manager > PayXpert > Configure**, then enter:

- API Public Key  
- API Private Key  

If your keys are valid, you will gain access to the module’s advanced settings.


## 🛠 Dependencies

- PrestaShop >= 1.7.5.2  
- PHP >= 7.2  
- A valid PayXpert account  


## 📜 License

This module is distributed under the MIT license. See the [LICENSE](./LICENSE) file for more information.


## ✉️ Support

A contact form for support is available on the module configuration page.  
If you encounter any installation issues, please contact assistance@payxpert.com.