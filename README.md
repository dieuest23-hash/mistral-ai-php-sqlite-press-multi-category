# Revue de Presse Algérie - IA

**Système automatisé de veille médiatique et d'analyse critique de l'actualité algérienne**

Un système complet de revue de presse alimenté par l'IA Mistral, spécialisé sur l'Algérie et son actualité (politique, économie, société, culture, sport, diaspora, géopolitique).

---

## 🇩🇿 Spécialisation Algérie

Ce projet est entièrement configuré pour couvrir l'actualité algérienne :

- **100 catégories thématiques** : Politique algérienne, économie, Sonatrach, startups DZ, agriculture, diaspora, relations Algérie-France, Maghreb, Afrique, etc.
- **Sources RSS ciblées** : Google News configuré avec `gl=DZ&ceid=DZ:fr` pour récupérer exclusivement les actualités algériennes
- **IA spécialisée** : Prompts configurés pour positionner l'IA comme "journaliste d'investigation spécialisé sur l'Algérie"
- **Contexte régional** : Analyse avec perspective algérienne, africaine et méditerranéenne

---

## 📁 Architecture du Projet

```
/workspace/
├── index.php          # Interface principale + orchestrateur
├── page1.php          # Récupération et catégorisation des articles RSS
├── page2.php          # Génération de revues de presse multi-sources
├── page3.php          # Analyse approfondie d'articles uniques
├── page4.php          # Synthèses et analyses géopolitiques
├── api_mistral.php    # Client API Mistral + fetch RSS
├── api.php            # Endpoint API pour le frontend
├── db.php             # Gestion base de données SQLite
└── googlenews.sqlite  # Base de données des articles
```

---

## ⚙️ Prérequis

- **PHP 7.4+** avec extensions :
  - `curl`
  - `sqlite3` ou `pdo_sqlite`
  - `libxml`
  - `simplexml`
