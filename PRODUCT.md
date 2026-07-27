# Product

## Register

product

## Users

**Direction générale (PDG, DG, rôle `lecteur`)** — consultent la vue exécutive quelques minutes par jour ou en comité mensuel, souvent sur portable ou tablette. Ils ne saisissent rien : ils cherchent à savoir si la production tient ses objectifs, où se situent les pertes, et quel site décroche.

**Superviseurs opérations et gestionnaires de stock** — usage quotidien, sur poste fixe. Ils arbitrent les réapprovisionnements, valident les points journaliers et traitent les écarts d'inventaire.

**Coordinateurs de site** — terrain, saisie des points journaliers (véhicules posés, films consommés, rivets). Contexte parfois dégradé : connexion lente, écran de petite taille.

Le métier : Express Multiservices CI produit et pose des plaques d'immatriculation sur un réseau de sites fixes et mobiles en Côte d'Ivoire. La matière première est le film (bobines séries A/B/C/D selon le type d'engin), le rivet et le PMMA.

## Product Purpose

DigiStock remplace un suivi Excel éclaté par un système unique : stock de bobines, consommation journalière par site, commandes, inventaires, réconciliation avec le système EMUCI/OptoPlate.

Le succès se mesure à trois choses : un point journalier saisi chaque jour ouvré par chaque site, un écart d'inventaire détecté en jours plutôt qu'en mois, et une direction capable de répondre « combien de jours de film nous reste-t-il ? » sans appeler un magasinier.

## Brand Personality

Sobre, factuel, sans esbroufe. L'interface parle en chiffres et en écarts, pas en superlatifs. Un indicateur qui va mal doit se voir sans être dramatisé ; un indicateur qui va bien ne mérite pas de célébration.

Trois mots : **précis, calme, direct**.

Le ton des libellés est celui d'un contremaître expérimenté : « 42 plaques non posées » plutôt que « opportunités manquées ».

## Anti-references

- **Les dashboards SaaS génériques** : gros chiffre + flèche verte + graphique décoratif qui ne se lit pas. Chaque chiffre affiché doit pouvoir déclencher une décision.
- **Les jauges et compteurs de type cockpit** (aiguilles, demi-cercles multicolores) : impressionnants en démo, illisibles en usage.
- **Le rouge partout.** Un seuil dépassé n'est pas une urgence. Réserver le registre d'alerte aux ruptures réelles et aux écarts majeurs non traités.
- **Les cartes empilées de taille identique** qui donnent le même poids visuel à un chiffre critique et à une statistique de contexte.

## Design Principles

1. **Le dénominateur avant le numérateur.** Un volume brut ne dit rien. 12 000 plaques posées n'a de sens qu'avec les non-posées à côté, et une consommation de film n'a de sens que rapportée au stock restant en jours.

2. **Nommer la perte.** Gâche matière, écart d'inventaire, demande non servie : ce sont les chiffres qui changent une décision. Ils passent devant les volumes.

3. **Un site en difficulté doit se repérer sans lire.** La ventilation par site est le niveau d'action réel de la direction. Forme et position portent l'information avant la couleur.

4. **Densité assumée.** Les utilisateurs connaissent leur métier. Mieux vaut un tableau dense et complet qu'une succession d'écrans à parcourir.

5. **Ne jamais afficher un chiffre non calculable.** Pas de zéro par défaut quand la donnée manque : un tiret et la raison. Un faux zéro coûte plus cher qu'une case vide.

## Accessibility & Inclusion

- Cible **WCAG 2.1 AA** : contraste 4.5:1 sur le texte courant, 3:1 sur les grands titres et les éléments d'interface.
- **Le mode sombre est de première classe**, pas un ajout. Il est piloté par `[data-theme="dark"]` et doit tenir les mêmes contrastes.
- **La couleur ne porte jamais seule une information.** Tout état signalé par une couleur l'est aussi par un libellé, un signe (↗ / ↘) ou une position. Une part notable des utilisateurs consulte l'outil sur des écrans mal calibrés en plein jour.
- `prefers-reduced-motion` respecté sur toute animation.
- Chiffres en `font-variant-numeric: tabular-nums` pour que les colonnes s'alignent.
- Navigation clavier complète avec `:focus-visible` visible sur fond clair comme sombre.
