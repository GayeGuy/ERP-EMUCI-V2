# Checklist Permissions — Actions Nouvelles (v2.1)

## 🔐 Résumé

Les 8 actions ont introduit **4 nouvelles utilisations de permissions** :

| Module | Permission | Nouvelle | Utilisée dans |
|--------|-----------|----------|----------------|
| `pmma` | `can_export` | ⚠️ NON CRÉÉE | Export PMMA (action 5) |
| `rivets` | `can_export` | ⚠️ NON CRÉÉE | Export Rivets (action 5) |
| `rapports` | `can_export` | ✅ Existante | PDF Rapports (action 4) |
| `rapport_journalier` | `can_export` | ⚠️ NON CRÉÉE | Export Rapports Journaliers (action 6) |

---

## ✅ Actions recommandées

### 1. **Ajouter permissions `pmma` → `can_export`**

Pour chaque rôle devant exporter les données PMMA :

```sql
-- Admin + SuperAdmin (déjà autorisés sur tout)
INSERT INTO permissions (role_id, module, can_export) 
  SELECT id, 'pmma', 1 FROM roles WHERE slug IN ('admin', 'superadmin')
  ON CONFLICT DO NOTHING;

-- Superviseur opérations
INSERT INTO permissions (role_id, module, can_export) 
  SELECT id, 'pmma', 1 FROM roles WHERE slug = 'superviseur_operation'
  ON CONFLICT DO NOTHING;

-- Gestionnaire stock bobines (accès PMMA)
INSERT INTO permissions (role_id, module, can_export) 
  SELECT id, 'pmma', 1 FROM roles WHERE slug = 'gestionnaire_stock_bobines'
  ON CONFLICT DO NOTHING;
```

**Rôles recommandés** : `admin`, `superadmin`, `superviseur_operation`, `gestionnaire_stock_bobines`

---

### 2. **Ajouter permissions `rivets` → `can_export`**

```sql
-- Mêmes rôles que PMMA (cohérence stock)
INSERT INTO permissions (role_id, module, can_export) 
  SELECT id, 'rivets', 1 FROM roles WHERE slug IN ('admin', 'superadmin', 'superviseur_operation', 'gestionnaire_stock_bobines')
  ON CONFLICT DO NOTHING;
```

**Rôles recommandés** : `admin`, `superadmin`, `superviseur_operation`, `gestionnaire_stock_bobines`

---

### 3. **Ajouter permissions `rapport_journalier` → `can_export`**

Uniquement pour les techniciens maintenance qui rédigent les rapports :

```sql
-- Maintenance info + superviseur IT
INSERT INTO permissions (role_id, module, can_export) 
  SELECT id, 'rapport_journalier', 1 FROM roles WHERE slug IN ('maintenance_info', 'superviseur_it', 'admin', 'superadmin')
  ON CONFLICT DO NOTHING;
```

**Rôles recommandés** : `admin`, `superadmin`, `superviseur_it`, `maintenance_info`

---

## 📋 Matrice complète (post-déploiement)

| Rôle | PMMA export | Rivets export | Rapport Journalier export |
|------|:-----------:|:--------------:|:------------------------:|
| `admin` | ✅ | ✅ | ✅ |
| `superadmin` | ✅ | ✅ | ✅ |
| `superviseur_operation` | ✅ | ✅ | — |
| `superviseur_it` | — | — | ✅ |
| `gestionnaire_stock_bobines` | ✅ | ✅ | — |
| `maintenance_info` | — | — | ✅ |
| `coordinateur_site` | — | — | — |
| `lecteur` | — | — | — |

---

## 🧪 Test d'accès

Après ajout des permissions, vérifier :

```bash
# 1. Vérifier la permission en base
SELECT * FROM permissions 
  WHERE module IN ('pmma', 'rivets', 'rapport_journalier')
  AND can_export = 1
  ORDER BY module, role_id;

# 2. Tester les exports avec un compte superviseur_operation
# URL : /pages/pmma.php?export=xlsx
# URL : /api/export.php?type=pmma
# URL : /api/export.php?type=rivets
```

---

## 🚀 Déploiement

**Avant d'activer** en production :

1. ⏳ Exécuter les INSERT SQL (étapes 1-3 ci-dessus)
2. ⏳ Tester accès avec chaque rôle listé
3. ⏳ Vérifier que les boutons d'export n'apparaissent que pour les rôles autorisés
4. ✅ Notifier superviseurs et gestionnaires des nouveaux exports

---

## 📞 Questions fréquentes

**Q: Un utilisateur n'a pas accès au bouton d'export PMMA ?**  
A: Vérifier que son rôle a la permission `pmma` → `can_export`.

**Q: Pourquoi le bouton PDF rapport ne s'affiche pas ?**  
A: Vérifier permission `rapports` → `can_export`. (C'est le module existant, pas nouveau.)

**Q: Peut-on exporter sans permission ?**  
A: Non. Les scripts `api/export.php` et `pages/rapports.php` vérifient `require_permission()` avant de générer le PDF/Excel.

---

**Dernière mise à jour** : 25/07/2026 après commit `6d4c1ea`