- **Serveur web** : Apache, Nginx ou PHP built-in server
- **Clé API Mistral** : Obtenable sur [console.mistral.ai](https://console.mistral.ai/)

---

## 🚀 Installation

### 1. Cloner ou télécharger le projet

```bash
cd /workspace
```

### 2. Configurer la clé API Mistral

#### Option A : Variable d'environnement (Recommandé)

```bash
export MISTRAL_API_KEY="votre_clé_api_mistral_ici"
```

Pour rendre permanent, ajoutez cette ligne à votre `~/.bashrc` ou `~/.zshrc` :
```bash
echo 'export MISTRAL_API_KEY="votre_clé_api_mistral_ici"' >> ~/.bashrc
source ~/.bashrc
```

#### Option B : Modification directe du fichier

Ouvrez `api_mistral.php` et remplacez la ligne 5 :

```php
define('MISTRAL_API_KEY', getenv('MISTRAL_API_KEY') ?: 'votre_clé_api_mistral_ici');
```

⚠️ **Important** : Ne commitez jamais votre clé API dans un dépôt Git public !

### 3. Vérifier les permissions

Assurez-vous que le dossier est accessible en écriture pour créer/modifier la base SQLite :

```bash
chmod 755 /workspace
chmod 644 /workspace/*.php
```

### 4. Lancer le serveur

#### Avec le serveur built-in PHP :

```bash
cd /workspace
php -S localhost:8000
```

#### Ou configurez un vhost Apache/Nginx pointant vers `/workspace`

---

## 🎯 Utilisation

### Accès à l'interface

Ouvrez votre navigateur et accédez à :
- **Local** : `http://localhost:8000`
- **Production** : `http://votre-domaine.com`

### Fonctionnement

Le système fonctionne en **boucle perpétuelle** sur 4 pages exécutées séquentiellement :

1. **Page 1** : Récupère les flux RSS Google News Algérie et catégorise les articles
2. **Page 2** : Génère des revues de presse synthétiques multi-sources
3. **Page 3** : Analyse en profondeur des articles individuels
4. **Page 4** : Produit des synthèses géopolitiques et analyses régionales

### Console Live

L'interface inclut une console en temps réel affichant :
- Logs de récupération RSS
- Appels API Mistral
- Opérations base de données
- Progression des cycles

---

## 🗂️ Catégories Couvertes (100 thèmes algériens)

Le système couvre tous les aspects de l'actualité algérienne :

### Politique & Institutions
- Présidence, Gouvernement, Assemblée Populaire Nationale
- Partis politiques (FLN, RND, MSP, Front El-Bina, etc.)
- Réformes constitutionnelles, élections, justice

### Économie & Énergie
- **Sonatrach**, hydrocarbures, OPEP
- Diversification économique, hors-pétrole
- Startups algériennes, écosystème tech DZ
- Agriculture, sécurité alimentaire
- Commerce extérieur, investissements

### Société & Culture
- Éducation, santé, logement
- Culture algérienne, cinéma, musique, littérature
- Sport (football, équipe nationale, joueurs à l'étranger)
- Diaspora algérienne (France, Europe, Amérique du Nord)

### Géopolitique
- Relations Algérie-France, Algérie-USA, Algérie-Russie
- Union du Maghreb Arabe (UMA), relations Maroc-Tunisie-Libye
- Union Africaine, rôle de l'Algérie en Afrique
- Méditerranée, monde arabe, Organisation de la Coopération Islamique

---

## 🔧 Configuration Avancée

### Modifier le modèle IA

Dans `api_mistral.php`, ligne 7 :

```php
define('MISTRAL_MODEL', 'mistral-large-2411'); // ou mistral-small, open-mixtral-8x7b
```

### Ajuster les timeouts

Pour des connexions lentes, augmentez les timeouts dans `api_mistral.php` :

```php
CURLOPT_TIMEOUT        => 180,  // 3 minutes
CURLOPT_CONNECTTIMEOUT => 30,
```

### Personnaliser les catégories

Modifiez le tableau `$categories` dans `page1.php` pour adapter les thèmes.

---

## 📊 Base de Données

La base SQLite `googlenews.sqlite` contient :
- Articles bruts RSS
- Revues de presse générées par IA
- Analyses approfondies
- Synthèses géopolitiques

Structure des tables visible dans `db.php`.

---

## 🔒 Sécurité

- **Clé API** : Stockez-la dans une variable d'environnement
- **HTTPS** : Recommandé en production
- **Rate limiting** : Implémentez côté serveur si exposition publique
- **Logs** : Consultez régulièrement les logs d'erreurs PHP

---

## 🐛 Dépannage

### Erreur "API Key invalide"
→ Vérifiez que `MISTRAL_API_KEY` est correctement définie
→ Testez avec `echo getenv('MISTRAL_API_KEY');` dans un fichier PHP temporaire

### Timeout API
→ Augmentez `CURLOPT_TIMEOUT` dans `api_mistral.php`
→ Vérifiez votre connexion internet

### Base de données verrouillée
→ Supprimez `googlenews.sqlite-lock` si présent
→ Vérifiez les permissions d'écriture

### Aucun article affiché
→ Vérifiez que Page 1 s'exécute correctement (console live)
→ Testez manuellement un flux RSS avec `fetchRSS()` dans un script de test

---

## 📝 Licence

Ce projet est fourni à titre éducatif et informatif.

---

## 🤝 Contribution

Les améliorations sont les bienvenues, notamment :
- Nouvelles sources RSS algériennes
- Catégories supplémentaires
- Optimisation des prompts IA
- Support multilingue (Arabe, Tamazight)

---

## 📞 Support

Pour toute question relative à l'API Mistral : [documentation officielle](https://docs.mistral.ai/)

Pour les problèmes spécifiques au projet, consultez les logs dans la console live de l'interface.

---

**Développé avec ❤️ pour l'actualité algérienne**  
*Analyse critique permanente • Boucle perpétuelle • IA Mistral Large*
