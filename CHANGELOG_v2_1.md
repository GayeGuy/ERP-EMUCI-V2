# Changelog v2.1 — ERP EMUCI

**Date** : 25 juillet 2026  
**Branche** : `master`  
**Commits** : 7 commits (891afca → 6d4c1ea)

## 🎯 Aperçu

Amélioration majeure du module rapports et exports. Consolidation des données PMMA & rivets, ajout de filtres temporels avancés, nettoyage de la production.

---

## ✨ Nouvelles fonctionnalités

### 1. **Exports Excel : PMMA & Rivets** `3ac2fa6`
**Fichiers** : `api/export.php`

Deux nouveaux types d'export dans l'interface Rapports :
- **Export PMMA** (`type=pmma`) : Stock par site avec seuil alerte, responsable, date
- **Export Rivets** (`type=rivets`) : Mouvements (entrée/sortie) avec type, utilisateur, notes

**Accès** : Bouton dans la section Rapports (nécessite permission `can_export` sur pmma/rivets)

```
URL: /api/export.php?type=pmma&site=<id>
     /api/export.php?type=rivets&site=<id>
```

---

### 2. **PDF Synthèse Mensuelle Rivets** `6686663`
**Fichier** : `pages/operations/rivets.php`

Rapport PDF mensuel groupé par mois (cohérence PMMA).  
Affiche : gonflables, éclatés, endommagés, utilisés, total sorti.

**Accès** : Bouton 🗓️ **PDF mensuel** dans la page Gestion rivets

---

### 3. **Export PDF Rapports Généraux** `d34ea19`
**Fichier** : `pages/rapports.php`

Génère un PDF résumé du rapport avec KPIs et consommation top 20.  
Utilise DomPDF. Archivable directement.

**Accès** : Bouton 📄 **PDF rapport** dans Rapports & Analyses

---

### 4. **Historique + Export Rapports Journaliers** `98d10b5`
**Fichiers** : `pages/rapport_journalier.php`, `api/export.php`

- **Historique** : Tableau de tous les rapports du mois/site sélectionné
- **Export Excel** : Bouton 📥 **Exporter** (type=rapports_journaliers)  
  Récapitulatif opérationnels, H.S, maintenance, interventions par date

**Accès** : Page Rapport journalier (Informatique)

---

### 5. **Filtre Annuel — Vue PDG** `6d4c1ea`
**Fichier** : `pages/pdg_overview.php`

Basculer entre deux vues :
- **Par mois** : Données du mois sélectionné (comportement antérieur)
- **Année complète** : Agrégation annuelle de tous les KPIs

Tous les graphiques, totaux et statistiques s'adaptent automatiquement.

**Contrôles** : Sélecteur "Vue" + sélecteur mois/année

**Disponibilité** : Années 2020 → année en cours

---

### 6. **Menu Réception de Site** `e3749e7`
**Fichier** : `includes/groupes_config.php`

Ajout entrée "Réception de site" dans le groupe **STOCK** du menu principal.

**Accès** : Rôles `gestionnaire_stock`, `superviseur_operation`, `admin`, `superadmin`

**Icône** : 📥 (inbox)

---

## 🧹 Maintenance

### 7. **Suppression script de maintenance** `891afca`
**Fichier supprimé** : `fix_columns.php`

Script d'administration (réinitialisation, correction de colonnes) retiré de la production.  
**Raison** : Non autorisé en environnement de production.

---

## 🔐 Sécurité & Permissions

**Nouvelles permissions requises** :

| Fonction | Module | Permission |
|----------|--------|-----------|
| Export PMMA | pmma | can_export |
| Export Rivets | rivets | can_export |
| Export Rapports | rapports | can_export |
| Export Rapports Journaliers | rapport_journalier | can_export |
| PDF Rapports | rapports | can_export |

**Rôles impactés** :
- ✅ `admin`, `superadmin` : Accès complet
- ✅ `superviseur_operation` : Accès rapports + inventaires
- ✅ `gestionnaire_stock_bobines` : Accès exports PMMA/rivets
- ✅ `maintenance_info` : Accès rapports journaliers

---

## 📊 Exemples d'utilisation

### Export PMMA mensuel
```
GET /api/export.php?type=pmma&site=3
→ stock_pmma_[date].xlsx
```

### PDF mensuel rivets
```
GET /pages/operations/rivets.php?export=pdf_mensuel&site=2
→ synthese_rivets_mensuel_[date].pdf
```

### Vue PDG annuelle 2025
```
GET /pages/pdg_overview.php?vue=annee&annee=2025&mois=2025-01
```

---

## 📝 Notes technique

- **DomPDF** : Committé dans `vendor/` — pas d'installation supplémentaire
- **Idempotence** : Les exports SQL utilisent `COALESCE()` pour traiter les données NULL
- **Performance** : Filtres date optimisés (index sur `date_point`, `date_rapport`, `created_at`)
- **Thème** : Les exports PDF héritent du logo `pdf_logo_img()` défini dans `helpers`

---

## 🚀 Déploiement

**Branche** : `master`  
**Depuis** : commit `891afca`  
**Jusqu'à** : commit `6d4c1ea`

**Actions recommandées** :
1. ✅ Pusher sur `master` (fait)
2. ⏳ Tester les exports en staging
3. ⏳ Vérifier les permissions utilisateur
4. ⏳ Notifier les superviseurs des nouveaux filtres PDG

---

## 📞 Support

Pour toute question sur ces changements, consulter les commit messages :
```bash
git log 891afca..6d4c1ea --oneline
```
