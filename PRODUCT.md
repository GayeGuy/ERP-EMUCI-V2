# Product

## Register

product

## Users

Dix-sept rôles répartis sur vingt et un sites, autour de la pose de plaques
d'immatriculation et de la gestion du stock qui l'alimente.

Quatre profils portent l'essentiel de l'usage quotidien :

- **Coordinateur de site.** Sur le terrain, souvent au téléphone. Saisit le
  point journalier, déclare ses réceptions, demande des corrections quand il
  s'est trompé. Ne voit que son site, et c'est imposé par son rôle, pas
  choisi. Il ouvre la page pour savoir ce qu'il doit faire maintenant.
- **Superviseur opération.** Au bureau, écran large. Valide les points des
  coordinateurs, traite les demandes de correction, surveille les stocks
  matin. Il arbitre entre vingt et un sites : il a besoin de situer avant
  d'agir.
- **Gestionnaire de stock bobines.** Sert les commandes, suit les bobines
  actives par site, surveille les seuils. Son travail est une file d'attente.
- **Direction (vue PDG).** Consulte, ne saisit pas. Cherche la tendance et
  l'écart, pas la ligne de détail.

Le contexte d'usage est le début de journée : on ouvre la page pour savoir
où on en est, puis on part vers l'écran métier. Le tableau de bord n'est pas
un lieu de travail, c'est un point de départ.

## Product Purpose

DigiStock suit la production de plaques et la chaîne de stock qui l'alimente :
points journaliers par site, bobines, consommables, rivets, équipements,
maintenance, demandes internes.

Le tableau de bord répond à une seule question, posée différemment selon le
métier : « qu'est-ce qui me concerne aujourd'hui ». Il réussit quand
quelqu'un l'ouvre, comprend sa situation en quelques secondes, et sait sur
quoi cliquer.

## Brand Personality

Sobre, dense, factuel.

C'est un outil de travail interne, consulté tous les jours par les mêmes
personnes. Il ne cherche pas à impressionner : il cherche à ne pas faire
perdre de temps. Le chiffre juste, lisible du premier coup, prime sur
l'effet.

La vue PDG (`pages/pdg_overview.php`) est la référence de style de tout le
produit : cartes blanches à filet fin, chiffres larges et sombres, barres et
anneaux pour les répartitions, pastilles colorées pour les états. Le reste
du produit s'aligne dessus.

## Anti-references

- **Le dégradé et le liseré.** Le bandeau d'accueil en dégradé et les cartes
  KPI à liseré coloré ont été essayés puis abandonnés : ils faisaient
  décoratif sans rien apprendre. Ne pas y revenir.
- **L'emoji comme icône de section.** Retiré des titres. Un titre est un mot,
  pas un pictogramme.
- **La grille de cartes identiques.** Huit cartes de même forme et même
  hauteur empilées : c'est l'état dont cette page sort, et le défaut à ne pas
  reproduire. Si tout a le même poids visuel, rien n'est prioritaire.
- Le tableau de bord d'outil SaaS générique : grande métrique isolée en haut,
  trois statistiques de soutien, accent en dégradé.

## Design Principles

1. **Situer avant de détailler.** Chaque profil ouvre sur une synthèse
   chiffrée de son périmètre, puis descend vers la ligne de détail. Le
   détail sans repère ne dit rien.
2. **La forme encode l'urgence.** Ce qui demande une action se distingue par
   sa forme, pas seulement par son texte : pastille, couleur d'état, place
   dans la page. On doit repérer un problème sans lire.
3. **Le périmètre est explicite et unique.** Un seul objet décide de quoi la
   page parle, et tout s'y conforme. L'utilisateur sait toujours s'il regarde
   un site ou l'ensemble.
4. **L'absence de donnée est une information.** Un bloc vide dit pourquoi il
   est vide, et si c'est une bonne nouvelle. Jamais un cadre nu.
5. **Ce que la permission ne dit pas, le code ne l'invente pas.** Un rôle
   sans permission renseignée voit le profil par défaut plutôt qu'une page
   vide. La configuration manquante ne doit pas ressembler à un refus.

## Accessibility & Inclusion

- **WCAG 2.1 AA sur le contraste du texte**, vérifié en mesurant le rendu et
  non en relisant le CSS. Seuil 4,5:1 pour le texte courant, 3:1 pour le
  grand texte. Les libellés secondaires sont à 10-13 px, donc soumis au seuil
  strict : `var(--muted)` (#64748B, 4,33:1) ne convient pas pour eux.
- **Bureau et téléphone à parts égales.** Le coordinateur consulte depuis le
  terrain, le superviseur depuis son bureau. La mise en page étroite doit
  être aussi soignée que la large, pas une dégradation.
- **La couleur ne porte jamais seule une information.** Un état se lit aussi
  dans le mot de la pastille.
- `prefers-reduced-motion` respecté par le moteur d'animation partagé, qui
  pose directement les valeurs finales.
